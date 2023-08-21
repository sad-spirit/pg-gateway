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

namespace sad_spirit\pg_gateway\fragments\join_strategies;

use sad_spirit\pg_gateway\{
    TableGateway,
    exceptions\InvalidArgumentException,
    walkers\ReplaceTableAliasWalker
};
use sad_spirit\pg_builder\{
    Select,
    SelectCommon,
    Statement
};
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    Identifier,
    ScalarExpression,
    TargetElement,
    expressions\KeywordConstant,
    range\FromElement,
    range\JoinExpression,
    range\Subselect
};

/**
 * Adds the joined table to the base one using the explicit JOIN clause
 *
 * Join condition will be added via ON clause. If the joined Select
 *  - has multiple FROM elements
 *  - contains LIMIT / OFFSET / DISTINCT / GROUP BY / HAVING / WINDOW / locking clauses
 *  - is an OUTER JOIN and has a WHERE clause
 * then it will be wrapped in a sub-select when joining
 */
class ExplicitJoinStrategy extends SelectOnlyJoinStrategy
{
    protected const ALLOWED_TYPES = [
        JoinExpression::LEFT  => true,
        JoinExpression::RIGHT => true,
        JoinExpression::FULL  => true,
        JoinExpression::INNER => true
    ];

    public function __construct(string $joinType = JoinExpression::INNER)
    {
        $this->setJoinType($joinType);
    }

    public function join(
        Statement $statement,
        SelectCommon $joined,
        ?ScalarExpression $condition,
        string $alias,
        bool $isCount
    ): void {
        if (!$statement instanceof Select) {
            throw new InvalidArgumentException(\sprintf(
                "Explicit joins can only be performed with Select statements, instance of %s given",
                \get_class($statement)
            ));
        }

        $fromElement = $this->findNodeForJoin($statement->from, 'self');
        if ($this->requiresSubSelect($joined)) {
            $this->joinUsingSubselect($statement, $fromElement, $joined, $condition, $isCount);
        } else {
            $this->joinSimple($statement, $fromElement, $joined, $condition, $alias, $isCount);
        }
    }

    private function joinUsingSubselect(
        Select $select,
        FromElement $fromElement,
        SelectCommon $joined,
        ?ScalarExpression $condition,
        bool $isCount
    ): void {
        $subAlias  = $this->getSubselectAlias();
        $subselect = new Subselect($joined);
        $subselect->setAlias(new Identifier($subAlias));

        if (null !== $condition) {
            $condition->dispatch(new ReplaceTableAliasWalker(TableGateway::ALIAS_JOINED, $subAlias));
        } else {
            $condition = new KeywordConstant(KeywordConstant::TRUE);
        }
        $fromElement->join($subselect, $this->getJoinType())->on = $condition;

        if (!$isCount) {
            $select->list[] = new TargetElement(new ColumnReference($subAlias, '*'));
        }
    }

    private function joinSimple(
        Select $select,
        FromElement $fromElement,
        Select $joined,
        ?ScalarExpression $condition,
        string $alias,
        bool $isCount
    ): void {
        $select->with->merge($joined->with);

        if (null !== $condition) {
            $condition->dispatch(new ReplaceTableAliasWalker(TableGateway::ALIAS_JOINED, $alias));
        } else {
            $condition = new KeywordConstant(KeywordConstant::TRUE);
        }

        $fromElement->join($joined->from[0], $this->getJoinType())->on = $condition;

        $select->where->and($joined->where);

        if (!$isCount) {
            $select->list->merge($joined->list);
            $select->order->merge($joined->order);
        }
    }

    /**
     * Checks whether the joined query should be wrapped in a subselect
     *
     * @param SelectCommon $joined
     * @return bool
     * @psalm-assert-if-false Select $joined
     */
    private function requiresSubSelect(SelectCommon $joined): bool
    {
        return !$joined instanceof Select
            || \count($joined->from) > 1
            // We can merge the WHERE clauses in case of INNER JOIN, but not with OUTER
            || JoinExpression::INNER !== $this->getJoinType()
                && null !== $joined->where->condition
            || !InlineStrategy::canBeInlined($joined);
    }

    public function getKey(): ?string
    {
        return 'join-' . $this->getJoinType();
    }
}
