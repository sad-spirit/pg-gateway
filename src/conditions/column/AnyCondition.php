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
use sad_spirit\pg_builder\enums\ArrayComparisonConstruct;
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    ScalarExpression,
    expressions\ArrayComparisonExpression,
    expressions\NamedParameter,
    expressions\OperatorExpression,
    expressions\TypecastExpression
};

/**
 * Generates a "self.foo = any(:foo::foo_type[])" condition for the "foo" table column
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
                ArrayComparisonConstruct::ANY,
                new TypecastExpression(new NamedParameter($this->column->getName()), $typeName)
            )
        );
    }
}
