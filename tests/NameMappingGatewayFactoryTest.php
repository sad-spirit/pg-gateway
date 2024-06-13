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

use sad_spirit\pg_gateway\{
    NameMappingGatewayFactory,
    OrdinaryTableDefinition,
    TableLocator,
    metadata\TableName
};
use sad_spirit\pg_gateway\tests\assets\mapping\{
    ExplicitGateway,
    ExplicitSomething,
    StandardBuilder};

class NameMappingGatewayFactoryTest extends DatabaseBackedTest
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::executeSqlFromFile(
            self::$connection,
            'primary-key-drop.sql',
            'primary-key-create.sql',
        );
    }

    public static function tearDownAfterClass(): void
    {
        self::executeSqlFromFile(self::$connection, 'primary-key-drop.sql');
        self::$connection = null;
    }

    public function testCreateGatewayNoMapping(): void
    {
        $factory = new NameMappingGatewayFactory([]);
        $locator = new TableLocator(self::$connection);

        $this::assertNull($factory->createGateway(
            new OrdinaryTableDefinition(self::$connection, new TableName('pkey_test', 'explicit')),
            $locator
        ));
    }

    public function testCreateGateway(): void
    {
        $factory = new NameMappingGatewayFactory([
            'pkey_test' => [
                'sad_spirit\pg_gateway\tests\assets\mapping',
                'sad_spirit\pg_gateway\tests\assets\mapping'
            ]
        ]);
        $locator = new TableLocator(self::$connection);

        $this::assertInstanceOf(ExplicitGateway::class, $factory->createGateway(
            new OrdinaryTableDefinition(self::$connection, new TableName('pkey_test', 'explicit')),
            $locator
        ));
        $this::assertNull($factory->createGateway(
            new OrdinaryTableDefinition(self::$connection, new TableName('pkey_test', 'standard')),
            $locator
        ));
    }

    public function testCreateGatewayCustomTemplate(): void
    {
        $factory = new NameMappingGatewayFactory([
            'pkey_test' => [
                'sad_spirit\pg_gateway\tests\assets\mapping'
            ]
        ]);
        $factory->setGatewayClassNameTemplate('%sSomething');
        $locator = new TableLocator(self::$connection);

        $this::assertInstanceOf(ExplicitSomething::class, $factory->createGateway(
            new OrdinaryTableDefinition(self::$connection, new TableName('pkey_test', 'explicit')),
            $locator
        ));
    }

    public function testCreateBuilder(): void
    {
        $factory = new NameMappingGatewayFactory([
            'pkey_test' => [
                'sad_spirit\pg_gateway\tests\assets\mapping',
                'sad_spirit\pg_gateway\tests\assets\mapping'
            ]
        ]);
        $locator = new TableLocator(self::$connection);

        $this::assertInstanceOf(StandardBuilder::class, $factory->createBuilder(
            new OrdinaryTableDefinition(self::$connection, new TableName('pkey_test', 'standard')),
            $locator
        ));
        $this::assertNull($factory->createBuilder(
            new OrdinaryTableDefinition(self::$connection, new TableName('pkey_test', 'explicit')),
            $locator
        ));
    }
}
