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

use sad_spirit\pg_gateway\TableGateway;
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    ScalarExpression
};
use sad_spirit\pg_builder\nodes\expressions\{
    NamedParameter,
    OperatorExpression,
    TypecastExpression
};
use sad_spirit\pg_gateway\metadata\Column;
use sad_spirit\pg_builder\converters\TypeNameNodeHandler;
use sad_spirit\pg_gateway\TableLocator;

/**
 * Generates a "self.foo OPERATOR :foo::foo_type" condition for the "foo" table column
 */
final class OperatorCondition extends TypedCondition
{
    public function __construct(
        Column $column,
        TypeNameNodeHandler $converterFactory,
        private readonly string $operator
    ) {
        parent::__construct($column, $converterFactory);
    }

    protected function generateExpressionImpl(): ScalarExpression
    {
        return new OperatorExpression(
            $this->operator,
            new ColumnReference(TableGateway::ALIAS_SELF, $this->column->name),
            new TypecastExpression(
                new NamedParameter($this->column->name),
                $this->converterFactory->createTypeNameNodeForOID($this->column->typeOID)
            )
        );
    }

    public function getKey(): ?string
    {
        return TableLocator::hash([static::class, $this->column, $this->operator]);
    }
}
