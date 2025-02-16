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
    SelectBuilder,
    exceptions\InvalidArgumentException,
    holders\ParameterHolderFactory
};
use sad_spirit\pg_builder\{
    Insert,
    Statement
};

/**
 * Wrapper for SelectBuilder object passed as $values to GenericTableGateway::insert()
 */
readonly class InsertSelectFragment implements Fragment, Parametrized
{
    public function __construct(private SelectBuilder $select)
    {
    }

    public function applyTo(Statement $statement): void
    {
        if (!$statement instanceof Insert) {
            throw new InvalidArgumentException(\sprintf(
                "This fragment can only be added to INSERT statements, instance of %s given",
                $statement::class
            ));
        }
        $statement->values = $this->select->createSelectAST();
    }

    public function getPriority(): int
    {
        return Fragment::PRIORITY_HIGHEST;
    }

    public function getKey(): ?string
    {
        return $this->select->getKey();
    }

    public function getParameterHolder(): ParameterHolder
    {
        return ParameterHolderFactory::create($this->select);
    }
}
