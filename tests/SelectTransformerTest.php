<?php

/*
 * This file is part of sad_spirit/pg_gateway package
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/** @noinspection SqlResolve */

declare(strict_types=1);

namespace sad_spirit\pg_gateway\tests;

use sad_spirit\pg_gateway\{
    OrdinaryTableDefinition,
    SelectProxy,
    TableLocator,
    gateways\GenericTableGateway,
    metadata\TableName
};
use sad_spirit\pg_gateway\tests\assets\SelectTransformerImplementation;

class SelectTransformerTest extends DatabaseBackedTest
{
    use NormalizeWhitespace;

    protected static ?TableLocator $tableLocator;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$tableLocator = new TableLocator(self::$connection);
        self::executeSqlFromFile(self::$connection, 'delete-drop.sql', 'delete-create.sql');
    }

    public static function tearDownAfterClass(): void
    {
        self::executeSqlFromFile(self::$connection, 'delete-drop.sql');
        self::$tableLocator = null;
        self::$connection = null;
    }

    public function testKeyIsNullIfOwnKeyIsNull(): void
    {
        $tableLocator = new TableLocator(self::$connection, [], null, $this->getMockForNoCache());

        $mockSelect = $this::getMockBuilder(SelectProxy::class)
            ->onlyMethods(['getKey', 'createSelectAST'])
            ->getMockForAbstractClass();
        $mockSelect->expects($this::atLeastOnce())
            ->method('getKey')
            ->willReturn('a select key');
        $mockSelect->expects($this::atLeastOnce())
            ->method('createSelectAST')
            ->willReturnCallback(
                fn() => $tableLocator->getStatementFactory()->createFromString('select self.* from foo as self')
            );

        $transformer = new SelectTransformerImplementation($mockSelect, $tableLocator);

        $this::assertEquals(null, $transformer->getKey());
        $transformer->createSelectStatement();
    }

    public function testKeyIsNullIfSelectKeyIsNull(): void
    {
        $tableLocator = new TableLocator(self::$connection, [], null, $this->getMockForNoCache());

        $mockSelect = $this::getMockBuilder(SelectProxy::class)
            ->onlyMethods(['getKey', 'createSelectAST'])
            ->getMockForAbstractClass();
        $mockSelect->expects($this::atLeastOnce())
            ->method('getKey')
            ->willReturn(null);
        $mockSelect->expects($this::atLeastOnce())
            ->method('createSelectAST')
            ->willReturnCallback(
                fn() => $tableLocator->getStatementFactory()->createFromString('select self.* from foo as self')
            );

        $transformer = new SelectTransformerImplementation($mockSelect, $tableLocator, 'a transformer key');

        $this::assertEquals(null, $transformer->getKey());
        $transformer->createSelectStatement();
    }

    public function testDelegatesToWrapped(): void
    {
        $gateway     = new GenericTableGateway(
            new OrdinaryTableDefinition(self::$connection, new TableName('victim')),
            self::$tableLocator
        );
        $select      = $gateway->select(null, ['foo' => 'bar']);
        $transformer = new SelectTransformerImplementation($select, self::$tableLocator);

        $this::assertSame($select->getConnection(), $transformer->getConnection());
        $this::assertSame($select->getDefinition(), $transformer->getDefinition());
        $this::assertEquals($select->getParameterHolder(), $transformer->getParameterHolder());
    }

    public function testSelectCountIsNotTransformed(): void
    {
        $gateway     = new GenericTableGateway(
            new OrdinaryTableDefinition(self::$connection, new TableName('victim')),
            self::$tableLocator
        );
        $transformer = new SelectTransformerImplementation($gateway->select(), self::$tableLocator);

        $this::assertEquals(4, $transformer->executeCount());
    }

    public function testTransformSelect(): void
    {
        $gateway     = new GenericTableGateway(
            new OrdinaryTableDefinition(self::$connection, new TableName('victim')),
            self::$tableLocator
        );
        $transformer = new SelectTransformerImplementation($gateway->select(), self::$tableLocator, 'a key');

        $this::assertStringEqualsStringNormalizingWhitespace(
            "select self.* from public.victim as self union all select self.* from public.victim as self",
            $transformer->createSelectStatement()->getSql()
        );
    }
}
