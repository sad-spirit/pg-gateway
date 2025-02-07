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

namespace sad_spirit\pg_gateway\tests\metadata;

use sad_spirit\pg_gateway\{
    exceptions\InvalidArgumentException,
    metadata\CachedTableOIDMapper,
    metadata\TableName,
    metadata\TableOIDMapper,
    tests\DatabaseBackedTestCase
};

class CachedTableOIDMapperTest extends DatabaseBackedTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::executeSqlFromFile(self::$connection, 'insert-drop.sql', 'insert-create.sql');
    }

    public static function tearDownAfterClass(): void
    {
        self::executeSqlFromFile(self::$connection, 'insert-drop.sql');
        self::$connection   = null;
    }

    public function testFindDataForUserTable(): void
    {
        $mapper = new CachedTableOIDMapper(self::$connection);

        $this::assertIsNumeric($oid = $mapper->findOIDForTableName(new TableName('insert_test')));
        $this::assertEquals(new TableName('insert_test'), $mapper->findTableNameForOID($oid));
        $this::assertEquals(
            TableOIDMapper::RELKIND_ORDINARY_TABLE,
            $mapper->findRelationKindForTableName(new TableName('insert_test'))
        );
    }

    public function testNoSystemTablesByDefault(): void
    {
        $mapper = new CachedTableOIDMapper(self::$connection);

        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('system schema');
        $mapper->findOIDForTableName(new TableName('pg_catalog', 'pg_class'));
    }

    public function testFindDataForSystemTable(): void
    {
        $mapper = new CachedTableOIDMapper(self::$connection, false);

        $this::assertEquals(new TableName('pg_catalog', 'pg_class'), $mapper->findTableNameForOID(1259));
        $this::assertEquals(1259, $mapper->findOIDForTableName(new TableName('pg_catalog', 'pg_class')));
        $this::assertEquals(
            TableOIDMapper::RELKIND_ORDINARY_TABLE,
            $mapper->findRelationKindForTableName(new TableName('pg_catalog', 'pg_class'))
        );
    }

    public function testFindForMissingTable(): void
    {
        $mapper = new CachedTableOIDMapper(self::$connection);

        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('does not exist');
        $mapper->findOIDForTableName(new TableName('missing'));
    }

    public function testFindForMissingOID(): void
    {
        $mapper = new CachedTableOIDMapper(self::$connection);

        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('could not find');
        $mapper->findTableNameForOID(24);
    }

    public function testLoadsFromCache(): void
    {
        $connection = clone self::$connection;
        $connection->setMetadataCache($this->getMockForCacheHit([
            'bar' => ['foo' => [666, TableOIDMapper::RELKIND_VIEW]]
        ]));

        $mapper = new CachedTableOIDMapper($connection);

        $this::assertEquals(666, $mapper->findOIDForTableName(new TableName('foo', 'bar')));
        $this::assertEquals(
            TableOIDMapper::RELKIND_VIEW,
            $mapper->findRelationKindForTableName(new TableName('foo', 'bar'))
        );
    }

    public function testLoadsFromDBOnCacheMiss(): void
    {
        $donor = new CachedTableOIDMapper(self::$connection);
        $donor->findOIDForTableName(new TableName('insert_test'));

        $reflectedNames = new \ReflectionProperty($donor, 'tableNames');

        $connection = clone self::$connection;
        $connection->setMetadataCache($this->getMockForCacheMiss($reflectedNames->getValue($donor)));
        $mapper = new CachedTableOIDMapper($connection);

        $this::assertIsNumeric($mapper->findOIDForTableName(new TableName('insert_test')));
    }
}
