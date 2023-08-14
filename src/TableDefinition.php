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

use sad_spirit\pg_builder\nodes\QualifiedName;
use sad_spirit\pg_wrapper\Connection;

/**
 * Interface for classes that have information about the properties of a specific table
 */
interface TableDefinition
{
    /**
     * Returns the DB connection object
     *
     * @return Connection
     */
    public function getConnection(): Connection;

    /**
     * Returns table name
     *
     * @return QualifiedName
     */
    public function getName(): QualifiedName;

    /**
     * Returns table columns
     *
     * @return metadata\Columns
     */
    public function getColumns(): metadata\Columns;

    /**
     * Returns information about table's primary key
     *
     * @return metadata\PrimaryKey
     */
    public function getPrimaryKey(): metadata\PrimaryKey;

    /**
     * Returns information about foreign keys added to the table / referencing it
     *
     * @return metadata\References
     */
    public function getReferences(): metadata\References;
}
