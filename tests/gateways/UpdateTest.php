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

use sad_spirit\pg_builder\Update;
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    Star,
    expressions\NamedParameter,
    expressions\OperatorExpression
};
use sad_spirit\pg_gateway\{
    OrdinaryTableDefinition,
    TableLocator,
    builders\ColumnsBuilder,
    builders\FluentBuilder,
    exceptions\UnexpectedValueException,
    gateways\GenericTableGateway,
    metadata\TableName
};
use sad_spirit\pg_gateway\tests\{
    DatabaseBackedTest,
    assets\ParametrizedFragmentImplementation
};

/**
 * Tests for update() method
 */
class UpdateTest extends DatabaseBackedTest
{
    protected static ?TableLocator $tableLocator;
    protected static ?GenericTableGateway $gateway;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$tableLocator = new TableLocator(self::$connection);
        self::executeSqlFromFile(self::$connection, 'update-drop.sql', 'update-create.sql');
        self::$gateway = new GenericTableGateway(
            new OrdinaryTableDefinition(self::$connection, new TableName('update_test')),
            self::$tableLocator
        );
    }

    public static function tearDownAfterClass(): void
    {
        self::executeSqlFromFile(self::$connection, 'update-drop.sql');
        self::$tableLocator = null;
        self::$gateway      = null;
        self::$connection   = null;
    }

    public function testUpdateWithoutFragments(): void
    {
        $gateway = new GenericTableGateway(
            new OrdinaryTableDefinition(self::$connection, new TableName('unconditional')),
            self::$tableLocator
        );
        $result  = $gateway->update(['title' => 'Only one']);

        $this::assertEquals(2, $result->getAffectedRows());
    }

    public function testUpdateWithAST(): void
    {
        $result = self::$gateway->updateWithAST(['title' => 'New title'], function (Update $update) {
            $update->where->and('id = 2');
            $update->returning[] = new Star();
        });
        $row = $result->current();
        $this::assertEquals(2, $row['id']);
        $this::assertEquals('New title', $row['title']);
    }

    public function testUpdateWithASTAndParameters(): void
    {
        $result = self::$gateway->updateWithAST(
            ['title' => 'Updated title', 'added' => '2022-01-10'],
            function (Update $update) {
                $update->where->and('id = :id');
                $update->returning[] = new Star();
            },
            ['id' => 3]
        );
        $row = $result->current();
        $this::assertEquals(3, $row['id']);
        $this::assertEquals('Updated title', $row['title']);
        $this::assertEquals('2022-01-10', $row['added']->format('Y-m-d'));
    }

    public function testUpdateWithClosure(): void
    {
        $result = self::$gateway->update(
            ['title' => 'Changed title'],
            fn(FluentBuilder $fb) => $fb->primaryKey(2)
                ->returningColumns(fn(ColumnsBuilder $cb) => $cb->all())
        );
        $row = $result->current();
        $this::assertEquals(2, $row['id']);
        $this::assertEquals('Changed title', $row['title']);
    }

    public function testDisallowColumnNamesInBothSetAndParameters(): void
    {
        $this::expectException(UnexpectedValueException::class);
        $this::expectExceptionMessage('Multiple values');

        self::$gateway->updateWithAST(
            ['id' => 666],
            function (Update $update) {
                $update->where->and('id = :id');
            },
            ['id' => 3]
        );
    }

    public function testUpdateWithParameterHolder(): void
    {
        $result = self::$gateway->update(
            ['title' => 'Too little too late'],
            new ParametrizedFragmentImplementation(
                new OperatorExpression(
                    '=',
                    new ColumnReference('id'),
                    new NamedParameter('id')
                ),
                ['id' => 4],
                'holder'
            )
        );

        $this::assertEquals(1, $result->getAffectedRows());
    }
}
