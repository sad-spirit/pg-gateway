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

use sad_spirit\pg_gateway\{
    exceptions\InvalidArgumentException,
    metadata\RelationKind,
    metadata\TableName,
    metadata\TableOIDMapper
};
use sad_spirit\pg_wrapper\Connection;

/**
 * Creates an instance of OrdinaryTableDefinition for TableName corresponding to an ordinary table
 *
 * @since 0.2.0
 */
final readonly class OrdinaryTableDefinitionFactory implements TableDefinitionFactory
{
    public function __construct(private Connection $connection, private TableOIDMapper $mapper)
    {
    }

    public function create(TableName $name): TableDefinition
    {
        if (RelationKind::OrdinaryTable === $kind = $this->mapper->findRelationKindForTableName($name)) {
            return new OrdinaryTableDefinition($this->connection, $name);
        }
        throw new InvalidArgumentException(\sprintf(
            "Cannot create a TableDefinition for %s of type '%s'",
            $name->__toString(),
            $kind->toReadable()
        ));
    }
}
