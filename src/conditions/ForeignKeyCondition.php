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

namespace sad_spirit\pg_gateway\conditions;

use sad_spirit\pg_gateway\{
    Condition,
    TableGateway,
    TableLocator,
    metadata\ForeignKey
};
use sad_spirit\pg_builder\enums\LogicalOperator;
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    ScalarExpression,
    expressions\LogicalExpression,
    expressions\OperatorExpression
};

/**
 * Generates a join condition using the given foreign key constraint
 */
final class ForeignKeyCondition extends Condition
{
    public function __construct(private readonly ForeignKey $foreignKey, private readonly bool $fromChild = true)
    {
    }

    protected function generateExpressionImpl(): ScalarExpression
    {
        $expression      = [];
        $childAlias      = $this->fromChild ? TableGateway::ALIAS_SELF : TableGateway::ALIAS_JOINED;
        $referencedAlias = $this->fromChild ? TableGateway::ALIAS_JOINED : TableGateway::ALIAS_SELF;

        foreach ($this->foreignKey as $childColumn => $referencedColumn) {
            $expression[] = new OperatorExpression(
                '=',
                new ColumnReference($childAlias, $childColumn),
                new ColumnReference($referencedAlias, $referencedColumn)
            );
        }

        return new LogicalExpression($expression, LogicalOperator::AND);
    }

    public function getKey(): ?string
    {
        return TableLocator::hash([self::class, $this->foreignKey, $this->fromChild]);
    }
}
