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
 * Default implementation of PrimaryKey interface
 *
 * This reads a primary key constraint info from the system catalog, so will only work for actual tables.
 * A different implementation will be required for e.g. a view that contains a primary key from the base table.
 *
 * @since 0.2.0
 */
class TablePrimaryKey extends CachedMetadataLoader implements PrimaryKey
{
    use ArrayOfPrimaryKeyColumns;

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

    protected function getCacheKey(Connection $connection, TableName $table): string
    {
        return \sprintf('%s.pkey.%x', $connection->getConnectionId(), \crc32((string)$table));
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
        $generated = false;
        foreach ($result as $row) {
            if (TableOIDMapper::RELKIND_ORDINARY_TABLE !== $row['relkind']) {
                throw new UnexpectedValueException(\sprintf(
                    "Relation %s is not an ordinary table",
                    $table->__toString()
                ));
            }
            if (null !== $row['attname']) {
                $this->columns[] = new Column($row['attname'], !$row['attnotnull'], $row['typeoid']);
            }
            $generated = $generated
                || 'a' === $row['attidentity']
                || 'd' === $row['attidentity']
                || str_starts_with((string)$row['defexpr'], 'nextval(');
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
}
