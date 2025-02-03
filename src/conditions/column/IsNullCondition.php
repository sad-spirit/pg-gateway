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
use sad_spirit\pg_gateway\conditions\ColumnCondition;
use sad_spirit\pg_builder\enums\IsPredicate;
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    ScalarExpression,
    expressions\IsExpression
};

/**
 * Generates a "foo IS NULL" Condition for the "foo" table column
 */
final class IsNullCondition extends ColumnCondition
{
    protected function generateExpressionImpl(): ScalarExpression
    {
        return new IsExpression(
            new ColumnReference(TableGateway::ALIAS_SELF, $this->column->getName()),
            IsPredicate::NULL
        );
    }
}
