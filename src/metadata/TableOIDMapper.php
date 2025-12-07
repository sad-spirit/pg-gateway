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

namespace sad_spirit\pg_gateway\metadata;

use sad_spirit\pg_gateway\exceptions\InvalidArgumentException;

/**
 * Interface for classes that map OIDs to table names and can return info about relation type
 *
 * This is mostly needed to check `relkind` column of `pg_class` and create a proper implementation of
 * {@see \sad_spirit\pg_gateway\TableDefinition TableDefinition}.
 * Also table OIDs are returned by
 * {@see \sad_spirit\pg_wrapper\Result::getTableOID() Result::getTableOID()}
 * and may be used e.g. to map result rows to DTOs.
 *
 * @since 0.2.0
 */
interface TableOIDMapper
{
    /**
     * Finds an OID corresponding to the given relation name in loaded metadata
     *
     * @return int|numeric-string
     * @throws InvalidArgumentException
     */
    public function findOIDForTableName(TableName $name): int|string;

    /**
     * Finds a relation name corresponding to the given OID in loaded metadata
     *
     * @param int|numeric-string $oid
     * @throws InvalidArgumentException
     */
    public function findTableNameForOID(int|string $oid): TableName;

    /**
     * Finds the kind of relation (table, view) for the given name
     *
     * @throws InvalidArgumentException
     */
    public function findRelationKindForTableName(TableName $name): RelationKind;
}
