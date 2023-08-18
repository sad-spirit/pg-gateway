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
    ScalarExpression,
    expressions\ArrayComparisonExpression,
    expressions\NamedParameter,
    expressions\OperatorExpression,
    expressions\TypecastExpression
};

/**
 * Generates a "foo = any(:foo::foo_type[])" condition for the "foo" table column
 */
final class AnyCondition extends TypedCondition
{
    protected function generateExpressionImpl(): ScalarExpression
    {
        $typeName = $this->converterFactory->createTypeNameNodeForOID($this->column->getTypeOID());
        $typeName->bounds = [-1];

        return new OperatorExpression(
            '=',
            new ColumnReference(TableGateway::ALIAS_SELF, $this->column->getName()),
            new ArrayComparisonExpression(
                ArrayComparisonExpression::ANY,
                new TypecastExpression(new NamedParameter($this->column->getName()), $typeName)
            )
        );
    }
}
