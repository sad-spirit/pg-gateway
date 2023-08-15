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
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    QualifiedName,
    Star,
    expressions\KeywordConstant,
    expressions\NamedParameter,
    expressions\OperatorExpression
};
use sad_spirit\pg_gateway\{
    TableLocator,
    conditions\SqlStringCondition,
    gateways\GenericTableGateway
};
use sad_spirit\pg_gateway\tests\{
    DatabaseBackedTest,
    assets\FragmentImplementation,
    assets\ParametrizedFragmentImplementation
};

/**
 * Tests for delete() method
 */
class DeleteTest extends DatabaseBackedTest
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

    public function testDeleteWithoutFragments(): void
    {
        $gateway = new GenericTableGateway(new QualifiedName('victim'), self::$tableLocator);
        $result  = $gateway->delete();

        $this::assertEquals(4, $result->getAffectedRows());
    }

    public function testDeleteWithClosure(): void
    {
        $gateway = new GenericTableGateway(new QualifiedName('bar'), self::$tableLocator);
        $result  = $gateway->delete(function (Delete $delete) {
            $delete->where->and('id = 1');
            $delete->returning[] = new Star();
        });

        $this::assertEquals(1, $result->getAffectedRows());
        $this::assertEquals('some stuff', $result[0]['name']);
    }

    public function testDeleteWithClosureAndParameters(): void
    {
        $gateway = new GenericTableGateway(new QualifiedName('foo'), self::$tableLocator);
        $result  = $gateway->delete(
            function (Delete $delete) {
                $delete->where->and('id = :param');
                $delete->returning[] = new Star();
            },
            ['param' => 3]
        );

        $this::assertEquals(1, $result->getAffectedRows());
        $this::assertEquals('many', $result[0]['name']);
    }

    public function testDeleteWithFragment(): void
    {
        $gateway = new GenericTableGateway(new QualifiedName('foo'), self::$tableLocator);
        $result  = $gateway->delete(new FragmentImplementation(
            new KeywordConstant(KeywordConstant::FALSE),
            'falsy'
        ));

        $this::assertEquals(0, $result->getAffectedRows());
    }

    public function testDeleteWithMultipleFragments(): void
    {
        $gateway = new GenericTableGateway(new QualifiedName('bar'), self::$tableLocator);
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
        $gateway = new GenericTableGateway(new QualifiedName('foo'), self::$tableLocator);
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
