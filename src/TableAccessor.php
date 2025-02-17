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

use sad_spirit\pg_wrapper\Connection;

/**
 * Interface for classes that execute queries accessing a specific table
 *
 * @since 0.2.0
 */
interface TableAccessor
{
    /**
     * Returns the DB connection object
     */
    public function getConnection(): Connection;

    /**
     * Returns the object containing table metadata
     */
    public function getDefinition(): TableDefinition;
}
