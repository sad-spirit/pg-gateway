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

namespace sad_spirit\pg_gateway\fragments;

use sad_spirit\pg_gateway\{
    Fragment,
    ParameterHolder,
    Parametrized,
    SelectProxy,
    exceptions\InvalidArgumentException
};
use sad_spirit\pg_builder\{
    Insert,
    Statement
};

/**
 * Wrapper for SelectProxy object passed as $values to GenericTableGateway::insert()
 */
class InsertSelectFragment implements Fragment, Parametrized
{
    private SelectProxy $select;

    public function __construct(SelectProxy $select)
    {
        $this->select = $select;
    }

    public function applyTo(Statement $statement): void
    {
        if (!$statement instanceof Insert) {
            throw new InvalidArgumentException(\sprintf(
                "This fragment can only be added to INSERT statements, instance of %s given",
                \get_class($statement)
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

    public function getParameterHolder(): ?ParameterHolder
    {
        return $this->select->getParameterHolder();
    }
}
