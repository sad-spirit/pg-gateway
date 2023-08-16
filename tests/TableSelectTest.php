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
 * @noinspection SqlResolve
 */

declare(strict_types=1);

namespace sad_spirit\pg_gateway\tests;

use sad_spirit\pg_gateway\{
    TableSelect,
    TableLocator,
    gateways\GenericTableGateway
};
use sad_spirit\pg_gateway\tests\assets\{
    ParametrizedFragmentImplementation,
    SelectFragmentImplementation
};
use sad_spirit\pg_builder\Select;
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    QualifiedName,
    expressions\NamedParameter,
    expressions\OperatorExpression
};

/**
 * Test for the class that actually executes SELECT queries
 *
 * NB: Reuses fixtures from DELETE tests
 */
class TableSelectTest extends DatabaseBackedTest
{
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

    public function testGetIteratorNoFragments(): void
    {
        $gateway = new GenericTableGateway(new QualifiedName('victim'), self::$tableLocator);

        $this::assertEqualsCanonicalizing(
            [1, 2, 3, 10],
            $gateway->select()->getIterator()->fetchColumn('id')
        );
    }

    public function testExecuteCountNoFragments(): void
    {
        $gateway = new GenericTableGateway(new QualifiedName('foo'), self::$tableLocator);

        $this::assertEquals(3, $gateway->select()->executeCount());
    }

    public function testSelectWithClosure(): void
    {
        $gateway     = new GenericTableGateway(new QualifiedName('foo'), self::$tableLocator);
        $tableSelect = $gateway->select(function (Select $select) {
            $select->where->and('id = 2');
        });

        $this::assertEquals(
            ['id' => 2, 'name' => 'two'],
            $tableSelect->getIterator()->current()
        );
        $this::assertEquals(1, $tableSelect->executeCount());
    }

    public function testSelectWithClosureAndParameters(): void
    {
        $gateway = new GenericTableGateway(new QualifiedName('bar'), self::$tableLocator);
        $tableSelect = $gateway->select(
            fn (Select $select) => $select->where->and('foo_id = :foo_id and id > :id'),
            ['foo_id' => 2, 'id' => 2]
        );

        $this::assertEquals(
            ['id' => 3, 'foo_id' => 2, 'name' => 'a third one'],
            $tableSelect->getIterator()->current()
        );
        $this::assertEquals(1, $tableSelect->executeCount());
    }

    public function testIgnoredFragmentsOnSelectCount(): void
    {
        $gateway     = new GenericTableGateway(new QualifiedName('victim'), self::$tableLocator);
        $tableSelect = $gateway->select(
            [
                new SelectFragmentImplementation(
                    function (Select $select) {
                        $select->order->replace('id');
                        $select->offset = ':offset';
                    },
                    false
                ),
                new SelectFragmentImplementation(
                    fn (Select $select) => $select->where->and('id > :id'),
                    true
                )
            ],
            ['id' => 1, 'offset' => 1]
        );

        $this::assertEquals(
            [3, 10],
            $tableSelect->getIterator()->fetchColumn('id')
        );
        $this::assertEquals(3, $tableSelect->executeCount());
    }

    public function testSelectWithParameterHolder(): void
    {
        $gateway = new GenericTableGateway(new QualifiedName('foo'), self::$tableLocator);
        $select  = $gateway->select(new ParametrizedFragmentImplementation(
            new OperatorExpression(
                '<',
                new ColumnReference('id'),
                new NamedParameter('id')
            ),
            ['id' => 2],
            'holder'
        ));

        $this::assertEquals(
            ['id' => 1, 'name' => 'one'],
            $select->getIterator()->current()
        );
    }

    public function testSelectCountWithParameterHolder(): void
    {
        $gateway = new GenericTableGateway(new QualifiedName('foo'), self::$tableLocator);
        $select  = $gateway->select(new ParametrizedFragmentImplementation(
            new OperatorExpression(
                '>=',
                new ColumnReference('id'),
                new NamedParameter('id')
            ),
            ['id' => 2],
            'holder'
        ));

        $this::assertEquals(2, $select->executeCount());
    }

    public function testSelectWithCustomBaseAST(): void
    {
        $gateway = new GenericTableGateway(new QualifiedName('foo'), self::$tableLocator);
        $select  = new TableSelect(
            self::$tableLocator,
            $gateway,
            fn (Select $select) => $select->where->and('self.id = :id'),
            ['id' => 1],
            fn() => self::$tableLocator->createFromString(
                "select self.*, bar.name as bar_name from foo as self, bar where self.id = bar.id"
            )
        );

        $this::assertEquals(
            ['id' => 1, 'name' => 'one', 'bar_name' => 'some stuff'],
            $select->getIterator()->current()
        );
    }
}
