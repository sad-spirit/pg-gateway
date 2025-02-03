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

namespace sad_spirit\pg_gateway\tests;

use PHPUnit\Framework\TestCase;
use Psr\Cache\{
    CacheItemInterface,
    CacheItemPoolInterface
};
use sad_spirit\pg_wrapper\Connection;

/**
 * Contains helper methods for setting up DB fixtures and creating mocks for metadata cache
 */
abstract class DatabaseBackedTestCase extends TestCase
{
    protected static ?Connection $connection = null;

    public static function setUpBeforeClass(): void
    {
        if ('' === CONNECTION_STRING) {
            self::fail('Connection string is not configured');
        }
        self::$connection = new Connection(CONNECTION_STRING);
    }

    protected static function executeSqlFromFile(Connection $connection, string ...$files): void
    {
        $queries = [];
        foreach ($files as $file) {
            if (false === ($contents = @\file_get_contents(__DIR__ . '/assets/' . $file))) {
                self::fail('Failed to read fixture file ' . $file);
            }
            $queries = [...$queries, ...\preg_split('/;\s*/', $contents, -1, \PREG_SPLIT_NO_EMPTY) ?: []];
        }
        $connection->atomic(static function (Connection $connection) use ($queries) {
            foreach ($queries as $query) {
                $connection->execute($query);
            }
        });
    }

    protected function getMockForNoCache(): CacheItemPoolInterface
    {
        $mockPool = $this->getMockBuilder(CacheItemPoolInterface::class)
            ->onlyMethods(['getItem'])
            ->getMockForAbstractClass();

        $mockPool->expects($this->never())
            ->method('getItem');

        return $mockPool;
    }

    protected function getMockForCacheMiss($value): CacheItemPoolInterface
    {
        $mockPool = $this->getMockBuilder(CacheItemPoolInterface::class)
            ->onlyMethods(['getItem', 'save'])
            ->getMockForAbstractClass();

        $mockItem = $this->getMockBuilder(CacheItemInterface::class)
            ->onlyMethods(['isHit', 'set'])
            ->getMockForAbstractClass();

        $mockPool->expects($this->atLeastOnce())
            ->method('getItem')
            ->will($this->returnValue($mockItem));

        $mockPool->expects($this->once())
            ->method('save')
            ->with($mockItem);

        $mockItem->expects($this->once())
            ->method('isHit')
            ->will($this->returnValue(false));

        $mockItem->expects($this->once())
            ->method('set')
            ->with($value)
            ->willReturnSelf();

        return $mockPool;
    }

    protected function getMockForCacheHit($value): CacheItemPoolInterface
    {
        $mockPool = $this->getMockBuilder(CacheItemPoolInterface::class)
            ->onlyMethods(['getItem', 'save'])
            ->getMockForAbstractClass();

        $mockItem = $this->getMockBuilder(CacheItemInterface::class)
            ->onlyMethods(['isHit', 'set'])
            ->getMockForAbstractClass();

        $mockPool->expects($this->once())
            ->method('getItem')
            ->will($this->returnValue($mockItem));

        $mockPool->expects($this->never())
            ->method('save');
        $mockItem->expects($this->once())
            ->method('isHit')
            ->will($this->returnValue(true));

        $mockItem->expects($this->once())
            ->method('get')
            ->will($this->returnValue($value));

        $mockItem->expects($this->never())
            ->method('set');

        return $mockPool;
    }
}
