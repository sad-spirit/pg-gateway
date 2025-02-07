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

use sad_spirit\pg_builder\Delete;
use sad_spirit\pg_builder\enums\ConstantName;
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    Star,
    expressions\KeywordConstant,
    expressions\NamedParameter,
    expressions\OperatorExpression
};
use sad_spirit\pg_gateway\{
    OrdinaryTableDefinition,
    TableLocator,
    builders\ColumnsBuilder,
    builders\FluentBuilder,
    conditions\SqlStringCondition,
    gateways\GenericTableGateway,
    metadata\TableName
};
use sad_spirit\pg_gateway\builders\proxies\ColumnsBuilderProxy;
use sad_spirit\pg_gateway\tests\{
    DatabaseBackedTestCase,
    assets\FragmentImplementation,
    assets\ParametrizedFragmentImplementation
};

/**
 * Tests for delete() method
 */
class DeleteTest extends DatabaseBackedTestCase
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

    public function testDeleteWithoutFragments(): void
    {
        $gateway = $this->createTableGateway('victim');
        $result  = $gateway->delete();

        $this::assertEquals(4, $result->getAffectedRows());
    }

    public function testDeleteWithAST(): void
    {
        $gateway = $this->createTableGateway('bar');
        $result  = $gateway->deleteWithAST(function (Delete $delete): void {
            $delete->where->and('id = 1');
            $delete->returning[] = new Star();
        });

        $this::assertEquals(1, $result->getAffectedRows());
        $this::assertEquals('some stuff', $result[0]['name']);
    }

    public function testDeleteWithASTAndParameters(): void
    {
        $gateway = $this->createTableGateway('foo');
        $result  = $gateway->deleteWithAST(
            function (Delete $delete): void {
                $delete->where->and('id = :param');
                $delete->returning[] = new Star();
            },
            ['param' => 3]
        );

        $this::assertEquals(1, $result->getAffectedRows());
        $this::assertEquals('many', $result[0]['name']);
    }

    public function testDeleteWithClosure(): void
    {
        $gateway = $this->createTableGateway('bar');
        $result  = $gateway->delete(
            fn(FluentBuilder $fb): ColumnsBuilderProxy => $fb->equal('id', 2)
                ->returningColumns(fn(ColumnsBuilder $cb): ColumnsBuilder => $cb->primaryKey())
        );

        $this::assertEquals(1, $result->getAffectedRows());
        $this::assertEquals(2, $result[0]['id']);
    }

    public function testDeleteWithFragment(): void
    {
        $gateway = $this->createTableGateway('foo');
        $result  = $gateway->delete(new FragmentImplementation(
            new KeywordConstant(ConstantName::FALSE),
            'falsy'
        ));

        $this::assertEquals(0, $result->getAffectedRows());
    }

    public function testDeleteWithMultipleFragments(): void
    {
        $gateway = $this->createTableGateway('bar');
        $result  = $gateway->delete(
            [
                new SqlStringCondition(self::$tableLocator->getParser(), 'id = :id'),
                new SqlStringCondition(self::$tableLocator->getParser(), 'foo_id <> all(:foo_id::integer[])')
            ],
            [
                'id'     => 3,
                'foo_id' => [4, 5, 6]
            ]
        );

        $this::assertEquals(1, $result->getAffectedRows());
    }

    public function testDeleteWithParameterHolder(): void
    {
        $gateway = $this->createTableGateway('foo');
        $result  = $gateway->delete(new ParametrizedFragmentImplementation(
            new OperatorExpression(
                '=',
                new ColumnReference('id'),
                new NamedParameter('id')
            ),
            ['id' => 1],
            'holder'
        ));

        $this::assertEquals(1, $result->getAffectedRows());
    }
}
