<?php

/*
 * This file is part of sad_spirit/pg_gateway:
 * Table Data Gateway for Postgres - auto-converts types, allows raw SQL, supports joins between gateways
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace sad_spirit\pg_gateway;

use sad_spirit\pg_gateway\exceptions\{
    InvalidArgumentException,
    LogicException
};
use sad_spirit\pg_gateway\holders\{
    ParameterHolderFactory,
    RecursiveParameterHolder,
    SimpleParameterHolder
};
use sad_spirit\pg_builder\Statement;

/**
 * A list of Fragments behaving as a single Fragment, also aggregates parameters
 *
 * This is used internally by GenericTableGateway to normalize whatever was passed to its methods as $fragments
 *
 * @implements \IteratorAggregate<int, Fragment>
 */
class FragmentList implements SelectFragment, Parametrized, \IteratorAggregate, \Countable
{
    /**
     * Fragments added to the list
     * @var array<int,Fragment>
     */
    private array $fragments = [];

    /**
     * Values for named parameters defined in the fragments
     * @var array<string,mixed>
     */
    private array $parameters = [];

    /**
     * Fragment key hash, prevents adding the same fragment multiple times
     * @var array<string, int>
     */
    private array $fragmentKeys = [];

    /**
     * Converts whatever was passed as $fragments parameter to an instance of FragmentList
     *
     * @throws InvalidArgumentException
     */
    public static function normalize(null|iterable|Fragment|FragmentBuilder $fragments): self
    {
        $arguments = [];
        if ($fragments instanceof Fragment || $fragments instanceof FragmentBuilder) {
            $arguments[] = $fragments;
        } elseif (\is_iterable($fragments)) {
            foreach ($fragments as $fragment) {
                if (!$fragment instanceof Fragment && !$fragment instanceof FragmentBuilder) {
                    throw new InvalidArgumentException(\sprintf(
                        "Expecting only implementations of Fragment or FragmentBuilder in iterable, %s given",
                        \is_object($fragment) ? 'object(' . $fragment::class . ')' : \gettype($fragment)
                    ));
                }
                $arguments[] = $fragment;
            }
        }

        return new self(...$arguments);
    }

    /**
     * Constructor, accepts Fragments and FragmentBuilders
     */
    public function __construct(Fragment|FragmentBuilder ...$fragments)
    {
        foreach ($fragments as $fragment) {
            $this->add($fragment);
        }
    }

    /**
     * Adds a fragment to the list
     *
     * Instances of FragmentList will be "flattened" with their items added rather than the list itself
     *
     * @return $this
     */
    public function add(Fragment|FragmentBuilder $fragment): self
    {
        if ($fragment instanceof self) {
            $this->mergeParameters($fragment->parameters, $fragment);
            foreach ($fragment as $inner) {
                $this->add($inner);
            }
        } elseif ($fragment instanceof FragmentBuilder) {
            $this->add($fragment->getFragment());
        } else {
            $fragmentKey = $fragment->getKey();
            if (null !== $fragmentKey && isset($this->fragmentKeys[$fragmentKey])) {
                $this->mergeDuplicateFragmentParameters($fragment, $this->fragmentKeys[$fragmentKey]);
            } else {
                $this->fragments[] = $fragment;
                if (null !== $fragmentKey) {
                    $this->fragmentKeys[$fragmentKey] = \count($this->fragments) - 1;
                }
            }
        }

        return $this;
    }


    /**
     * Ensures that parameters from Fragment with a duplicate key are not lost
     *
     * @param Fragment $fragment    Fragment being added
     * @param int      $existingIdx Index of the Fragment having the same key in $fragments array
     * @return void
     */
    private function mergeDuplicateFragmentParameters(Fragment $fragment, int $existingIdx): void
    {
        if (
            !$fragment instanceof Parametrized
            || [] === ($incomingHolder = $fragment->getParameterHolder())->getParameters()
        ) {
            return;
        }

        $existing = $this->fragments[$existingIdx];
        if (
            $existing instanceof Parametrized
            && [] !== ($existingHolder = $existing->getParameterHolder())->getParameters()
        ) {
            $addedParameters = \array_diff_key(
                (new RecursiveParameterHolder($existingHolder, $incomingHolder))
                    ->getParameters(),
                $existingHolder->getParameters()
            );
            if ([] !== $addedParameters) {
                $this->mergeParameters($addedParameters);
            }
        } else {
            $this->fragments[$existingIdx] = $fragment;
        }
    }

    /**
     * Adds values for several named parameters
     *
     * @param array<string,mixed> $parameters
     * @param KeyEquatable|null $owner This is needed for exception message produced by RecursiveParameterHolder only
     * @return $this
     */
    public function mergeParameters(array $parameters, ?KeyEquatable $owner = null): self
    {
        if ([] !== $parameters) {
            if ([] === $this->parameters) {
                $this->parameters = $parameters;
            } else {
                $this->parameters = (new RecursiveParameterHolder(
                    new SimpleParameterHolder($this, $this->parameters),
                    new SimpleParameterHolder($owner ?? $this, $parameters)
                ))->getParameters();
            }
        }

        return $this;
    }

    /**
     * Returns values for query parameters
     *
     * All parameter values are returned: those that were merged into the list itself and those that belong
     * to Parametrized fragments in the list
     *
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->getParameterHolder()->getParameters();
    }

    public function getParameterHolder(): RecursiveParameterHolder
    {
        return new RecursiveParameterHolder(
            new SimpleParameterHolder($this, $this->parameters),
            ParameterHolderFactory::create(...$this->fragments)
        );
    }

    public function getPriority(): never
    {
        // Priority doesn't make much sense for FragmentList
        throw new LogicException("getPriority() should not be called on FragmentList instances");
    }

    /**
     * Adds the contained fragments to the given statement
     */
    public function applyTo(Statement $statement, bool $isCount = false): void
    {
        foreach ($this->getSortedFragments() as $fragment) {
            if ($fragment instanceof SelectFragment) {
                $fragment->applyTo($statement, $isCount);
            } else {
                $fragment->applyTo($statement);
            }
        }
    }

    public function isUsedForCount(): never
    {
        // Should be called on owning Fragment rather than on FragmentList itself
        throw new LogicException("isUsedForCount() should not be called on FragmentList instances");
    }

    /**
     * Returns a string that uniquely identifies this fragment list
     *
     * The string is generated using the sorted fragment keys. If any of these keys is null,
     * this method will return null.
     */
    public function getKey(): ?string
    {
        if ([] === $this->fragments) {
            return 'empty';
        }

        $fragmentKeys = [];
        foreach ($this->fragments as $fragment) {
            if (null === $key = $fragment->getKey()) {
                return null;
            }
            $fragmentKeys[] = ['key' => $key, 'priority' => $fragment->getPriority()];
        }
        \usort($fragmentKeys, fn (array $a, array $b): int => ($b['priority'] <=> $a['priority'])
            ?: ($a['key'] <=> $b['key']));
        return TableLocator::hash(\array_map(fn ($a): string => $a['key'], $fragmentKeys));
    }

    /**
     * Returns fragments sorted by priority (higher first) and key (alphabetically)
     *
     * @return Fragment[]
     */
    public function getSortedFragments(): array
    {
        $fragments = $this->fragments;
        \usort($fragments, fn (Fragment $a, Fragment $b): int => ($b->getPriority() <=> $a->getPriority())
            ?: (\is_null($a->getKey()) <=> \is_null($b->getKey()))
            ?: ($a->getKey() <=> $b->getKey()));
        return $fragments;
    }

    /**
     * Filters the FragmentList using the given callback
     *
     * This uses array_filter() internally so callback should be compatible to that
     *
     * @param callable(Fragment): bool $callback
     */
    public function filter(callable $callback): self
    {
        return (new self(...\array_filter($this->fragments, $callback)))
            ->mergeParameters($this->parameters);
    }

    /**
     * {@inheritDoc}
     *
     * @return \ArrayIterator<int, Fragment>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->fragments);
    }

    public function count(): int
    {
        return \count($this->fragments);
    }
}
