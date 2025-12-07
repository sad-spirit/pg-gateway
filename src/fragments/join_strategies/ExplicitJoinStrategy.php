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

namespace sad_spirit\pg_gateway\fragments\join_strategies;

use sad_spirit\pg_gateway\{
    TableGateway,
    exceptions\InvalidArgumentException,
    exceptions\LogicException,
    walkers\ReplaceTableAliasWalker
};
use sad_spirit\pg_builder\{
    BlankWalker,
    Select,
    SelectCommon,
    SetOpSelect,
    Statement,
    enums\ConstantName,
    enums\JoinType
};
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    Identifier,
    ScalarExpression,
    Star,
    TargetElement,
    expressions\KeywordConstant,
    range\FromElement,
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
    public function __construct(public readonly ExplicitJoinType $joinType = ExplicitJoinType::Inner)
    {
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
                $statement::class
            ));
        }

        $condition   ??= new KeywordConstant(ConstantName::TRUE);
        $fromElement   = $this->findNodeForJoin($statement->from, 'self');
        if ($this->requiresSubSelect($joined)) {
            $this->joinUsingSubselect($statement, $fromElement, $joined, $condition, $alias, $isCount);
        } else {
            $this->joinSimple($statement, $fromElement, $joined, $condition, $alias, $isCount);
        }
    }

    public function getKey(): ?string
    {
        return 'join-' . $this->joinType->value;
    }

    private function joinUsingSubselect(
        Select $select,
        FromElement $fromElement,
        SelectCommon $joined,
        ScalarExpression $condition,
        string $alias,
        bool $isCount
    ): void {
        $subAlias  = $this->getSubselectAlias();
        $subselect = new Subselect($joined);
        $subselect->setAlias(new Identifier($subAlias));

        $columnAliases = null;
        if (!$condition instanceof KeywordConstant) {
            // When we are joining the $joined directly, we can reference all its columns in condition.
            // Once we wrap it in the subselect, however,
            // - only columns that are actually in the select list can be accessed outside;
            // - those may be aliased.

            // Step 1: find all columns of `joined` referenced in $condition
            $joinedColumns = $this->findReferencedColumns($condition, TableGateway::ALIAS_JOINED);
            if ([] !== $joinedColumns) {
                // Step 2: assert these appear as columns of `self` in $joined, find aliases, if any
                $columnAliases = $this->assertColumnsAreInSelectList($joined, $joinedColumns, $alias);
            }
        }

        $fromElement->join($subselect, JoinType::from($this->joinType->value))->on = $condition;
        // Done after adding the condition, as it should have the parent node set
        if (null !== $columnAliases) {
            // Step 3: replace the `joined` alias and rename columns to aliases
            $condition->dispatch(new ReplaceTableAliasWalker(
                TableGateway::ALIAS_JOINED,
                $subAlias,
                $columnAliases
            ));
        }

        if (!$isCount) {
            $select->list[] = new TargetElement(new ColumnReference($subAlias, '*'));
        }
    }

    private function joinSimple(
        Select $select,
        FromElement $fromElement,
        Select $joined,
        ScalarExpression $condition,
        string $alias,
        bool $isCount
    ): void {
        $select->with->merge($joined->with);

        $fromElement->join($joined->from[0], JoinType::from($this->joinType->value))->on = $condition;
        if (!$condition instanceof KeywordConstant) {
            // Done after adding the condition, as it should have the parent node set
            $condition->dispatch(new ReplaceTableAliasWalker(TableGateway::ALIAS_JOINED, $alias));
        }

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
            || ExplicitJoinType::Inner !== $this->joinType
                && null !== $joined->where->condition
            || !InlineStrategy::canBeInlined($joined);
    }

    /**
     * Finds names of columns with the given table $alias used in the given $expression
     *
     * @return string[]
     */
    private function findReferencedColumns(ScalarExpression $expression, string $alias): array
    {
        $walker = new class ($alias) extends BlankWalker {
            /** @var array<string, bool> */
            public array $columnsHash = [];
            public function __construct(private readonly string $alias)
            {
            }

            public function walkColumnReference(ColumnReference $node): null
            {
                if (
                    $this->alias === $node->relation?->value
                    // If set, then probably a field of a real table named $alias (e.g. "foo.$alias.bar") is accessed
                    && null === $node->schema
                    // Not really needed, but psalm complains otherwise
                    && $node->column instanceof Identifier
                ) {
                    $this->columnsHash[$node->column->value] = true;
                }
                return null;
            }
        };
        $expression->dispatch($walker);
        return \array_keys($walker->columnsHash);
    }

    /**
     * Checks that all the given columns appear in the SELECT list
     *
     * @param string[] $joinedColumns
     * @return array<string, string> Mapping 'column name' => 'alias' for the aliased columns
     * @throws LogicException
     */
    private function assertColumnsAreInSelectList(SelectCommon $select, array $joinedColumns, string $alias): array
    {
        while ($select instanceof SetOpSelect) {
            // Postgres will use aliases from the first SELECT for the result of set operations
            $select = $select->left;
        }
        if (!$select instanceof Select) {
            // $select may only be an instance of Values here, these have column1, ..., columnN aliases by default
            // and the package does not offer a means to change them
            return [];
        }
        // We don't use a walker as we only need to check top level expressions
        $columnsHash = [];
        /** @var TargetElement $item */
        foreach ($select->list as $item) {
            if (
                $item->expression instanceof ColumnReference
                && $alias === $item->expression->relation?->value
                && null === $item->expression->schema
            ) {
                if ($item->expression->column instanceof Star) {
                    // All columns selected, no aliases possible, good to go
                    return [];
                }
                $columnsHash[$item->expression->column->value] = $item->alias?->value;
            }
        }

        if ([] !== $missing = \array_diff($joinedColumns, \array_keys($columnsHash))) {
            $message = "Cannot wrap joined table in a subselect: " . (
                1 < \count($missing)
                    ? "columns '" . \implode("', '", $missing)
                    : "column '" . \reset($missing) . "'"
            ) . " for joined table "
            . (1 < \count($missing) ? 'are' : 'is') . " not selected, but present in join condition";
            throw new LogicException($message);
        }

        return \array_filter($columnsHash, static fn($value) => null !== $value);
    }
}
