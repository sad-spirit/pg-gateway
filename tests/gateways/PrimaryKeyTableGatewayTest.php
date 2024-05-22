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

namespace sad_spirit\pg_gateway\tests\gateways;

use sad_spirit\pg_gateway\{
    TableLocator,
    exceptions\InvalidArgumentException,
    exceptions\UnexpectedValueException,
    gateways\PrimaryKeyTableGateway,
    metadata\TableName,
    tests\DatabaseBackedTest
};

/**
 * Tests access by primary key and upsert() implementation
 */
class PrimaryKeyTableGatewayTest extends DatabaseBackedTest
{
    protected static ?TableLocator $tableLocator;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$tableLocator = new TableLocator(self::$connection);
        self::executeSqlFromFile(
            self::$connection,
            'primary-key-drop.sql',
            'primary-key-create.sql',
            'composite-primary-key-create.sql'
        );
    }

    public static function tearDownAfterClass(): void
    {
        self::executeSqlFromFile(self::$connection, 'primary-key-drop.sql');
        self::$tableLocator = null;
        self::$connection = null;
    }

    public function testMissingPrimaryKey(): void
    {
        $this::expectException(UnexpectedValueException::class);
        $this::expectExceptionMessage('No columns');
        $gateway = new PrimaryKeyTableGateway(new TableName('pkey_test', 'nokey'), self::$tableLocator);
        $gateway->selectByPrimaryKey(1);
    }

    public function testScalarValueForSingleColumnKey(): void
    {
        $gateway = new PrimaryKeyTableGateway(new TableName('public', 'haskey'), self::$tableLocator);
        $gateway->insert(['id' => 10, 'name' => 'A text value']);

        $gateway->updateByPrimaryKey(10, ['name' => 'Another value']);
        $this::assertEquals(
            ['id' => 10, 'name' => 'Another value'],
            $gateway->selectByPrimaryKey(10)->getIterator()->current()
        );

        $gateway->deleteByPrimaryKey(10);
        $this::assertEquals(0, $gateway->select()->executeCount());
    }

    public function testDisallowPrimaryKeyColumnInSet(): void
    {
        $this::expectException(UnexpectedValueException::class);
        $this::expectExceptionMessage("Multiple values");

        $gateway = new PrimaryKeyTableGateway(new TableName('public', 'haskey'), self::$tableLocator);
        $gateway->updateByPrimaryKey(10, ['id' => 20, 'name' => 'Changed name']);
    }

    public function testArrayForSingleColumnKey(): void
    {
        $gateway = new PrimaryKeyTableGateway(new TableName('public', 'haskey'), self::$tableLocator);
        $gateway->insert(['id' => 5, 'name' => 'Some name']);

        $this::assertEquals(
            ['id' => 5, 'name' => 'Some name'],
            $gateway->selectByPrimaryKey(['id' => 5])->getIterator()->current()
        );

        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage("column 'id' not found");
        $gateway->deleteByPrimaryKey(['foo' => 5]);
    }

    public function testUpsertGeneratedKey(): void
    {
        $gateway = new PrimaryKeyTableGateway(new TableName('pkey_test', 'standard'), self::$tableLocator);
        $pkey    = $gateway->upsert(['i_name' => 'Some name']);

        $this::assertArrayHasKey('i_id', $pkey);
        $this::assertIsInt($pkey['i_id']);

        $newKey  = $gateway->upsert($pkey + ['i_name' => 'Some new name']);
        $this::assertSame($pkey, $newKey);
    }

    public function testUpsertCompositeKey(): void
    {
        $gateway = new PrimaryKeyTableGateway(new TableName('pkey_test', 'composite'), self::$tableLocator);
        $pkey    = ['e_id' => 1, 's_id' => 2, 'i_id' => 3];

        $this::assertEquals($pkey, $gateway->upsert($pkey));
        // This should trigger an "ON CONFLICT" path
        $this::assertEquals($pkey, $gateway->upsert($pkey));

        $this::assertEquals($pkey, $gateway->selectByPrimaryKey($pkey)->getIterator()->current());
    }
}
