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

use sad_spirit\pg_gateway\tests\DatabaseBackedTest;
use sad_spirit\pg_gateway\{
    TableLocator,
    exceptions\InvalidArgumentException,
    exceptions\UnexpectedValueException,
    gateways\GenericTableGateway
};
use sad_spirit\pg_builder\{
    Insert,
    Select,
    nodes\QualifiedName,
    nodes\SetTargetElement
};

/**
 * Tests for insert() method
 */
class InsertTest extends DatabaseBackedTest
{
    protected static ?TableLocator $tableLocator;
    protected static ?GenericTableGateway $gateway;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$tableLocator = new TableLocator(self::$connection);
        self::executeSqlFromFile(self::$connection, 'insert-drop.sql', 'insert-create.sql');
        self::$gateway = new GenericTableGateway(
            new QualifiedName('insert_test'),
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

        self::$gateway->insert(
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
        $this::expectExceptionMessage('an array, an instance of SelectCommon, an implementation of SelectProxy');

        self::$gateway->insert(new \stdClass());
    }

    public function testInsertWithDefaultValues(): void
    {
        $result = self::$gateway->insert([], function (Insert $insert) {
            $insert->returning[] = 'self.title';
        });

        $this::assertEquals(1, $result->getAffectedRows());
        $this::assertEquals('Some default title', $result[0]['title']);
    }

    public function testInsertWithArray(): void
    {
        $result = self::$gateway->insert(['title' => 'Some non-default title'], function (Insert $insert) {
            $insert->returning[] = 'self.title';
        });

        $this::assertEquals(1, $result->getAffectedRows());
        $this::assertEquals('Some non-default title', $result[0]['title']);
    }

    public function testInsertWithSelect(): void
    {
        /** @var Select $select */
        $select = self::$tableLocator->getStatementFactory()->createFromString(
            "select unnest(array['first title', 'second title'])"
        );
        $result = self::$gateway->insert($select, function (Insert $insert) {
            $insert->cols[] = new SetTargetElement('title');
            $insert->returning[] = 'self.title';
        });

        $this::assertEquals(2, $result->getAffectedRows());
        $this::assertEquals(['first title', 'second title'], $result->fetchColumn('title'));
    }
}
