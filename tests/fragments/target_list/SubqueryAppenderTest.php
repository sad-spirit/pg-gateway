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

namespace sad_spirit\pg_gateway\tests\fragments\target_list;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_gateway\tests\assets\ConditionImplementation;
use sad_spirit\pg_gateway\tests\NormalizeWhitespace;
use sad_spirit\pg_gateway\{
    Condition,
    SelectBuilder,
    SelectProxy,
    conditions\ParametrizedCondition,
    conditions\SqlStringCondition,
    exceptions\UnexpectedValueException,
    holders\SimpleParameterHolder
};
use sad_spirit\pg_gateway\fragments\target_list\SubqueryAppender;
use sad_spirit\pg_builder\Select;
use sad_spirit\pg_builder\StatementFactory;
use sad_spirit\pg_builder\enums\ConstantName;
use sad_spirit\pg_builder\nodes\expressions\KeywordConstant;

class SubqueryAppenderTest extends TestCase
{
    use NormalizeWhitespace;

    public function testKeyIsNullIfSelectKeyIsNull(): void
    {
        $fragment = new SubqueryAppender($this->getMockKeyedSelect(null));

        $this::assertNull($fragment->getKey());
    }

    public function testKeyDependsOnSelectKey(): void
    {
        $fragment = new SubqueryAppender($this->getMockKeyedSelect('selectKey'));

        $this::assertNotNull($fragment->getKey());
        $this::assertStringContainsString('selectKey', $fragment->getKey());
    }

    public function testKeyIsNullIfConditionKeyIsNull(): void
    {
        $mockSelect = $this->getMockKeyedSelect('selectKey');
        $mockCondition = $this->getMockKeyedCondition(null);

        $fragment = new SubqueryAppender($mockSelect, $mockCondition);
        $this::assertNull($fragment->getKey());
    }

    public function testKeyDependsOnConditionKey(): void
    {
        $mockSelect = $this->getMockKeyedSelect('selectKey');
        $mockCondition = $this->getMockKeyedCondition('conditionKey');

        $fragment = new SubqueryAppender($mockSelect, $mockCondition);
        $this::assertNotNull($fragment->getKey());
        $this::assertStringContainsString('conditionKey', $fragment->getKey());
    }


    public function testKeyDependsOnExplicitTableAlias(): void
    {
        $mockSelect = $this->getMockKeyedSelect('selectKey');

        $fragmentOne   = new SubqueryAppender($mockSelect, null, 'alias_one');
        $fragmentTwo   = new SubqueryAppender($mockSelect, null, 'alias_two');
        $fragmentThree = new SubqueryAppender($mockSelect, null, 'alias_two');

        $this::assertNotEquals($fragmentOne->getKey(), $fragmentTwo->getKey());
        $this::assertEquals($fragmentTwo->getKey(), $fragmentThree->getKey());
    }

    public function testKeyDependsOnColumnAlias(): void
    {
        $mockSelect = $this->getMockKeyedSelect('selectKey');

        $fragmentOne   = new SubqueryAppender($mockSelect, null, null, 'alias_one');
        $fragmentTwo   = new SubqueryAppender($mockSelect, null, null, 'alias_two');
        $fragmentThree = new SubqueryAppender($mockSelect, null, null, 'alias_two');

        $this::assertNotEquals($fragmentOne->getKey(), $fragmentTwo->getKey());
        $this::assertEquals($fragmentTwo->getKey(), $fragmentThree->getKey());
    }

    public function testModifyTargetList(): void
    {
        $factory = new StatementFactory();
        /** @var Select $select */
        $select  = $factory->createFromString('select self.foo as bar, quux.xyzzy');

        $mockSelect = $this->getMockBuilder(SelectProxy::class)
            ->onlyMethods(['createSelectAST'])
            ->getMockForAbstractClass();

        $mockSelect->expects($this->any())
            ->method('createSelectAST')
            ->willReturn($factory->createFromString('select 1'));

        (new SubqueryAppender($mockSelect))
            ->applyTo($select);

        $mockSelect = $this->getMockBuilder(SelectProxy::class)
            ->onlyMethods(['createSelectAST'])
            ->getMockForAbstractClass();
        $mockSelect->expects($this->any())
            ->method('createSelectAST')
            ->willReturn($factory->createFromString(
                'select foo from baz as self'
            ));

        $joinCondition = new SqlStringCondition($factory->getParser(), 'self.quux = joined.foo');

        (new SubqueryAppender($mockSelect, $joinCondition, 'custom', 'klmn'))
            ->applyTo($select);

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select self.foo as bar, quux.xyzzy, ( select 1 ),'
             . ' ( select foo from baz as custom where self.quux = custom.foo ) as klmn',
            $factory->createFromAST($select)->getSql()
        );
    }

    public function testDisallowJoinConditionWithSetOpSelect(): void
    {
        $factory = new StatementFactory();

        $mockSelect = $this->getMockBuilder(SelectProxy::class)
            ->onlyMethods(['createSelectAST'])
            ->getMockForAbstractClass();
        $mockSelect->expects($this->any())
            ->method('createSelectAST')
            ->willReturn($factory->createFromString(
                'select foo, bar from baz except all select foo, bar from quux'
            ));

        $joinCondition = new ConditionImplementation(new KeywordConstant(ConstantName::TRUE));

        /** @var Select $select */
        $select = $factory->createFromString('select 1');

        $this::expectException(UnexpectedValueException::class);
        $this::expectExceptionMessage('WHERE clause');
        (new SubqueryAppender($mockSelect, $joinCondition))
            ->applyTo($select);
    }

    public function testGetParameters(): void
    {
        $mockSelect = $this->getMockBuilder(SelectProxy::class)
            ->onlyMethods(['getParameterHolder'])
            ->getMockForAbstractClass();
        $mockSelect->expects($this->any())
            ->method('getParameterHolder')
            ->willReturn(new SimpleParameterHolder($mockSelect, ['foo' => 'bar']));

        $condition = new ConditionImplementation(new KeywordConstant(ConstantName::TRUE));

        $fragment = new SubqueryAppender($mockSelect, new ParametrizedCondition($condition, ['name' => 'value']));
        $this::assertEquals(
            ['foo' => 'bar', 'name' => 'value'],
            $fragment->getParameterHolder()->getParameters()
        );
    }

    private function getMockKeyedSelect(?string $key): SelectBuilder
    {
        $mockSelect = $this->getMockBuilder(SelectBuilder::class)
            ->onlyMethods(['getKey'])
            ->getMockForAbstractClass();

        $mockSelect->expects($this->any())
            ->method('getKey')
            ->willReturn($key);

        return $mockSelect;
    }

    private function getMockKeyedCondition(?string $key): Condition
    {
        $mockCondition = $this->getMockBuilder(Condition::class)
            ->onlyMethods(['getKey'])
            ->getMockForAbstractClass();
        $mockCondition->expects($this->any())
            ->method('getKey')
            ->willReturn($key);

        return $mockCondition;
    }
}
