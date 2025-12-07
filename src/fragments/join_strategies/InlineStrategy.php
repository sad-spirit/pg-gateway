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
    exceptions\UnexpectedValueException,
    fragments\JoinStrategy,
    walkers\ReplaceTableAliasWalker
};
use sad_spirit\pg_builder\{
    Delete,
    Select,
    SelectCommon,
    Statement,
    Update,
    nodes\ScalarExpression
};

/**
 * The most generic strategy, adds the joined table as another item to FROM (or USING) clause of the base statement
 *
 * This can be used to join to DELETE and UPDATE statements as well as to SELECT
 *
 * Select statement being joined can only contain
 *  - WITH clause
 *  - WHERE clause
 *  - ORDER BY clause
 * in addition to obvious target list and FROM clause, everything else will trigger an Exception.
 * All the clauses will be merged into the relevant clauses of the base statement.
 */
final class InlineStrategy implements JoinStrategy
{
    public function join(
        Statement $statement,
        SelectCommon $joined,
        ?ScalarExpression $condition,
        string $alias,
        bool $isCount
    ): void {
        if (!self::canBeInlined($joined)) {
            throw new UnexpectedValueException(
                "SELECT statement being joined contains either of"
                . " LIMIT / OFFSET / DISTINCT / GROUP BY / HAVING / WINDOW clauses"
                . " or a locking clause, cannot inline"
            );
        }
        if (!isset($statement->where)) {
            throw new InvalidArgumentException(\sprintf(
                "Joins can only be applied to Statements containing a WHERE clause, instance of %s given",
                $statement::class
            ));
        }
        /** @psalm-var Delete|Select|Update $statement */
        $statement->with->merge($joined->with);

        if ($statement instanceof Delete) {
            $statement->using->merge($joined->from);
        } else {
            $statement->from->merge($joined->from);
        }

        $statement->where->and($joined->where);
        if (null !== $condition) {
            $statement->where->and($condition);
            // Done after adding the condition, as it should have the parent node set
            $condition->dispatch(new ReplaceTableAliasWalker(TableGateway::ALIAS_JOINED, $alias));
        }

        if ($statement instanceof Select && !$isCount) {
            $statement->list->merge($joined->list);
            $statement->order->merge($joined->order);
        }
    }

    /**
     * Checks that the query being joined does not contain "fancy" clauses that cannot be safely inlined
     *
     * @param SelectCommon $select
     * @return bool
     * @psalm-assert-if-true Select $select
     */
    public static function canBeInlined(SelectCommon $select): bool
    {
        return $select instanceof Select
            && null === $select->limit
            && null === $select->offset
            && 0 === \count($select->locking)
            && false === $select->distinct
            && 0 === \count($select->group)
            && null === $select->having->condition
            && 0 === \count($select->window);
    }

    public function getKey(): ?string
    {
        return 'inline';
    }
}
