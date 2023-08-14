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
use Psr\Cache\InvalidArgumentException;
use sad_spirit\pg_builder\nodes\QualifiedName;
use sad_spirit\pg_wrapper\Connection;

/**
 * Base class for classes loading various table metadata from system catalogs
 *
 * If the provided Connection object has a cache for metadata, that cache will be used to prevent querying
 * system catalogs on each object instantiation
 */
abstract class CachedMetadataLoader
{
    final public function __construct(Connection $connection, QualifiedName $table)
    {
        $cacheItem = null;
        if (null !== ($cache = $connection->getMetadataCache())) {
            try {
                $cacheItem = $cache->getItem($this->getCacheKey($connection, $table));
            } catch (InvalidArgumentException $e) {
            }
        }

        if (null !== $cacheItem && $cacheItem->isHit()) {
            $this->loadFromCache($cacheItem);
        } else {
            $this->loadFromDatabase($connection, $table);

            if ($cache && $cacheItem) {
                $cache->save($this->setCachedData($cacheItem));
            }
        }
    }

    /**
     * Returns the cache key under which this particular metadata will be stored
     *
     * @param Connection $connection
     * @param QualifiedName $table
     * @return string
     */
    abstract protected function getCacheKey(Connection $connection, QualifiedName $table): string;

    /**
     * Loads the metadata from Postgres system catalogs
     *
     * @param Connection $connection
     * @param QualifiedName $table
     */
    abstract protected function loadFromDatabase(Connection $connection, QualifiedName $table): void;

    /**
     * Loads the metadata from cache
     *
     * @param CacheItemInterface $cacheItem
     */
    abstract protected function loadFromCache(CacheItemInterface $cacheItem): void;

    /**
     * Sets the new value for the cached item
     *
     * @param CacheItemInterface $cacheItem
     * @return CacheItemInterface
     */
    abstract protected function setCachedData(CacheItemInterface $cacheItem): CacheItemInterface;
}
