<?php

/*
 * This file is part of sad_spirit/pg_gateway:
 * Table Data Gateway for Postgres - auto-converts types, allows raw SQL, supports joins between gateways
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
        $connection->atomic(static function (Connection $connection) use ($queries): void {
            foreach ($queries as $query) {
                $connection->execute($query);
            }
        });
    }

    protected function getMockForNoCache(): CacheItemPoolInterface
    {
        $mockPool = $this->createMock(CacheItemPoolInterface::class);

        $mockPool->expects($this->never())
            ->method('getItem');

        return $mockPool;
    }

    protected function getMockForCacheMiss($value): CacheItemPoolInterface
    {
        $mockPool = $this->createMock(CacheItemPoolInterface::class);
        $mockItem = $this->createMock(CacheItemInterface::class);

        $mockPool->expects($this->atLeastOnce())
            ->method('getItem')
            ->willReturn($mockItem);

        $mockPool->expects($this->once())
            ->method('save')
            ->with($mockItem);

        $mockItem->expects($this->once())
            ->method('isHit')
            ->willReturn(false);

        $mockItem->expects($this->once())
            ->method('set')
            ->with($value)
            ->willReturnSelf();

        return $mockPool;
    }

    protected function getMockForCacheHit($value): CacheItemPoolInterface
    {
        $mockPool = $this->createMock(CacheItemPoolInterface::class);
        $mockItem = $this->createMock(CacheItemInterface::class);

        $mockPool->expects($this->once())
            ->method('getItem')
            ->willReturn($mockItem);

        $mockPool->expects($this->never())
            ->method('save');
        $mockItem->expects($this->once())
            ->method('isHit')
            ->willReturn(true);

        $mockItem->expects($this->once())
            ->method('get')
            ->willReturn($value);

        $mockItem->expects($this->never())
            ->method('set');

        return $mockPool;
    }
}
