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
 * Default implementation of TableDefinition interface
 *
 * This should work with ordinary tables, but not with views
 *
 * @since 0.2.0
 */
final class OrdinaryTableDefinition implements TableDefinition
{
    private ?metadata\TableColumns $columns = null;
    private ?metadata\TablePrimaryKey $primaryKey = null;
    private ?metadata\TableReferences $references = null;

    public function __construct(private readonly Connection $connection, private readonly metadata\TableName $name)
    {
    }

    public function getName(): metadata\TableName
    {
        return $this->name;
    }

    public function getColumns(): metadata\Columns
    {
        return $this->columns ??= new metadata\TableColumns($this->connection, $this->name);
    }

    public function getPrimaryKey(): metadata\PrimaryKey
    {
        return $this->primaryKey ??= new metadata\TablePrimaryKey($this->connection, $this->name);
    }

    public function getReferences(): metadata\References
    {
        return $this->references ??= new metadata\TableReferences($this->connection, $this->name);
    }
}
