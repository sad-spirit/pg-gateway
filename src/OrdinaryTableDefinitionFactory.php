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

use sad_spirit\pg_gateway\{
    exceptions\InvalidArgumentException,
    metadata\TableName,
    metadata\TableOIDMapper
};
use sad_spirit\pg_wrapper\Connection;

/**
 * Creates an instance of OrdinaryTableDefinition for TableName corresponding to an ordinary table
 *
 * @since 0.2.0
 */
class OrdinaryTableDefinitionFactory implements TableDefinitionFactory
{
    private Connection $connection;
    private TableOIDMapper $mapper;

    private array $relationKindNames = [
        TableOIDMapper::RELKIND_ORDINARY_TABLE    => 'ordinary table',
        TableOIDMapper::RELKIND_VIEW              => 'view',
        TableOIDMapper::RELKIND_MATERIALIZED_VIEW => 'materialized view',
        TableOIDMapper::RELKIND_FOREIGN_TABLE     => 'foreign table',
        TableOIDMapper::RELKIND_PARTITIONED_TABLE => 'partitioned table'
    ];

    public function __construct(Connection $connection, TableOIDMapper $mapper)
    {
        $this->connection = $connection;
        $this->mapper = $mapper;
    }

    public function create(TableName $name): TableDefinition
    {
        if (TableOIDMapper::RELKIND_ORDINARY_TABLE === ($kind = $this->mapper->findRelationKindForTableName($name))) {
            return new OrdinaryTableDefinition($this->connection, $name);
        }
        throw new InvalidArgumentException(\sprintf(
            "Cannot create a TableDefinition for %s of type '%s'",
            $name->__toString(),
            $this->relationKindToName($kind)
        ));
    }

    protected function relationKindToName(string $relationKind): string
    {
        return $this->relationKindNames[$relationKind] ?? 'unknown';
    }
}
