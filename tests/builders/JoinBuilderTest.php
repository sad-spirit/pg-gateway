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
    Fragment,
    TableLocator,
    builders\JoinBuilder,
    conditions\ForeignKeyCondition,
    exceptions\InvalidArgumentException,
    exceptions\LogicException,
    fragments\JoinFragment,
    fragments\JoinStrategy,
    metadata\TableName,
    tests\DatabaseBackedTestCase
};
use sad_spirit\pg_gateway\fragments\join_strategies\{
    ExplicitJoinStrategy,
    ExplicitJoinType,
    InlineStrategy,
    LateralSubselectJoinType,
    LateralSubselectStrategy
};
use PHPUnit\Framework\Attributes\DataProvider;

class JoinBuilderTest extends DatabaseBackedTestCase
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
        $builder = new JoinBuilder($gateway->getDefinition(), $select);

        $this::assertEquals(new JoinFragment($select), $builder->getFragment());
    }

    #[DataProvider('strategiesProvider')]
    public function testStrategies(string $method, ?JoinStrategy $strategy): void
    {
        $gateway   = self::$tableLocator->createGateway('fkey_test.documents');
        $select  = $gateway->select();
        $builder = (new JoinBuilder($gateway->getDefinition(), $select))
            ->$method();

        $this::assertEquals(
            new JoinFragment($select, null, $strategy),
            $builder->getFragment()
        );
    }

    public function testForeignKeyFromChildSide(): void
    {
        $base    = self::$tableLocator->createGateway('fkey_test.documents_tags');
        $joined  = self::$tableLocator->createGateway('fkey_test.documents')
            ->select();
        $builder = (new JoinBuilder($base->getDefinition(), $joined))
            ->onForeignKey();

        $this::assertEquals(
            new JoinFragment(
                $joined,
                new ForeignKeyCondition(
                    $base->getDefinition()
                        ->getReferences()
                        ->get(new TableName('fkey_test', 'documents')),
                    true
                )
            ),
            $builder->getFragment()
        );
    }

    public function testForeignKeyFromReferencedSite(): void
    {
        $base    = self::$tableLocator->createGateway('fkey_test.documents');
        $joined  = self::$tableLocator->createGateway('fkey_test.documents_tags')
            ->select();
        $builder = (new JoinBuilder($base->getDefinition(), $joined))
            ->onForeignKey();

        $this::assertEquals(
            new JoinFragment(
                $joined,
                new ForeignKeyCondition(
                    $base->getDefinition()
                        ->getReferences()
                        ->get(new TableName('fkey_test', 'documents_tags')),
                    false
                )
            ),
            $builder->getFragment()
        );
    }

    public function testMultipleForeignKeys(): void
    {
        $base    = self::$tableLocator->createGateway('fkey_test.documents');
        $joined  = self::$tableLocator->createGateway('public.employees')
            ->select();
        $builder = (new JoinBuilder($base->getDefinition(), $joined))
            ->onForeignKey(['boss_id']);

        $this::assertEquals(
            new JoinFragment(
                $joined,
                new ForeignKeyCondition(
                    $base->getDefinition()
                        ->getReferences()
                        ->get(new TableName('public', 'employees'), ['boss_id']),
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
        $base    = self::$tableLocator->createGateway('fkey_test.documents');
        $joined  = $base->select();
        $builder = (new JoinBuilder($base->getDefinition(), $joined))
            ->onRecursiveForeignKey(false);

        $this::assertEquals(
            new JoinFragment(
                $joined,
                new ForeignKeyCondition(
                    $base->getDefinition()
                        ->getReferences()
                        ->get(new TableName('fkey_test', 'documents')),
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
        $gateway = self::$tableLocator->createGateway('fkey_test.documents');
        $select  = $gateway->select();
        $builder = (new JoinBuilder($gateway->getDefinition(), $select))->priority(Fragment::PRIORITY_LOWEST);

        $this::assertEquals(
            new JoinFragment($select, null, null, true, Fragment::PRIORITY_LOWEST),
            $builder->getFragment()
        );
    }

    public function testUseForCount(): void
    {
        $gateway = self::$tableLocator->createGateway('fkey_test.documents');
        $select  = $gateway->select();
        $builder = (new JoinBuilder($gateway->getDefinition(), $select))->useForCount(false);

        $this::assertEquals(
            new JoinFragment($select, null, null, false),
            $builder->getFragment()
        );
    }

    public function testAlias(): void
    {
        $gateway = self::$tableLocator->createGateway('fkey_test.documents');
        $select  = $gateway->select();
        $builder = (new JoinBuilder($gateway->getDefinition(), $select))->alias('foo');

        $this::assertEquals(
            new JoinFragment($select, null, null, true, Fragment::PRIORITY_DEFAULT, 'foo'),
            $builder->getFragment()
        );
    }

    public static function strategiesProvider(): array
    {
        return [
            ['inline',        new InlineStrategy()],
            ['inner',         new ExplicitJoinStrategy(ExplicitJoinType::Inner)],
            ['left',          new ExplicitJoinStrategy(ExplicitJoinType::Left)],
            ['right',         new ExplicitJoinStrategy(ExplicitJoinType::Right)],
            ['full',          new ExplicitJoinStrategy(ExplicitJoinType::Full)],
            ['lateral',       new LateralSubselectStrategy(LateralSubselectJoinType::Append)],
            ['lateralInner',  new LateralSubselectStrategy(LateralSubselectJoinType::Inner)],
            ['lateralLeft',   new LateralSubselectStrategy(LateralSubselectJoinType::Left)],
            ['unconditional', null]
        ];
    }
}
