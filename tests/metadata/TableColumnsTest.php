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
    exceptions\UnexpectedValueException,
    metadata\Column,
    metadata\TableColumns,
    metadata\TableName,
    tests\DatabaseBackedTest
};

class TableColumnsTest extends DatabaseBackedTest
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::executeSqlFromFile(self::$connection, 'columns-drop.sql', 'columns-create.sql');
    }

    public static function tearDownAfterClass(): void
    {
        self::executeSqlFromFile(self::$connection, 'columns-drop.sql');
        self::$connection = null;
    }

    public function testFailsOnNonExistentRelation(): void
    {
        $this::expectException(UnexpectedValueException::class);
        $this::expectExceptionMessage('does not exist');
        new TableColumns(self::$connection, new TableName('cols_test', 'missing'));
    }

    public function testFailsOnNonTable(): void
    {
        $this::expectException(UnexpectedValueException::class);
        $this::expectExceptionMessage('is not an ordinary table');
        new TableColumns(self::$connection, new TableName('cols_test', 'notatable'));
    }

    public function testFailsOnZeroColumnTable(): void
    {
        $this::expectException(UnexpectedValueException::class);
        $this::expectExceptionMessage('has zero columns');
        new TableColumns(self::$connection, new TableName('cols_test', 'zerocolumns'));
    }

    public function testSimpleTable(): void
    {
        $cols = new TableColumns(self::$connection, new TableName('cols_test', 'simple'));

        $this::assertCount(2, $cols);
        $this::assertEquals(
            [
                'id'   => new Column('id', false, 23),
                'name' => new Column('name', true, 25)
            ],
            $cols->getAll()
        );
    }

    public function testTableWithDroppedColumns(): void
    {
        $cols = new TableColumns(self::$connection, new TableName('cols_test', 'hasdropped'));

        $this::assertCount(1, $cols);
        $this::assertArrayNotHasKey('bar', $cols->getAll());
    }

    public function testDomainType(): void
    {
        $cols = new TableColumns(self::$connection, new TableName('cols_test', 'hasdomain'));

        $this::assertEquals(['foo' => new Column('foo', true, 25)], $cols->getAll());
    }

    public function testMetadataIsStoredInCache(): void
    {
        self::$connection->setMetadataCache($this->getMockForCacheMiss(
            [
                'id'   => new Column('id', false, 23),
                'name' => new Column('name', true, 25)
            ]
        ));
        new TableColumns(self::$connection, new TableName('cols_test', 'simple'));
    }

    public function testMetadataIsLoadedFromCache(): void
    {
        self::$connection->setMetadataCache($this->getMockForCacheHit(
            [
                'id'   => new Column('id', false, 23),
                'name' => new Column('name', true, 25)
            ]
        ));
        $cols = new TableColumns(self::$connection, new TableName('cols_test', 'simple'));
        $this::assertEquals(
            [
                'id'   => new Column('id', false, 23),
                'name' => new Column('name', true, 25)
            ],
            $cols->getAll()
        );
    }
}
