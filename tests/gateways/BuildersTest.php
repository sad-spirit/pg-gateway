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

use sad_spirit\pg_gateway\{
    TableLocator,
    conditions\ParametrizedCondition,
    exceptions\LogicException,
    exceptions\OutOfBoundsException,
    gateways\GenericTableGateway,
    tests\DatabaseBackedTest,
    tests\NormalizeWhitespace
};
use sad_spirit\pg_builder\nodes\QualifiedName;

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
        self::$gateway      = new GenericTableGateway(new QualifiedName('update_test'), self::$tableLocator);
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
}
