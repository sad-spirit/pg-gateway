<?php

/*
 * This file is part of sad_spirit/pg_gateway:
 * Table Data Gateway for Postgres - auto-converts types, allows raw SQL, supports joins between gateways
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
    FragmentList,
    OrdinaryTableDefinition,
    TableSelect,
    TableLocator,
    builders\FluentBuilder,
    conditions\ParametrizedCondition,
    conditions\PrimaryKeyCondition,
    fragments\ClosureFragment,
    gateways\GenericTableGateway,
    metadata\TableName
};
use sad_spirit\pg_gateway\builders\proxies\ColumnsBuilderProxy;
use sad_spirit\pg_gateway\tests\assets\{
    ParametrizedFragmentImplementation,
    SelectFragmentImplementation
};
use sad_spirit\pg_builder\Select;
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    expressions\NamedParameter,
    expressions\OperatorExpression
};
use sad_spirit\pg_builder\Statement;

/**
 * Test for the class that actually executes SELECT queries
 *
 * NB: Reuses fixtures from DELETE tests
 */
class TableSelectTest extends DatabaseBackedTestCase
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

    private function createTableGateway(string ...$nameParts): GenericTableGateway
    {
        return new GenericTableGateway(
            new OrdinaryTableDefinition(self::$connection, new TableName(...$nameParts)),
            self::$tableLocator
        );
    }

    public function testGetIteratorNoFragments(): void
    {
        $gateway = $this->createTableGateway('victim');

        $this::assertEqualsCanonicalizing(
            [1, 2, 3, 10],
            $gateway->select()->getIterator()->fetchColumn('id')
        );
    }

    public function testExecuteCountNoFragments(): void
    {
        $gateway = $this->createTableGateway('foo');

        $this::assertEquals(3, $gateway->select()->executeCount());
    }

    public function testSelectWithClosure(): void
    {
        $gateway     = $this->createTableGateway('foo');
        $tableSelect = $gateway->selectWithAST(function (Select $select): void {
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
        $gateway     = $this->createTableGateway('bar');
        $tableSelect = $gateway->selectWithAST(
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
        $gateway     = $this->createTableGateway('victim');
        $tableSelect = $gateway->select(
            [
                new SelectFragmentImplementation(
                    function (Select $select): void {
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
        $gateway = $this->createTableGateway('foo');
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
        $gateway = $this->createTableGateway('foo');
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
        $gateway = $this->createTableGateway('foo');
        $select  = new TableSelect(
            self::$tableLocator,
            $gateway,
            (new FragmentList(new ClosureFragment(
                fn (Select $select) => $select->where->and('self.id = :id')
            )))
                ->mergeParameters(['id' => 1]),
            fn (): Statement => self::$tableLocator->createFromString(
                "select self.*, bar.name as bar_name from foo as self, bar where self.id = bar.id"
            )
        );

        $this::assertEquals(
            ['id' => 1, 'name' => 'one', 'bar_name' => 'some stuff'],
            $select->getIterator()->current()
        );
    }

    public function testFragmentsAddedToPassedFragmentListAreIgnored(): void
    {
        $fragmentList = new FragmentList();
        $tableGateway = $this->createTableGateway('foo');
        $tableSelect  = new TableSelect(self::$tableLocator, $tableGateway, $fragmentList);

        $this::assertEquals(3, $tableSelect->executeCount());

        $fragmentList->add(new ParametrizedCondition(
            new PrimaryKeyCondition(
                $tableGateway->getDefinition()->getPrimaryKey(),
                self::$tableLocator->getTypeConverterFactory()
            ),
            ['id' => 10]
        ));
        $this::assertEquals(3, $tableSelect->executeCount());
    }

    public function testFetchFirst(): void
    {
        $gateway = $this->createTableGateway('foo');
        $select  = $gateway->select(
            fn (FluentBuilder $fb): ColumnsBuilderProxy => $fb->orderBy('id desc')
                ->returningColumns()
                    ->primaryKey()
        );

        $this::assertEquals(['id' => 3], $select->fetchFirst());
    }
}
