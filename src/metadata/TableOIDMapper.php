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

namespace sad_spirit\pg_gateway\metadata;

use sad_spirit\pg_gateway\exceptions\InvalidArgumentException;

/**
 * Interface for classes that map OIDs to table names and can return info about relation type
 *
 * This is mostly needed to check relkind column of pg_class and create a proper implementation of TableDefinition.
 * Also table OIDs are returned by Result::getTableOID() and may be used e.g. to map result rows to DTOs.
 *
 * @since 0.2.0
 */
interface TableOIDMapper
{
    // The values for constants directly follow those in pg_class.relkind column
    // We don't care about indexes and sequences, so no constants for these here
    public const RELKIND_ORDINARY_TABLE    = 'r';
    public const RELKIND_VIEW              = 'v';
    public const RELKIND_MATERIALIZED_VIEW = 'm';
    public const RELKIND_FOREIGN_TABLE     = 'f';
    public const RELKIND_PARTITIONED_TABLE = 'p';

    /**
     * Finds an OID corresponding to the given relation name in loaded metadata
     *
     * @param TableName $name
     * @return int|numeric-string
     * @throws InvalidArgumentException
     */
    public function findOIDForTableName(TableName $name);

    /**
     * Finds a relation name corresponding to the given OID in loaded metadata
     *
     * @param int|numeric-string $oid
     * @return TableName
     * @throws InvalidArgumentException
     */
    public function findTableNameForOID($oid): TableName;

    /**
     * Finds the kind of relation (table, view) for the given name
     *
     * @param TableName $name
     * @return string
     * @psalm-return self::RELKIND_*
     * @throws InvalidArgumentException
     */
    public function findRelationKindForTableName(TableName $name): string;
}
