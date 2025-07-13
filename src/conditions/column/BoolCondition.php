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

namespace sad_spirit\pg_gateway\conditions\column;

use sad_spirit\pg_gateway\{
    TableGateway,
    conditions\ColumnCondition,
    exceptions\LogicException,
    metadata\Column
};
use sad_spirit\pg_builder\nodes\ColumnReference;
use sad_spirit\pg_builder\nodes\ScalarExpression;

/**
 * Uses the value of the bool-typed column as a Condition
 */
final class BoolCondition extends ColumnCondition
{
    public const BOOL_OID = 16;

    public function __construct(Column $column)
    {
        if (self::BOOL_OID !== (int)$column->typeOID) {
            throw new LogicException("Column '$column->name' is not of type 'bool'");
        }
        parent::__construct($column);
    }

    protected function generateExpressionImpl(): ScalarExpression
    {
        return new ColumnReference(TableGateway::ALIAS_SELF, $this->column->name);
    }
}
