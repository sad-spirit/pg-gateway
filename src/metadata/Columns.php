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
use sad_spirit\pg_gateway\exceptions\OutOfBoundsException;
use sad_spirit\pg_gateway\exceptions\UnexpectedValueException;
use sad_spirit\pg_wrapper\Connection;

/**
 * Contains information about table columns
 *
 * @implements \IteratorAggregate<string, Column>
 */
class Columns extends CachedMetadataLoader implements \IteratorAggregate, \Countable
{
    private const QUERY = <<<'SQL'
        select a.attname, a.attnotnull, c.relkind,
               case when t.typbasetype <> 0 then t.typbasetype else t.oid end as typeoid
        from pg_catalog.pg_namespace as n,
             pg_catalog.pg_class as c
                left join pg_catalog.pg_attribute as a on a.attrelid = c.oid and a.attnum > 0 and not a.attisdropped
                left join pg_catalog.pg_type as t on a.atttypid = t.oid
        where c.relnamespace = n.oid and
              c.relname = $1 and
              n.nspname = $2
        order by a.attnum        
        SQL;

    /**
     * Table columns
     * @var array<string, Column>
     */
    private array $columns = [];

    protected function getCacheKey(Connection $connection, QualifiedName $table): string
    {
        return \sprintf('%s.attributes.%x', $connection->getConnectionId(), \crc32((string)$table));
    }

    protected function loadFromDatabase(Connection $connection, QualifiedName $table): void
    {
        $result = $connection->executeParams(self::QUERY, [
            $table->relation->value,
            $table->schema ? $table->schema->value : 'public'
        ]);
        if (0 === \count($result)) {
            throw new UnexpectedValueException(\sprintf("Relation %s does not exist", $table->__toString()));
        }
        foreach ($result as $row) {
            if ('r' !== $row['relkind']) {
                throw new UnexpectedValueException(\sprintf("Relation %s is not a table", $table->__toString()));
            } elseif (null === $row['attname']) {
                // Zero-column tables are possible in Postgres, but we won't bother with that
                throw new UnexpectedValueException(\sprintf("Table %s has zero columns", $table->__toString()));
            }
            $this->columns[$row['attname']] = new Column($row['attname'], !$row['attnotnull'], $row['typeoid']);
        }
    }

    protected function loadFromCache(CacheItemInterface $cacheItem): void
    {
        $this->columns = $cacheItem->get();
    }

    protected function setCachedData(CacheItemInterface $cacheItem): CacheItemInterface
    {
        return $cacheItem->set($this->columns);
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->columns);
    }

    public function count(): int
    {
        return \count($this->columns);
    }

    /**
     * Returns all columns
     *
     * @return array<string, Column>
     */
    public function getAll(): array
    {
        return $this->columns;
    }

    /**
     * Returns column names
     *
     * @return string[]
     */
    public function getNames(): array
    {
        return \array_keys($this->columns);
    }

    /**
     * Checks whether the column with a given name exists
     *
     * @param string $column
     * @return bool
     */
    public function has(string $column): bool
    {
        return \array_key_exists($column, $this->columns);
    }

    /**
     * Returns the given column's properties
     *
     * @param string $column
     * @return Column
     * @throws OutOfBoundsException If the column was not found
     */
    public function get(string $column): Column
    {
        if (!\array_key_exists($column, $this->columns)) {
            throw new OutOfBoundsException(\sprintf("Column %s does not exist", $column));
        }
        return $this->columns[$column];
    }
}
