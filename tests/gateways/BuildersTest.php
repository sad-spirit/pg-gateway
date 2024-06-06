<?php

/*
 * This file is part of sad_spirit/pg_gateway package
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/** @noinspection SqlResolve */

declare(strict_types=1);

namespace sad_spirit\pg_gateway\tests\gateways;

use sad_spirit\pg_gateway\{
    OrdinaryTableDefinition,
    TableLocator,
    conditions\ParametrizedCondition,
    exceptions\LogicException,
    exceptions\OutOfBoundsException,
    exceptions\UnexpectedValueException,
    fragments\LimitClauseFragment,
    fragments\OffsetClauseFragment,
    gateways\GenericTableGateway,
    metadata\TableName,
    tests\DatabaseBackedTest,
    tests\NormalizeWhitespace
};

/**
 * Tests for methods of GenericTableGateway creating Fragments / FragmentBuilders
 */
class BuildersTest extends DatabaseBackedTest
{
    use NormalizeWhitespace;

    protected static ?TableLocator $tableLocator;
    protected static ?GenericTableGateway $gateway;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::executeSqlFromFile(self::$connection, 'update-drop.sql', 'update-create.sql');
        self::$tableLocator = new TableLocator(self::$connection);
        self::$gateway      = new GenericTableGateway(
            new OrdinaryTableDefinition(self::$connection, new TableName('update_test')),
            self::$tableLocator
        );
    }

    public static function tearDownAfterClass(): void
    {
        self::executeSqlFromFile(self::$connection, 'update-drop.sql');
        self::$gateway      = null;
        self::$tableLocator = null;
        self::$connection   = null;
    }

    public function testAny(): void
    {
        $condition = self::$gateway->any('id', [1, 2]);

        $this::assertStringEqualsStringNormalizingWhitespace(
            'self.id = any(:id::int4[])',
            $condition->generateExpression()->dispatch(
                self::$tableLocator->getStatementFactory()->getBuilder()
            )
        );
        $this::assertEquals(
            ['id' => [1, 2]],
            $condition->getParameterHolder()->getParameters()
        );

        $this::expectException(OutOfBoundsException::class);
        $this::expectExceptionMessage('does not exist');
        self::$gateway->any('missing', ['foo', 'bar']);
    }

    public function testBoolColumn(): void
    {
        $condition = self::$gateway->column('flag');

        $this::assertStringEqualsStringNormalizingWhitespace(
            'self.flag',
            $condition->generateExpression()->dispatch(
                self::$tableLocator->getStatementFactory()->getBuilder()
            )
        );
        $this::assertNotInstanceOf(ParametrizedCondition::class, $condition);

        $this::expectException(OutOfBoundsException::class);
        $this::expectExceptionMessage('does not exist');
        self::$gateway->column('missing');
    }

    public function testNotBoolColumn(): void
    {
        $condition = self::$gateway->notColumn('flag');

        $this::assertStringEqualsStringNormalizingWhitespace(
            'not self.flag',
            $condition->generateExpression()->dispatch(
                self::$tableLocator->getStatementFactory()->getBuilder()
            )
        );
        $this::assertNotInstanceOf(ParametrizedCondition::class, $condition);
    }

    public function testColumnConditionRequiresBoolColumn(): void
    {
        $this::expectException(LogicException::class);
        $this::expectExceptionMessage("is not of type 'bool'");
        self::$gateway->column('id');
    }

    public function testIsNull(): void
    {
        $condition = self::$gateway->isNull('title');

        $this::assertStringEqualsStringNormalizingWhitespace(
            'self.title is null',
            $condition->generateExpression()->dispatch(
                self::$tableLocator->getStatementFactory()->getBuilder()
            )
        );
        $this::assertNotInstanceOf(ParametrizedCondition::class, $condition);

        $this::expectException(OutOfBoundsException::class);
        $this::expectExceptionMessage('does not exist');
        self::$gateway->column('missing');
    }

    public function testIsNotNull(): void
    {
        $condition = self::$gateway->isNotNull('title');

        $this::assertStringEqualsStringNormalizingWhitespace(
            'self.title is not null',
            $condition->generateExpression()->dispatch(
                self::$tableLocator->getStatementFactory()->getBuilder()
            )
        );
        $this::assertNotInstanceOf(ParametrizedCondition::class, $condition);
    }

    public function testNotAll(): void
    {
        $condition = self::$gateway->notAll('id', [3, 4]);

        $this::assertStringEqualsStringNormalizingWhitespace(
            'self.id <> all(:id::int4[])',
            $condition->generateExpression()->dispatch(
                self::$tableLocator->getStatementFactory()->getBuilder()
            )
        );
        $this::assertEquals(
            ['id' => [3, 4]],
            $condition->getParameterHolder()->getParameters()
        );

        $this::expectException(OutOfBoundsException::class);
        $this::expectExceptionMessage('does not exist');
        self::$gateway->notAll('missing', ['baz', 'quux']);
    }

    public function testOperator(): void
    {
        $condition = self::$gateway->operatorCondition('title', '~*', 'gateway');

        $this::assertStringEqualsStringNormalizingWhitespace(
            'self.title ~* :title::"text"',
            $condition->generateExpression()->dispatch(
                self::$tableLocator->getStatementFactory()->getBuilder()
            )
        );
        $this::assertEquals(['title' => 'gateway'], $condition->getParameterHolder()->getParameters());

        $this::expectException(OutOfBoundsException::class);
        $this::expectExceptionMessage('does not exist');
        self::$gateway->operatorCondition('missing', '!~*', 'gateway');
    }

    public function testEqual(): void
    {
        $condition = self::$gateway->equal('id', 5);

        $this::assertStringEqualsStringNormalizingWhitespace(
            'self.id = :id::int4',
            $condition->generateExpression()->dispatch(
                self::$tableLocator->getStatementFactory()->getBuilder()
            )
        );
        $this::assertEquals(['id' => 5], $condition->getParameterHolder()->getParameters());
    }

    public function testSqlCondition(): void
    {
        $condition = self::$gateway->sqlCondition(
            "added between :cutoff and current_date",
            ['cutoff' => '2023-08-07']
        );

        $this::assertStringEqualsStringNormalizingWhitespace(
            "added between :cutoff and current_date",
            $condition->generateExpression()->dispatch(
                self::$tableLocator->getStatementFactory()->getBuilder()
            )
        );
        $this::assertEquals(['cutoff' => '2023-08-07'], $condition->getParameterHolder()->getParameters());
    }

    public function testJoinUsingTableName(): void
    {
        $select = self::$gateway->select(
            self::$gateway->join('update_test')
                ->alias('custom')
        );

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select self.*, custom.* from public.update_test as self, public.update_test as custom',
            $select->createSelectStatement()->getSql()
        );
    }

    public function testJoinUsingGateway(): void
    {
        $select = self::$gateway->select(
            self::$gateway->join(self::$gateway)
                ->alias('custom')
        );

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select self.*, custom.* from public.update_test as self, public.update_test as custom',
            $select->createSelectStatement()->getSql()
        );
    }

    public function testJoinUsingSelectProxy(): void
    {
        $select = self::$gateway->select(
            self::$gateway->join(
                self::$gateway->select(self::$gateway->column('flag'))
            )
                ->alias('custom')
        );

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select self.*, custom.* from public.update_test as self, public.update_test as custom where custom.flag',
            $select->createSelectStatement()->getSql()
        );
    }

    public function testOutputSubquery(): void
    {
        /** @var GenericTableGateway $unconditional */
        $unconditional = self::$tableLocator->createGateway('unconditional');

        $select = self::$gateway->select(
            self::$gateway->outputSubquery(
                $unconditional->select($unconditional->outputColumns()->only(['id']))
            )
                ->alias('custom')
                ->columnAlias('klmn')
                ->joinOn(
                    self::$gateway->sqlCondition('self.title = joined.title')
                )
        );

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select self.*, ( select custom.id from public.unconditional as custom where self.title = custom.title )'
            . ' as klmn from public.update_test as self',
            $select->createSelectStatement()->getSql()
        );
    }

    public function testOutputExpressionUsingString(): void
    {
        $select = self::$gateway->select(
            self::$gateway->outputExpression('upper(self.title) as upper_title')
        );

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select self.*, upper(self.title) as upper_title from public.update_test as self',
            $select->createSelectStatement()->getSql()
        );
    }

    public function testOutputExpressionUsingCondition(): void
    {
        $select = self::$gateway->select(
            self::$gateway->outputExpression(self::$gateway->isNull('title'), 'null_title')
        );

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select self.*, self.title is null as null_title from public.update_test as self',
            $select->createSelectStatement()->getSql()
        );
    }

    public function testOrderBy(): void
    {
        $select = self::$gateway->select(
            self::$gateway->orderBy('added')
        );

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select self.* from public.update_test as self order by added',
            $select->createSelectStatement()->getSql()
        );

        $this::expectException(UnexpectedValueException::class);
        $this::expectExceptionMessage('column names or ordinal numbers');
        self::$gateway->select(self::$gateway->orderBy('upper(title)'))
            ->createSelectStatement();
    }

    public function testOrderByUnsafe(): void
    {
        $select = self::$gateway->select(
            self::$gateway->orderByUnsafe('upper(title)')
        );

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select self.* from public.update_test as self order by upper(title)',
            $select->createSelectStatement()->getSql()
        );
    }

    public function testLimit(): void
    {
        $this::assertEquals(
            new LimitClauseFragment(5),
            self::$gateway->limit(5)
        );
    }

    public function testOffset(): void
    {
        $this::assertEquals(
            new OffsetClauseFragment(5),
            self::$gateway->offset(5)
        );
    }
}
