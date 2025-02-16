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

namespace sad_spirit\pg_gateway\fragments;

use sad_spirit\pg_gateway\{
    Fragment,
    ParameterHolder,
    Parametrized,
    SelectFragment,
    exceptions\InvalidArgumentException,
    holders\SimpleParameterHolder
};
use sad_spirit\pg_builder\Statement;

/**
 * A decorator around Fragment that keeps values of parameters used by that Fragment
 *
 * @since 0.2.0
 */
final readonly class ParametrizedFragment implements SelectFragment, Parametrized
{
    private Fragment $wrapped;

    /**
     * Constructor
     *
     * @param array<string, mixed> $parameters
     */
    public function __construct(Fragment $wrapped, private array $parameters)
    {
        if ($wrapped instanceof Parametrized) {
            throw new InvalidArgumentException(\sprintf(
                "%s already implements Parametrized interface",
                $wrapped::class
            ));
        }
        $this->wrapped = $wrapped;
    }

    public function getPriority(): int
    {
        return $this->wrapped->getPriority();
    }

    public function getKey(): ?string
    {
        return $this->wrapped->getKey();
    }

    public function getParameterHolder(): ParameterHolder
    {
        return new SimpleParameterHolder($this->wrapped, $this->parameters);
    }

    public function applyTo(Statement $statement, bool $isCount = false): void
    {
        if ($this->wrapped instanceof SelectFragment) {
            $this->wrapped->applyTo($statement, $isCount);
        } else {
            $this->wrapped->applyTo($statement);
        }
    }

    public function isUsedForCount(): bool
    {
        return !$this->wrapped instanceof SelectFragment || $this->wrapped->isUsedForCount();
    }
}
