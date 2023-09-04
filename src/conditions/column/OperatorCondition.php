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
class OperatorCondition extends TypedCondition
{
    private string $operator;

    public function __construct(Column $column, TypeNameNodeHandler $converterFactory, string $operator)
    {
        parent::__construct($column, $converterFactory);
        $this->operator = $operator;
    }

    protected function generateExpressionImpl(): ScalarExpression
    {
        return new OperatorExpression(
            $this->operator,
            new ColumnReference(TableGateway::ALIAS_SELF, $this->column->getName()),
            new TypecastExpression(
                new NamedParameter($this->column->getName()),
                $this->converterFactory->createTypeNameNodeForOID($this->column->getTypeOID())
            )
        );
    }

    public function getKey(): ?string
    {
        return TableLocator::hash([static::class, $this->column, $this->operator]);
    }
}
