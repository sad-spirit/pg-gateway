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

use Psr\Cache\CacheItemInterface;
use sad_spirit\pg_gateway\exceptions\InvalidArgumentException;
use sad_spirit\pg_wrapper\Connection;

/**
 * Default implementation of References interface
 *
 * This reads foreign key constraints info from the system catalog, so will only work for actual tables.
 * A different implementation will be required for e.g. a view that "inherits" foreign keys from base tables.
 *
 * @since 0.2.0
 */
class TableReferences extends CachedMetadataLoader implements References
{
    /** Query for FOREIGN KEY constraints added to the given table */
    private const QUERY_FROM = <<<'SQL'
        select tc.relname, tn.nspname, co.conname,
               array(
                    select attname
                    from pg_attribute, generate_subscripts(conkey, 1) as i
                    where attrelid = fc.oid and
                          attnum   = conkey[i]
                    order by i
               ) as child_columns,
               array(
                    select attname
                    from pg_attribute, generate_subscripts(confkey, 1) as i
                    where attrelid = tc.oid and
                          attnum   = confkey[i]
                    order by i
               ) as referenced_columns
        from pg_class as fc, pg_namespace as fn, pg_constraint as co, pg_class as tc, pg_namespace as tn
        where co.contype      = 'f' and
              fc.oid          = co.conrelid and
              tc.oid          = co.confrelid and
              fc.relnamespace = fn.oid and
              tc.relnamespace = tn.oid and
              fc.relname      = $1 and
              fn.nspname      = $2
        SQL;

    /** Query for FOREIGN KEY constraints referencing the given table */
    private const QUERY_TO = <<<'SQL'
        select fc.relname, fn.nspname, co.conname,
               array(
                    select attname
                    from pg_attribute, generate_subscripts(conkey, 1) as i
                    where attrelid = fc.oid and
                          attnum   = conkey[i]
                    order by i
               ) as child_columns,
               array(
                    select attname
                    from pg_attribute, generate_subscripts(confkey, 1) as i
                    where attrelid = tc.oid and
                          attnum   = confkey[i]
                    order by i
               ) as referenced_columns
        from pg_class as fc, pg_namespace as fn, pg_constraint as co, pg_class as tc, pg_namespace as tn
        where co.contype      = 'f' and
              co.conrelid    <> co.confrelid and
              fc.oid          = co.conrelid and
              tc.oid          = co.confrelid and
              fc.relnamespace = fn.oid and
              tc.relnamespace = tn.oid and
              tc.relname      = $1 and
              tn.nspname      = $2
        SQL;


    /** @var ForeignKey[] */
    private array $foreignKeys  = [];
    /** @var array<string, array<int, int>> */
    private array $referencing  = [];
    /** @var array<string, array<int, int>> */
    private array $referencedBy = [];

    protected function getCacheKey(Connection $connection, TableName $table): string
    {
        return sprintf('%s.references.%x', $connection->getConnectionId(), \crc32((string)$table));
    }

    protected function loadFromDatabase(Connection $connection, TableName $table): void
    {
        $tableStr = (string)$table;
        $index    = -1;

        foreach (
            [
                [self::QUERY_FROM, true],
                [self::QUERY_TO,   false]
            ] as [$query, $from]
        ) {
            foreach (
                $connection->executeParams(
                    $query,
                    [$table->getRelation(), $table->getSchema()]
                ) as $row
            ) {
                $relatedTable    = new TableName($row['nspname'], $row['relname']);
                $relatedTableStr = (string)$relatedTable;

                $this->foreignKeys[++$index] = new ForeignKey(
                    $from ? $table : $relatedTable,
                    $row['child_columns'],
                    $from ? $relatedTable : $table,
                    $row['referenced_columns'],
                    $row['conname']
                );

                if ($from) {
                    if (!isset($this->referencing[$relatedTableStr])) {
                        $this->referencing[$relatedTableStr]   = [$index];
                    } else {
                        $this->referencing[$relatedTableStr][] = $index;
                    }
                }
                if (!$from || $relatedTableStr === $tableStr) {
                    if (!isset($this->referencedBy[$relatedTableStr])) {
                        $this->referencedBy[$relatedTableStr]   = [$index];
                    } else {
                        $this->referencedBy[$relatedTableStr][] = $index;
                    }
                }
            }
        }
    }

    protected function loadFromCache(CacheItemInterface $cacheItem): void
    {
        [$this->foreignKeys, $this->referencing, $this->referencedBy] = $cacheItem->get();
    }

    protected function setCachedData(CacheItemInterface $cacheItem): CacheItemInterface
    {
        return $cacheItem->set([$this->foreignKeys, $this->referencing, $this->referencedBy]);
    }
    public function get(TableName $relatedTable, array $keyColumns = []): ForeignKey
    {
        $relatedStr = (string)$relatedTable;
        $keys       = \array_merge(
            $this->getMatchingKeys($this->referencedBy, $relatedStr, $keyColumns),
            \array_filter(
                $this->getMatchingKeys($this->referencing, $relatedStr, $keyColumns),
                fn(ForeignKey $key) => !$key->isRecursive()
            )
        );

        if ([] === $keys) {
            throw new InvalidArgumentException(\sprintf(
                "No matching foreign keys for %s%s",
                $relatedStr,
                [] === $keyColumns ? '' : ' using (' . \implode(', ', $keyColumns) . ')'
            ));
        } elseif (1 < \count($keys)) {
            throw new InvalidArgumentException(\sprintf(
                "Several matching foreign keys for %s%s: %s",
                $relatedStr,
                [] === $keyColumns ? '' : ' using (' . \implode(', ', $keyColumns) . ')',
                \implode(', ', \array_map(fn(ForeignKey $key) => $key->getConstraintName(), $keys))
            ));
        }

        return \reset($keys);
    }

    public function to(TableName $referencedTable, array $keyColumns = []): array
    {
        return $this->getMatchingKeys($this->referencing, (string)$referencedTable, $keyColumns);
    }

    public function from(TableName $childTable, array $keyColumns = []): array
    {
        return $this->getMatchingKeys($this->referencedBy, (string)$childTable, $keyColumns);
    }

    /**
     * Finds matching foreign keys using one of $referencing or $referencedBy arrays
     *
     * @param array    $source
     * @param string   $tableName
     * @param string[] $keyColumns
     * @return ForeignKey[]
     */
    private function getMatchingKeys(array $source, string $tableName, array $keyColumns = []): array
    {
        $result = [];
        foreach ($source[$tableName] ?? [] as $index) {
            $foreignKey = $this->foreignKeys[$index];
            if (
                [] === $keyColumns
                || $keyColumns === \array_intersect($keyColumns, $foreignKey->getChildColumns())
            ) {
                $result[] = $foreignKey;
            }
        }
        return $result;
    }

    /**
     * Method required by IteratorAggregate interface
     *
     * {@inheritDoc}
     * @return \ArrayIterator<int, ForeignKey>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->foreignKeys);
    }

    public function count(): int
    {
        return \count($this->foreignKeys);
    }
}
