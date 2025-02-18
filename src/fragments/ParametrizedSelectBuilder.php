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
    ParameterHolder,
    Parametrized,
    SelectBuilder,
    exceptions\InvalidArgumentException,
    holders\SimpleParameterHolder
};
use sad_spirit\pg_builder\SelectCommon;

/**
 * A decorator for SelectBuilder that keeps values of parameters used by that SelectBuilder
 *
 * @since 0.9.0
 */
final readonly class ParametrizedSelectBuilder implements SelectBuilder, Parametrized
{
    private SelectBuilder $wrapped;

    /** @param array<string, mixed> $parameters */
    public function __construct(SelectBuilder $wrapped, private array $parameters)
    {
        if ($wrapped instanceof Parametrized) {
            throw new InvalidArgumentException(\sprintf(
                "%s already implements Parametrized interface",
                $wrapped::class
            ));
        }
        $this->wrapped = $wrapped;
    }

    public function getKey(): ?string
    {
        return $this->wrapped->getKey();
    }

    public function getParameterHolder(): ParameterHolder
    {
        return new SimpleParameterHolder($this->wrapped, $this->parameters);
    }

    public function createSelectAST(): SelectCommon
    {
        return $this->wrapped->createSelectAST();
    }
}
