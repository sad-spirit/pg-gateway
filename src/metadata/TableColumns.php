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
use sad_spirit\pg_gateway\exceptions\UnexpectedValueException;
use sad_spirit\pg_wrapper\Connection;

/**
 * Default implementation of Columns interface
 *
 * This should probably work with views as well after overriding {@see assertCorrectRelkind()}
 *
 * @since 0.2.0
 */
class TableColumns extends CachedMetadataLoader implements Columns
{
    use ArrayOfColumns;

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

    protected function getCacheKey(Connection $connection, TableName $table): string
    {
        return \sprintf('%s.attributes.%x', $connection->getConnectionId(), \crc32((string)$table));
    }

    protected function loadFromDatabase(Connection $connection, TableName $table): void
    {
        $result = $connection->executeParams(self::QUERY, [
            $table->getRelation(),
            $table->getSchema()
        ]);
        if (0 === \count($result)) {
            throw new UnexpectedValueException(\sprintf("Relation %s does not exist", $table->__toString()));
        }
        foreach ($result as $index => $row) {
            if (0 === $index) {
                $this->assertCorrectRelkind($row['relkind'], $table);
                if (null === $row['attname']) {
                    // Zero-column tables are possible in Postgres, but we won't bother with that
                    throw new UnexpectedValueException(\sprintf("Table %s has zero columns", $table->__toString()));
                }
            }
            $this->columns[$row['attname']] = new Column($row['attname'], !$row['attnotnull'], $row['typeoid']);
        }
    }

    /**
     * Asserts that the relation we are loading columns for is of the correct kind
     *
     * This is an extension point for subclasses supporting something other than ordinary tables
     *
     * @param string $relKind
     * @param TableName $table
     * @return void
     */
    protected function assertCorrectRelkind(string $relKind, TableName $table): void
    {
        if ($relKind !== TableOIDMapper::RELKIND_ORDINARY_TABLE) {
            throw new UnexpectedValueException(\sprintf(
                "Relation %s is not an ordinary table",
                $table->__toString()
            ));
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
}
