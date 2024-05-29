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
     *
     * @return Connection
     */
    public function getConnection(): Connection;

    /**
     * Returns the object containing table metadata
     *
     * @return TableDefinition
     */
    public function getDefinition(): TableDefinition;
}
