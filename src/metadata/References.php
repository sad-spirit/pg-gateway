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
use sad_spirit\pg_builder\nodes\QualifiedName;
use sad_spirit\pg_gateway\exceptions\InvalidArgumentException;
use sad_spirit\pg_wrapper\Connection;

/**
 * Contains data about FOREIGN KEY constraints added to the table and those referencing it
 *
 * NB: For a recursive FOREIGN KEY (e.g. on a table storing a tree-like structure) a single record will be kept
 *
 * @implements \IteratorAggregate<int, ForeignKey>
 */
class References extends CachedMetadataLoader implements \IteratorAggregate, \Countable
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

    protected function getCacheKey(Connection $connection, QualifiedName $table): string
    {
        return sprintf('%s.references.%x', $connection->getConnectionId(), \crc32((string)$table));
    }

    protected function loadFromDatabase(Connection $connection, QualifiedName $table): void
    {
        $canonical    = $this->addPublicSchemaIfNotQualified($table);
        $canonicalStr = (string)$canonical;
        $index        = -1;

        foreach (
            [
                [self::QUERY_FROM, true],
                [self::QUERY_TO,   false]
            ] as [$query, $from]
        ) {
            foreach (
                $connection->executeParams(
                    $query,
                    [$canonical->relation->value, $canonical->schema ? $canonical->schema->value : 'public']
                ) as $row
            ) {
                $relatedTable    = new QualifiedName($row['nspname'], $row['relname']);
                $relatedTableStr = (string)$relatedTable;

                $this->foreignKeys[++$index] = new ForeignKey(
                    $from ? clone $canonical : $relatedTable,
                    $row['child_columns'],
                    $from ? $relatedTable : clone $canonical,
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
                if (!$from || $relatedTableStr === $canonicalStr) {
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

    private function addPublicSchemaIfNotQualified(QualifiedName $incoming): QualifiedName
    {
        return new QualifiedName(
            $incoming->schema ? $incoming->schema->value : 'public',
            $incoming->relation->value
        );
    }

    /**
     * Returns a ForeignKey object matching the given related table and constraint columns
     *
     * While $relatedTable should almost always be the "other" member of the foreign key constraint
     * (unless you are requesting a recursive foreign key), $keyColumns always represent the columns
     * of the child table, where the constraint is defined.
     *
     * Consider the following schema
     * <code>
     * create table documents (
     *     ...
     *     employee_id integer references employees (id),
     *     boss_id integer references employees (id),
     *     ...
     * );
     * </code>
     * Here specifying the constraint column "employee_id" or "boss_id" uniquely identifies the constraint,
     * while specifying referenced "id" column is useless, it will most likely be the primary key.
     *
     * @param QualifiedName $relatedTable
     * @param string[]      $keyColumns
     * @return ForeignKey
     * @throws InvalidArgumentException Unless exactly one matching reference is found
     */
    public function get(QualifiedName $relatedTable, array $keyColumns = []): ForeignKey
    {
        $canonical = (string)$this->addPublicSchemaIfNotQualified($relatedTable);
        $keys      = \array_merge(
            $this->getMatchingKeys($this->referencedBy, $canonical, $keyColumns),
            \array_filter(
                $this->getMatchingKeys($this->referencing, $canonical, $keyColumns),
                fn(ForeignKey $key) => !$key->isRecursive()
            )
        );

        if ([] === $keys) {
            throw new InvalidArgumentException(\sprintf(
                "No matching foreign keys for %s%s",
                $relatedTable->__toString(),
                [] === $keyColumns ? '' : ' using (' . \implode(', ', $keyColumns) . ')'
            ));
        } elseif (1 < \count($keys)) {
            throw new InvalidArgumentException(\sprintf(
                "Several matching foreign keys for %s%s: %s",
                $relatedTable->__toString(),
                [] === $keyColumns ? '' : ' using (' . \implode(', ', $keyColumns) . ')',
                \implode(', ', \array_map(fn(ForeignKey $key) => $key->getConstraintName(), $keys))
            ));
        }

        return \reset($keys);
    }

    /**
     * Returns foreign keys on the current table referencing the given one and containing the given columns
     *
     * @param QualifiedName $referencedTable Target table of the FOREIGN KEY constraint
     * @param string[]      $keyColumns      If empty, all keys to the referenced table will be returned
     * @return ForeignKey[]
     */
    public function to(QualifiedName $referencedTable, array $keyColumns = []): array
    {
        return $this->getMatchingKeys(
            $this->referencing,
            (string)$this->addPublicSchemaIfNotQualified($referencedTable),
            $keyColumns
        );
    }

    /**
     * Returns foreign keys defined on the given table referencing the current one and containing the given columns
     *
     * @param QualifiedName $childTable The table where FOREIGN KEY constraint is defined
     * @param string[]      $keyColumns If empty, all foreign keys defined on the table will be returned
     * @return ForeignKey[]
     */
    public function from(QualifiedName $childTable, array $keyColumns = []): array
    {
        return $this->getMatchingKeys(
            $this->referencedBy,
            (string)$this->addPublicSchemaIfNotQualified($childTable),
            $keyColumns
        );
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
