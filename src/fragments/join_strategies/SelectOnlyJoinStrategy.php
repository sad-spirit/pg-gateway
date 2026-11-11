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

use sad_spirit\pg_gateway\TableLocator;
use sad_spirit\pg_gateway\fragments\JoinStrategy;

/**
 * Base class for join strategies only applicable to Select base statements
 */
abstract class SelectOnlyJoinStrategy implements JoinStrategy
{
    use JoinTargetFinder;

    private ?string $joinedAlias = null;
    private ?string $subselectAlias = null;

    protected function setAliases(?string $joinedAlias = null, ?string $subselectAlias = null): void
    {
        $this->joinedAlias    = $joinedAlias;
        $this->subselectAlias = $subselectAlias;
    }

    /**
     * Returns the alias for the joined table
     *
     * If the table is wrapped in a subselect, this will be inaccessible in the outer query
     */
    public function getJoinedAlias(): string
    {
        return $this->joinedAlias ??= TableLocator::generateAlias();
    }

    /**
     * Returns the alias for a subselect in FROM clause, if available
     */
    public function getSubselectAlias(): ?string
    {
        return $this->subselectAlias;
    }
}
