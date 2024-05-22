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

namespace sad_spirit\pg_gateway\tests\builders;

use sad_spirit\pg_gateway\{
    Fragment,
    TableLocator,
    builders\JoinBuilder,
    conditions\ForeignKeyCondition,
    exceptions\InvalidArgumentException,
    exceptions\LogicException,
    fragments\JoinFragment,
    fragments\JoinStrategy,
    metadata\TableName,
    tests\DatabaseBackedTest
};
use sad_spirit\pg_gateway\fragments\join_strategies\{
    ExplicitJoinStrategy,
    InlineStrategy,
    LateralSubselectStrategy
};
use sad_spirit\pg_builder\nodes\range\JoinExpression;

class JoinBuilderTest extends DatabaseBackedTest
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
        $gateway = self::$tableLocator->get('fkey_test.documents');
        $select  = $gateway->select();
        $builder = new JoinBuilder($gateway, $select);

        $this::assertEquals(new JoinFragment($select), $builder->getFragment());
    }

    /**
     * @dataProvider strategiesProvider
     */
    public function testStrategies(string $method, ?JoinStrategy $strategy): void
    {
        $gateway   = self::$tableLocator->get('fkey_test.documents');
        $select  = $gateway->select();
        $builder = (new JoinBuilder($gateway, $select))
            ->$method();

        $this::assertEquals(
            new JoinFragment($select, null, $strategy),
            $builder->getFragment()
        );
    }

    public function testForeignKeyFromChildSide(): void
    {
        $base    = self::$tableLocator->get('fkey_test.documents_tags');
        $joined  = self::$tableLocator->get('fkey_test.documents')
            ->select();
        $builder = (new JoinBuilder($base, $joined))
            ->onForeignKey();

        $this::assertEquals(
            new JoinFragment(
                $joined,
                new ForeignKeyCondition(
                    $base->getReferences()->get(new TableName('fkey_test', 'documents')),
                    true
                )
            ),
            $builder->getFragment()
        );
    }

    public function testForeignKeyFromReferencedSite(): void
    {
        $base    = self::$tableLocator->get('fkey_test.documents');
        $joined  = self::$tableLocator->get('fkey_test.documents_tags')
            ->select();
        $builder = (new JoinBuilder($base, $joined))
            ->onForeignKey();

        $this::assertEquals(
            new JoinFragment(
                $joined,
                new ForeignKeyCondition(
                    $base->getReferences()->get(new TableName('fkey_test', 'documents_tags')),
                    false
                )
            ),
            $builder->getFragment()
        );
    }

    public function testMultipleForeignKeys(): void
    {
        $base    = self::$tableLocator->get('fkey_test.documents');
        $joined  = self::$tableLocator->get('public.employees')
            ->select();
        $builder = (new JoinBuilder($base, $joined))
            ->onForeignKey(['boss_id']);

        $this::assertEquals(
            new JoinFragment(
                $joined,
                new ForeignKeyCondition(
                    $base->getReferences()->get(new TableName('public', 'employees'), ['boss_id']),
                    true
                )
            ),
            $builder->getFragment()
        );

        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('Several matching foreign keys');
        $builder->onForeignKey();
    }

    public function testRecursiveForeignKey(): void
    {
        $base    = self::$tableLocator->get('fkey_test.documents');
        $joined  = $base->select();
        $builder = (new JoinBuilder($base, $joined))
            ->onRecursiveForeignKey(false);

        $this::assertEquals(
            new JoinFragment(
                $joined,
                new ForeignKeyCondition(
                    $base->getReferences()->get(new TableName('fkey_test', 'documents')),
                    false
                )
            ),
            $builder->getFragment()
        );

        $this::expectException(LogicException::class);
        $this::expectExceptionMessage('Cannot join on recursive foreign key');
        $builder->onForeignKey();
    }

    public function testPriority(): void
    {
        $gateway = self::$tableLocator->get('fkey_test.documents');
        $select  = $gateway->select();
        $builder = (new JoinBuilder($gateway, $select))->priority(Fragment::PRIORITY_LOWEST);

        $this::assertEquals(
            new JoinFragment($select, null, null, true, Fragment::PRIORITY_LOWEST),
            $builder->getFragment()
        );
    }

    public function testUseForCount(): void
    {
        $gateway = self::$tableLocator->get('fkey_test.documents');
        $select  = $gateway->select();
        $builder = (new JoinBuilder($gateway, $select))->useForCount(false);

        $this::assertEquals(
            new JoinFragment($select, null, null, false),
            $builder->getFragment()
        );
    }

    public function testAlias(): void
    {
        $gateway = self::$tableLocator->get('fkey_test.documents');
        $select  = $gateway->select();
        $builder = (new JoinBuilder($gateway, $select))->alias('foo');

        $this::assertEquals(
            new JoinFragment($select, null, null, true, Fragment::PRIORITY_DEFAULT, 'foo'),
            $builder->getFragment()
        );
    }

    public function strategiesProvider(): array
    {
        return [
            ['inline',        new InlineStrategy()],
            ['inner',         new ExplicitJoinStrategy(JoinExpression::INNER)],
            ['left',          new ExplicitJoinStrategy(JoinExpression::LEFT)],
            ['right',         new ExplicitJoinStrategy(JoinExpression::RIGHT)],
            ['full',          new ExplicitJoinStrategy(JoinExpression::FULL)],
            ['lateral',       new LateralSubselectStrategy(LateralSubselectStrategy::APPEND)],
            ['lateralInner',  new LateralSubselectStrategy(JoinExpression::INNER)],
            ['lateralLeft',   new LateralSubselectStrategy(JoinExpression::LEFT)],
            ['unconditional', null]
        ];
    }
}
