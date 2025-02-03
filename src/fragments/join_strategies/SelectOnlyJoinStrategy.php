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
    TableLocator,
    exceptions\UnexpectedValueException,
    fragments\JoinStrategy
};
use sad_spirit\pg_builder\nodes\{
    lists\FromList,
    range\FromElement,
    range\JoinExpression,
    range\RelationReference
};

/**
 * Base class for join strategies only applicable to Select base statements
 */
abstract class SelectOnlyJoinStrategy implements JoinStrategy
{
    private ?string $alias = null;

    /**
     * Returns the alias for a subselect in FROM clause
     *
     * @return string
     */
    public function getSubselectAlias(): string
    {
        return $this->alias ??= TableLocator::generateAlias();
    }

    /**
     * Finds the item in FROM clause to which the JOIN will be applied
     *
     * NB: we do not use a Walker here, as the node for the base table should not be in sub-select,
     * it should either be a top-level member of FromList or participate in some JoinExpression
     *
     * A top-level FromElement is returned, as the base table could have had some (higher priority) joins
     * applied already, thus we should join to the result of the join, rather than to the table reference
     *
     * @param FromList $list
     * @param string $alias
     * @return FromElement
     */
    protected function findNodeForJoin(FromList $list, string $alias): FromElement
    {
        foreach ($list as $from) {
            if ($this->containsAliasedTable($from, $alias)) {
                return $from;
            }
        }
        throw new UnexpectedValueException("Table reference with '$alias' alias was not found in Select");
    }

    /**
     * Recursive part of {@see findNodeForJoin()}
     *
     * @param FromElement $from
     * @param string $alias
     * @return bool
     */
    private function containsAliasedTable(FromElement $from, string $alias): bool
    {
        if ($from instanceof RelationReference) {
            return null !== $from->tableAlias && $alias === $from->tableAlias->value;
        } elseif ($from instanceof JoinExpression) {
            return $this->containsAliasedTable($from->left, $alias)
                || $this->containsAliasedTable($from->right, $alias);
        } else {
            return false;
        }
    }
}
