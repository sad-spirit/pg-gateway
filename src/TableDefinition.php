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

/**
 * Interface for classes that have information about the properties of a specific table
 */
interface TableDefinition
{
    /**
     * Returns table name
     */
    public function getName(): metadata\TableName;

    /**
     * Returns table columns
     */
    public function getColumns(): metadata\Columns;

    /**
     * Returns information about table's primary key
     */
    public function getPrimaryKey(): metadata\PrimaryKey;

    /**
     * Returns information about foreign keys added to the table / referencing it
     */
    public function getReferences(): metadata\References;
}
