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

namespace sad_spirit\pg_gateway;

interface TableGatewayFactory
{
    /**
     * Creates a table data gateway for a given table name
     *
     * Should return `null` if it cannot find a specific gateway so that
     * {@see \sad_spirit\pg_gateway\TableLocator TableLocator} can fall back to a generic one
     */
    public function createGateway(TableDefinition $definition, TableLocator $tableLocator): ?TableGateway;

    /**
     * Creates a fluent builder for a given table name
     *
     * Should return `null` if it cannot find a specific builder so that
     * {@see \sad_spirit\pg_gateway\TableLocator TableLocator} can fall back to a generic one
     */
    public function createBuilder(
        TableDefinition $definition,
        TableLocator $tableLocator
    ): ?builders\FragmentListBuilder;
}
