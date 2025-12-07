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

namespace sad_spirit\pg_gateway\fragments;

use sad_spirit\pg_builder\{
    SelectCommon,
    Statement,
    nodes\ScalarExpression
};
use sad_spirit\pg_gateway\KeyEquatable;

/**
 * Encapsulates the logic of joining the Select into another Statement
 */
interface JoinStrategy extends KeyEquatable
{
    /**
     * Merges the Select with the Statement
     *
     * It is assumed that a unique alias for a joined table was already substituted into `$joined` and that "self" alias
     * (if present) references the base table. "joined" alias in `$condition` should be handled by a strategy.
     *
     * @param Statement             $statement Statement for the base table (aliased as "self")
     * @param SelectCommon          $joined    SELECT statement for the table being joined
     * @param ScalarExpression|null $condition JOIN condition (possibly empty)
     * @param string                $alias     Alias for joined table
     * @param bool                  $isCount   Whether the statement being generated is a "SELECT count(*)"
     * @return void
     */
    public function join(
        Statement $statement,
        SelectCommon $joined,
        ?ScalarExpression $condition,
        string $alias,
        bool $isCount
    ): void;
}
