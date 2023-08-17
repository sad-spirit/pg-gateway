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
    gateways\GenericTableGateway,
    gateways\PrimaryKeyTableGateway,
    gateways\CompositePrimaryKeyTableGateway,
    tests\DatabaseBackedTest
};
use sad_spirit\pg_builder\nodes\QualifiedName;

/**
 * Tests the behaviour of GenericTableGateway::create()
 */
class CreateTest extends DatabaseBackedTest
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

    public function testCreateGatewayForNoPrimaryKeyTable(): void
    {
        $gateway = GenericTableGateway::create(new QualifiedName('pkey_test', 'nokey'), self::$tableLocator);

        $this::assertInstanceOf(GenericTableGateway::class, $gateway);
        $this::assertNotInstanceOf(PrimaryKeyTableGateway::class, $gateway);
    }

    public function testCreateGatewayForSingleColumnPrimaryKey(): void
    {
        $gateway = GenericTableGateway::create(new QualifiedName('haskey'), self::$tableLocator);

        $this::assertInstanceOf(PrimaryKeyTableGateway::class, $gateway);
        $this::assertNotInstanceOf(CompositePrimaryKeyTableGateway::class, $gateway);
    }

    public function testCreateGatewayForCompositePrimaryKey(): void
    {
        $gateway = GenericTableGateway::create(new QualifiedName('pkey_test', 'composite'), self::$tableLocator);

        $this::assertInstanceOf(CompositePrimaryKeyTableGateway::class, $gateway);
    }
}
