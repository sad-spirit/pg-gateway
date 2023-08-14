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
use sad_spirit\pg_gateway\exceptions\UnexpectedValueException;
use sad_spirit\pg_wrapper\Connection;

/**
 * Contains information about table's primary key
 *
 * @implements \IteratorAggregate<int, Column>
 */
class PrimaryKey extends CachedMetadataLoader implements \IteratorAggregate, \Countable
{
    private const QUERY = <<<'SQL'
        select a.attname, a.attnotnull, a.attidentity, c.relkind, pg_get_expr(def.adbin, c.oid) as defexpr,
               case when t.typbasetype <> 0 then t.typbasetype else t.oid end as typeoid
        from pg_catalog.pg_namespace as n,
             pg_catalog.pg_class as c
                left join pg_catalog.pg_constraint as pk on pk.contype = 'p' and pk.conrelid = c.oid
                left join pg_catalog.pg_attribute as a on a.attrelid = c.oid and a.attnum = any(pk.conkey)
                left join pg_catalog.pg_type as t on a.atttypid = t.oid
                left join pg_catalog.pg_attrdef as def on c.oid = def.adrelid and a.attnum = def.adnum
        where c.relnamespace = n.oid and
              c.relname = $1 and
              n.nspname = $2
        order by a.attnum
        SQL;

    /**
     * Columns of the table's primary key
     * @var Column[]
     */
    protected array $columns = [];

    /**
     * Whether table's primary key is automatically generated
     * @var bool
     */
    protected bool $generated = false;

    protected function getCacheKey(Connection $connection, QualifiedName $table): string
    {
        return \sprintf('%s.pkey.%x', $connection->getConnectionId(), \crc32((string)$table));
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
        $generated = false;
        foreach ($result as $row) {
            if ('r' !== $row['relkind']) {
                throw new UnexpectedValueException(\sprintf("Relation %s is not a table", $table->__toString()));
            }
            if (null !== $row['attname']) {
                $this->columns[] = new Column($row['attname'], !$row['attnotnull'], $row['typeoid']);
            }
            $generated = $generated
                || 'a' === $row['attidentity']
                || 'd' === $row['attidentity']
                || 'nextval(' === \substr($row['defexpr'] ?? '', 0, 8);
        }
        $this->generated = $generated && 1 === \count($this->columns);
    }

    protected function loadFromCache(CacheItemInterface $cacheItem): void
    {
        [$this->columns, $this->generated] = $cacheItem->get();
    }

    protected function setCachedData(CacheItemInterface $cacheItem): CacheItemInterface
    {
        return $cacheItem->set([$this->columns, $this->generated]);
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->columns);
    }

    /**
     * Returns the number of columns in table's primary key
     *
     * {@inheritDoc}
     */
    public function count(): int
    {
        return \count($this->columns);
    }

    /**
     * Returns the columns of the table's primary key
     *
     * @return Column[]
     */
    public function getAll(): array
    {
        return $this->columns;
    }

    /**
     * Returns names of the columns in the table's primary key
     *
     * @return string[]
     */
    public function getNames(): array
    {
        return \array_map(fn(Column $column) => $column->getName(), $this->columns);
    }

    /**
     * Returns whether table's primary key is automatically generated
     *
     * @return bool
     */
    public function isGenerated(): bool
    {
        return $this->generated;
    }
}
