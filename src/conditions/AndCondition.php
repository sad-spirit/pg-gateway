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

use sad_spirit\pg_gateway\TableLocator;
use sad_spirit\pg_builder\nodes\{
    ScalarExpression,
    WhereOrHavingClause,
    expressions\KeywordConstant
};

/**
 * Combines several Conditions using AND operator
 */
final class AndCondition extends LogicalCondition
{
    protected function generateExpressionImpl(): ScalarExpression
    {
        $where = new WhereOrHavingClause();
        foreach ($this->children as $child) {
            $where->and($child->generateExpression());
        }

        return $where->condition ?? new KeywordConstant(KeywordConstant::TRUE);
    }

    public function getKey(): ?string
    {
        return null !== ($childKeys = $this->getChildKeys())
            ? 'and.' . TableLocator::hash($childKeys)
            : null;
    }
}
