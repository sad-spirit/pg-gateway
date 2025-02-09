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
use sad_spirit\pg_wrapper\Connection;
use Psr\Cache\InvalidArgumentException as PsrException;

/**
 * Implementation of TableOIDMapper loading data from DB or from cache
 *
 * @since 0.2.0
 */
class CachedTableOIDMapper implements TableOIDMapper
{
    private bool $loadedFromDB = false;

    /**
     * Relations for current database, loaded from pg_catalog.pg_class
     *
     * First array dimension is relation name, second is schema name. The value is an array of table OID and relkind
     * column
     *
     * @var array<string, array<string, array{int|numeric-string, RelationKind}>>
     */
    private array $tableNames = [];

    /**
     * Mapping 'table OID' => ['schema name', 'table name']
     *
     * This is built based on $tableNames, but not saved to cache
     *
     * @var array<array{string, string}>
     */
    private array $oidMap = [];


    public function __construct(
        private readonly Connection $connection,
        private readonly bool $ignoreSystemSchemas = true
    ) {
    }

    public function findOIDForTableName(TableName $name): int|string
    {
        return $this->findForTableName($name->getRelation(), $name->getSchema(), __METHOD__)[0];
    }

    public function findTableNameForOID($oid): TableName
    {
        if (!$this->loadedFromDB && [] === $this->oidMap) {
            $this->loadTableNames();
        }
        if (\array_key_exists($oid, $this->oidMap)) {
            return new TableName(...$this->oidMap[$oid]);
        }
        if (!$this->loadedFromDB) {
            $this->loadTableNames(true);
            return $this->findTableNameForOID($oid);
        }
        throw new InvalidArgumentException(\sprintf(
            "%s: could not find table name corresponding to OID %d",
            __METHOD__,
            $oid
        ));
    }

    public function findRelationKindForTableName(TableName $name): RelationKind
    {
        return $this->findForTableName($name->getRelation(), $name->getSchema(), __METHOD__)[1];
    }

    /**
     * Returns the value from $tableNames array keyed by the given $relation and $schema
     *
     * @return array{int|numeric-string, RelationKind}
     */
    private function findForTableName(string $relation, string $schema, string $method): array
    {
        if (!$this->loadedFromDB && [] === $this->tableNames) {
            $this->loadTableNames();
        }
        if (
            \array_key_exists($relation, $this->tableNames)
            && \array_key_exists($schema, $this->tableNames[$relation])
        ) {
            return $this->tableNames[$relation][$schema];
        }
        if ($this->ignoreSystemSchemas && $this->isSystemSchema($schema)) {
            throw new InvalidArgumentException(\sprintf(
                "%s: can not find data for system schema '%s' as loading such data is disabled",
                $method,
                $schema
            ));
        }
        if (!$this->loadedFromDB) {
            $this->loadTableNames(true);
            return $this->findForTableName($relation, $schema, $method);
        }
        throw new InvalidArgumentException(\sprintf(
            "%s: relation %s either does not exist or is of an unsupported kind",
            $method,
            (new TableName($schema, $relation))->__toString()
        ));
    }

    /**
     * Checks whether the given schema name corresponds to a system schema
     *
     * We consider SQL-standard 'information_schema' and names starting with 'pg_' as system
     */
    private function isSystemSchema(string $schema): bool
    {
        return 'information_schema' === $schema || str_starts_with($schema, 'pg_');
    }

    /**
     * Populates the relations list from pg_catalog.pg_class table
     *
     * @param bool $force Force loading from database even if cached list is available
     */
    private function loadTableNames(bool $force = false): void
    {
        $cacheItem = null;
        if ($cache = $this->connection->getMetadataCache()) {
            try {
                $cacheItem = $cache->getItem(
                    $this->connection->getConnectionId() . '-tables-'
                    . ($this->ignoreSystemSchemas ? 'user' : 'all')
                );
            } catch (PsrException) {
            }
        }

        if (!$force && null !== $cacheItem && $cacheItem->isHit()) {
            /** @psalm-suppress MixedAssignment */
            $this->tableNames      = $cacheItem->get();
            $this->loadedFromDB    = false;

        } else {
            $this->tableNames      = [];

            $sql = <<<SQL
select r.oid, relname, relkind, nspname
from pg_catalog.pg_class as r, pg_catalog.pg_namespace as s
where r.relnamespace = s.oid
      and r.relkind = any($1::char[])
SQL;
            if ($this->ignoreSystemSchemas) {
                $sql .= "     and nspname <> 'information_schema'\n      and nspname !~ '^pg_'\n";
            }
            $sql .= "order by 2, 4";

            $backingValues = \array_map(fn (RelationKind $kind): string => $kind->value, RelationKind::cases());
            /** @var array{oid: int|numeric-string, relname: string, relkind: string, nspname: string} $row */
            foreach ($this->connection->executeParams($sql, [$backingValues], ['text[]']) as $row) {
                $relkind = RelationKind::from($row['relkind']);
                if (!isset($this->tableNames[$row['relname']])) {
                    $this->tableNames[$row['relname']] = [$row['nspname'] => [$row['oid'], $relkind]];
                } else {
                    $this->tableNames[$row['relname']][$row['nspname']] = [$row['oid'], $relkind];
                }
            }

            if ($cache && $cacheItem) {
                $cache->save($cacheItem->set($this->tableNames));
            }

            $this->loadedFromDB = true;
        }

        $this->buildOIDMap();
    }

    /**
     * Builds mapping ['table OID' => ['schema name', 'table name']] using information from $tableNames
     */
    private function buildOIDMap(): void
    {
        $this->oidMap = [];
        foreach ($this->tableNames as $tableName => $schemas) {
            foreach ($schemas as $schemaName => [$oid,]) {
                $this->oidMap[$oid] = [$schemaName, $tableName];
            }
        }
    }
}
