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

namespace sad_spirit\pg_gateway\tests\assets;

use sad_spirit\pg_wrapper\Connection;
use sad_spirit\pg_gateway\{
    TableDefinition,
    metadata\Columns,
    metadata\PrimaryKey,
    metadata\References,
    metadata\TableName
};

/**
 * An implementation of TableDefinition interface used in tests
 */
class TableDefinitionImplementation implements TableDefinition
{
    private Connection $connection;
    private TableName $name;
    private ?Columns $columns = null;
    private ?PrimaryKey $primaryKey = null;
    private ?References $references = null;

    public function __construct(Connection $connection, TableName $name)
    {
        $this->connection = $connection;
        $this->name = $name;
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    public function getName(): TableName
    {
        return $this->name;
    }

    public function getColumns(): Columns
    {
        return $this->columns ??= new Columns($this->connection, $this->name);
    }

    public function getPrimaryKey(): PrimaryKey
    {
        return $this->primaryKey ??= new PrimaryKey($this->connection, $this->name);
    }

    public function getReferences(): References
    {
        return $this->references ??= new References($this->connection, $this->name);
    }
}
