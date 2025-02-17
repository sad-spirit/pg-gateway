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

declare(strict_types=1);

namespace sad_spirit\pg_gateway\tests\builders;

use sad_spirit\pg_gateway\{
    SqlStringSelectBuilder,
    TableLocator,
    builders\ExistsBuilder,
    conditions\ExistsCondition,
    conditions\NotCondition,
    exceptions\LogicException,
    fragments\WhereClauseFragment,
    tests\DatabaseBackedTestCase
};

class ExistsBuilderTest extends DatabaseBackedTestCase
{
    protected static ?TableLocator $tableLocator;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$tableLocator = new TableLocator(self::$connection);
        self::executeSqlFromFile(self::$connection, 'foreign-key-drop.sql', 'foreign-key-create.sql');
    }

    public static function tearDownAfterClass(): void
    {
        self::executeSqlFromFile(self::$connection, 'foreign-key-drop.sql');
        self::$tableLocator = null;
        self::$connection   = null;
    }

    public function testDefaultFragment(): void
    {
        $gateway = self::$tableLocator->createGateway('fkey_test.documents');
        $select  = $gateway->select();
        $builder = new ExistsBuilder($gateway->getDefinition(), $select);

        $this::assertEquals(new ExistsCondition($select), $builder->getCondition());
        $this::assertEquals(
            new WhereClauseFragment(new ExistsCondition($select)),
            $builder->getFragment()
        );
    }

    public function testNotExists(): void
    {
        $gateway = self::$tableLocator->createGateway('fkey_test.documents');
        $select  = $gateway->select();
        $builder = (new ExistsBuilder($gateway->getDefinition(), $select))
            ->not();

        $this::assertEquals(
            new NotCondition(new ExistsCondition($select)),
            $builder->getCondition()
        );
    }

    /** @noinspection SqlResolve */
    public function testNoForeignKeyForSqlString(): void
    {
        $builder = (new ExistsBuilder(
            self::$tableLocator->getTableDefinition('fkey_test.documents'),
            new SqlStringSelectBuilder(self::$tableLocator->getParser(), 'select 1 from some_table')
        ));

        $this::expectException(LogicException::class);
        $this::expectExceptionMessage('does not contain table metadata');
        $builder->joinOnForeignKey();
    }

    /** @noinspection SqlResolve */
    public function testParameters()
    {
        $builder = (new ExistsBuilder(
            self::$tableLocator->getTableDefinition('fkey_test.documents'),
            new SqlStringSelectBuilder(self::$tableLocator->getParser(), 'select 1 from some_table where :foo')
        ))
            ->parameters(['foo' => 'bar']);

        $this::assertEquals(
            ['foo' => 'bar'],
            $builder->getFragment()->getParameterHolder()->getParameters()
        );

        $this::expectException(LogicException::class);
        $this::expectExceptionMessage('already contains parameters');
        (new ExistsBuilder(
            self::$tableLocator->getTableDefinition('fkey_test.documents'),
            self::$tableLocator->select('fkey_test.documents')
        ))->parameters(['foo' => 'bar']);
    }
}
