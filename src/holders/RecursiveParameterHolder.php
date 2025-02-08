<?php

/*
 * This file is part of sad_spirit/pg_gateway package
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace sad_spirit\pg_gateway\holders;

use sad_spirit\pg_gateway\{
    KeyEquatable,
    ParameterHolder,
    exceptions\InvalidArgumentException,
    exceptions\UnexpectedValueException
};
use sad_spirit\pg_wrapper\exceptions\Stringifier;

/**
 * Recursive parameter holder, for Fragments/Conditions that may have Parametrized
 *
 * @implements \IteratorAggregate<int, ParameterHolder>
 */
final class RecursiveParameterHolder implements ParameterHolder, \IteratorAggregate
{
    use Stringifier;

    /** @psalm-var non-empty-array<ParameterHolder> */
    private array $holders;

    public function __construct(ParameterHolder ...$holders)
    {
        if ([] === $holders) {
            throw new InvalidArgumentException(\sprintf(
                '%s: at least one ParameterHolder is required',
                self::class
            ));
        }

        $this->holders = $holders;
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->holders);
    }

    public function getOwner(): KeyEquatable
    {
        return \reset($this->holders)->getOwner();
    }

    public function getParameters(): array
    {
        $parameters = [];
        $owners     = [];

        foreach ($this->flatten() as $holder) {
            foreach ($holder->getParameters() as $k => $v) {
                if (!\array_key_exists($k, $parameters)) {
                    $parameters[$k] = $v;
                    $owners[$k]     = $holder->getOwner();
                } elseif ($v !== $parameters[$k]) {
                    $ownerKey   = $owners[$k]->getKey();
                    $currentKey = $holder->getOwner()->getKey();
                    throw new UnexpectedValueException(\sprintf(
                        "Multiple values for parameter '%s' found: %s owned by object(%s) with %s"
                        . " and %s owned by object(%s) with %s",
                        $k,
                        self::stringify($parameters[$k]),
                        $owners[$k]::class,
                        null === $ownerKey ? 'null key' : "key '$ownerKey'",
                        self::stringify($v),
                        $holder->getOwner()::class,
                        null === $currentKey ? 'null key' : "key '$currentKey'"
                    ));
                }
            }
        }

        return $parameters;
    }

    /**
     * Returns a new instance of RecursiveParameterHolder that does not contain nested instances of self
     */
    public function flatten(): self
    {
        return [] === ($children = $this->flattenRecursive($this))
            ? new self(new EmptyParameterHolder())
            : new self(...$children);
    }

    /**
     * Recursive part of flatten(), converts a RecursiveParameterHolder to an array of non-recursive holders
     */
    private function flattenRecursive(self $holder): array
    {
        $flattened = [];

        foreach ($holder as $child) {
            if ($child instanceof self) {
                $flattened = \array_merge($flattened, $this->flattenRecursive($child));
            } elseif (!$child instanceof EmptyParameterHolder) {
                $flattened[] = $child;
            }
        }

        return $flattened;
    }
}
