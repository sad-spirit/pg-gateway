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

namespace sad_spirit\pg_gateway;

interface TableGatewayFactory
{
    /**
     * Creates a table data gateway for a given table name
     *
     * Should return null if it cannot find a specific gateway so that TableLocator can fall back to a generic one
     *
     * @param TableDefinition $definition
     * @param TableLocator $tableLocator
     *
     * @return null|TableGateway
     */
    public function create(TableDefinition $definition, TableLocator $tableLocator): ?TableGateway;
}
