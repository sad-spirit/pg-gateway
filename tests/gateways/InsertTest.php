<?php

/*
 * This file is part of sad_spirit/pg_gateway package
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * @noinspection SqlWithoutWhere
 * @noinspection SqlResolve
 */

declare(strict_types=1);

namespace sad_spirit\pg_gateway\tests\gateways;

use sad_spirit\pg_gateway\tests\DatabaseBackedTestCase;
use sad_spirit\pg_gateway\{
    OrdinaryTableDefinition,
    TableLocator,
    builders\ColumnsBuilder,
    builders\FluentBuilder,
    exceptions\InvalidArgumentException,
    exceptions\UnexpectedValueException,
    gateways\GenericTableGateway,
    metadata\TableName
};
use sad_spirit\pg_builder\{
    Insert,
    Select,
    nodes\SetTargetElement
};

/**
 * Tests for insert() method
 */
class InsertTest extends DatabaseBackedTestCase
{
    protected static ?TableLocator $tableLocator;
    protected static ?GenericTableGateway $gateway;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$tableLocator = new TableLocator(self::$connection);
        self::executeSqlFromFile(self::$connection, 'insert-drop.sql', 'insert-create.sql');
        self::$gateway = new GenericTableGateway(
            new OrdinaryTableDefinition(self::$connection, new TableName('insert_test')),
            self::$tableLocator
        );
    }

    public static function tearDownAfterClass(): void
    {
        self::executeSqlFromFile(self::$connection, 'insert-drop.sql');
        self::$gateway      = null;
        self::$tableLocator = null;
        self::$connection   = null;
    }

    public function testDisallowColumnNamesInBothValuesAndParameters(): void
    {
        $this::expectException(UnexpectedValueException::class);
        $this::expectExceptionMessage('Multiple values');

        self::$gateway->insertWithAST(
            ['id' => 666],
            function (Insert $insert) {
                $insert->returning[] = ':id';
            },
            ['id' => 3]
        );
    }

    public function testDisallowInvalidValues(): void
    {
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('an array, an instance of SelectCommon, an implementation of SelectBuilder');

        self::$gateway->insert(new \stdClass());
    }

    public function testInsertWithDefaultValues(): void
    {
        $result = self::$gateway->insertWithAST([], function (Insert $insert) {
            $insert->returning[] = 'self.title';
        });

        $this::assertEquals(1, $result->getAffectedRows());
        $this::assertEquals('Some default title', $result[0]['title']);
    }

    public function testInsertWithArray(): void
    {
        $result = self::$gateway->insertWithAST(['title' => 'Some non-default title'], function (Insert $insert) {
            $insert->returning[] = 'self.title';
        });

        $this::assertEquals(1, $result->getAffectedRows());
        $this::assertEquals('Some non-default title', $result[0]['title']);
    }

    public function testInsertWithSelectNode(): void
    {
        /** @var Select $select */
        $select = self::$tableLocator->createFromString(
            "select unnest(array['first title', 'second title'])"
        );
        $result = self::$gateway->insertWithAST($select, function (Insert $insert) {
            $insert->cols[] = new SetTargetElement('title');
            $insert->returning[] = 'self.title';
        });

        $this::assertEquals(2, $result->getAffectedRows());
        $this::assertEquals(['first title', 'second title'], $result->fetchColumn('title'));
    }

    public function testInsertWithSelectProxy(): void
    {
        $sourceGateway = new GenericTableGateway(
            new OrdinaryTableDefinition(self::$connection, new TableName('source_test')),
            self::$tableLocator
        );

        $result = self::$gateway->insert(
            $sourceGateway->selectWithAST(
                fn (Select $select) => $select->where->and('id = :id'),
                ['id' => -2]
            ),
            fn (FluentBuilder $fb) => $fb->returningColumns(fn (ColumnsBuilder $cb) => $cb->only(['title']))
        );

        $this::assertEquals(1, $result->getAffectedRows());
        $this::assertEquals('Minus second title', $result[0]['title']);
    }
}
