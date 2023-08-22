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

namespace sad_spirit\pg_gateway\tests\conditions;

use sad_spirit\pg_gateway\tests\{
    DatabaseBackedTest,
    NormalizeWhitespace,
    assets\ConditionImplementation
};
use sad_spirit\pg_gateway\{
    Condition,
    SelectProxy,
    TableLocator,
    conditions\ExistsCondition,
    conditions\ParametrizedCondition,
    conditions\SqlStringCondition,
    exceptions\UnexpectedValueException,
    holders\SimpleParameterHolder
};
use sad_spirit\pg_builder\nodes\expressions\KeywordConstant;

class ExistsConditionTest extends DatabaseBackedTest
{
    use NormalizeWhitespace;

    protected static ?TableLocator $tableLocator;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$tableLocator = new TableLocator(self::$connection);
    }

    public static function tearDownAfterClass(): void
    {
        self::$tableLocator = null;
        self::$connection   = null;
    }

    public function testKeyDependsOnConstructorArguments(): void
    {
        $mockSelect = $this->getMockBuilder(SelectProxy::class)
            ->onlyMethods(['getKey'])
            ->getMockForAbstractClass();
        $mockSelect->expects($this->any())
            ->method('getKey')
            ->willReturn('selectkey');

        $mockCondition = $this->getMockBuilder(Condition::class)
            ->onlyMethods(['getKey'])
            ->getMockForAbstractClass();
        $mockCondition->expects($this->any())
            ->method('getKey')
            ->willReturn('conditionkey');

        $condition = new ExistsCondition($mockSelect, $mockCondition);
        $this::assertStringContainsString('selectkey', $condition->getKey());
        $this::assertStringContainsString('conditionkey', $condition->getKey());
    }

    public function testKeyDependsOnExplicitAlias(): void
    {
        $mockSelect = $this->getMockBuilder(SelectProxy::class)
            ->onlyMethods(['getKey'])
            ->getMockForAbstractClass();
        $mockSelect->expects($this->any())
            ->method('getKey')
            ->willReturn('selectkey');

        $mockCondition = $this->getMockBuilder(Condition::class)
            ->onlyMethods(['getKey'])
            ->getMockForAbstractClass();
        $mockCondition->expects($this->any())
            ->method('getKey')
            ->willReturn('conditionkey');

        $noAlias      = new ExistsCondition($mockSelect, $mockCondition);
        $aliasOne     = new ExistsCondition($mockSelect, $mockCondition, 'one');
        $aliasOneMore = new ExistsCondition($mockSelect, $mockCondition, 'one');
        $aliasTwo     = new ExistsCondition($mockSelect, $mockCondition, 'two');

        $this::assertNotEquals($noAlias->getKey(), $aliasOne->getKey());
        $this::assertEquals($aliasOne->getKey(), $aliasOneMore->getKey());
        $this::assertNotEquals($aliasOne->getKey(), $aliasTwo->getKey());
    }

    public function testKeyIsNotNullForMissingCondition(): void
    {
        $mockSelect = $this->getMockBuilder(SelectProxy::class)
            ->onlyMethods(['getKey'])
            ->getMockForAbstractClass();
        $mockSelect->expects($this->any())
            ->method('getKey')
            ->willReturn('selectkey');

        $condition = new ExistsCondition($mockSelect);
        $this::assertNotNull($condition->getKey());
    }

    public function testKeyIsNullForNullSelectKey(): void
    {
        $mockSelect = $this->getMockBuilder(SelectProxy::class)
            ->onlyMethods(['getKey'])
            ->getMockForAbstractClass();
        $mockSelect->expects($this->any())
            ->method('getKey')
            ->willReturn(null);

        $condition = new ExistsCondition($mockSelect);
        $this::assertNull($condition->getKey());
    }

    public function testKeyIsNullForNullConditionKey(): void
    {
        $mockSelect = $this->getMockBuilder(SelectProxy::class)
            ->onlyMethods(['getKey'])
            ->getMockForAbstractClass();
        $mockSelect->expects($this->any())
            ->method('getKey')
            ->willReturn('selectkey');

        $mockCondition = $this->getMockBuilder(Condition::class)
            ->onlyMethods(['getKey'])
            ->getMockForAbstractClass();
        $mockCondition->expects($this->any())
            ->method('getKey')
            ->willReturn(null);

        $condition = new ExistsCondition($mockSelect, $mockCondition);
        $this::assertNull($condition->getKey());
    }

    public function testGetParameters(): void
    {
        $mockSelect = $this->getMockBuilder(SelectProxy::class)
            ->onlyMethods(['getParameterHolder'])
            ->getMockForAbstractClass();
        $mockSelect->expects($this->any())
            ->method('getParameterHolder')
            ->willReturn(new SimpleParameterHolder($mockSelect, ['foo' => 'bar']));

        $condition = new ConditionImplementation(new KeywordConstant(KeywordConstant::TRUE));

        $exists = new ExistsCondition($mockSelect, new ParametrizedCondition($condition, ['name' => 'value']));
        $this::assertEquals(
            ['foo' => 'bar', 'name' => 'value'],
            $exists->getParameterHolder()->getParameters()
        );
    }

    public function testReplaceTargetListOnSelect(): void
    {
        $mockSelect = $this->getMockBuilder(SelectProxy::class)
            ->onlyMethods(['createSelectAST'])
            ->getMockForAbstractClass();
        $mockSelect->expects($this->any())
            ->method('createSelectAST')
            ->willReturn(
                self::$tableLocator->getParser()
                    ->parseSelectStatement('select foo, bar from baz')
            );

        $condition = new ExistsCondition($mockSelect);
        $this::assertStringEqualsStringNormalizingWhitespace(
            'exists( select 1 from baz )',
            $condition->generateExpression()->dispatch(
                self::$tableLocator->getStatementFactory()->getBuilder()
            )
        );
    }

    public function testDoesNotReplaceTargetListOnSetOpSelect(): void
    {
        $mockSelect = $this->getMockBuilder(SelectProxy::class)
            ->onlyMethods(['createSelectAST'])
            ->getMockForAbstractClass();
        $mockSelect->expects($this->any())
            ->method('createSelectAST')
            ->willReturn(
                self::$tableLocator->getParser()
                    ->parseSelectStatement(
                        'select foo, bar from baz except all select foo, bar from quux'
                    )
            );

        $condition = new ExistsCondition($mockSelect);
        $this::assertStringEqualsStringNormalizingWhitespace(
            'exists( select foo, bar from baz except all select foo, bar from quux )',
            $condition->generateExpression()->dispatch(
                self::$tableLocator->getStatementFactory()->getBuilder()
            )
        );
    }

    public function testDisallowJoinConditionWithSetOpSelect(): void
    {
        $mockSelect = $this->getMockBuilder(SelectProxy::class)
            ->onlyMethods(['createSelectAST'])
            ->getMockForAbstractClass();
        $mockSelect->expects($this->any())
            ->method('createSelectAST')
            ->willReturn(
                self::$tableLocator->getParser()
                    ->parseSelectStatement(
                        'select foo, bar from baz except all select foo, bar from quux'
                    )
            );

        $joinCondition = new ConditionImplementation(new KeywordConstant(KeywordConstant::TRUE));

        $this::expectException(UnexpectedValueException::class);
        $this::expectExceptionMessage('WHERE clause');
        (new ExistsCondition($mockSelect, $joinCondition))->generateExpression();
    }

    public function testExplicitAlias(): void
    {
        $mockSelect = $this->getMockBuilder(SelectProxy::class)
            ->onlyMethods(['createSelectAST'])
            ->getMockForAbstractClass();
        $mockSelect->expects($this->any())
            ->method('createSelectAST')
            ->willReturn(
                self::$tableLocator->getParser()
                    ->parseSelectStatement(
                        'select foo, bar from baz as self'
                    )
            );

        $joinCondition = new SqlStringCondition(
            self::$tableLocator->getParser(),
            'self.quux = joined.foo'
        );

        $exists = new ExistsCondition($mockSelect, $joinCondition, 'custom');
        $this::assertStringEqualsStringNormalizingWhitespace(
            'exists( select 1 from baz as custom where self.quux = custom.foo )',
            $exists->generateExpression()->dispatch(
                self::$tableLocator->getStatementFactory()->getBuilder()
            )
        );
    }
}
