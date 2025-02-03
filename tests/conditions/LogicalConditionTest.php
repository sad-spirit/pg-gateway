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

namespace sad_spirit\pg_gateway\tests\conditions;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_builder\enums\ConstantName;
use sad_spirit\pg_builder\nodes\expressions\KeywordConstant;
use sad_spirit\pg_gateway\{
    conditions\LogicalCondition,
    conditions\ParametrizedCondition,
    exceptions\InvalidArgumentException,
    holders\EmptyParameterHolder
};
use sad_spirit\pg_gateway\tests\{
    NormalizeWhitespace,
    assets\ConditionImplementation
};

abstract class LogicalConditionTest extends TestCase
{
    use NormalizeWhitespace;

    /**
     * @return class-string<LogicalCondition>
     */
    abstract protected function getTestedClassName(): string;

    public function testAtLeastOneChildConditionIsRequired(): void
    {
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('at least one child');

        $className = $this->getTestedClassName();
        new $className();
    }

    public function testKeyIsNullIfAnyChildKeyIsNull(): void
    {
        $nullKey   = new ConditionImplementation(new KeywordConstant(ConstantName::NULL), null);
        $stringKey = new ConditionImplementation(new KeywordConstant(ConstantName::TRUE), 'key');

        $className = $this->getTestedClassName();
        $condition = new $className($nullKey, $stringKey);

        $this::assertNull($condition->getKey());
    }

    public function testChildConditionsOrderIsIrrelevantForKey(): void
    {
        $one = new ConditionImplementation(new KeywordConstant(ConstantName::TRUE), 'first');
        $two = new ConditionImplementation(new KeywordConstant(ConstantName::FALSE), 'second');

        $className     = $this->getTestedClassName();
        $conditionAsc  = new $className($one, $two);
        $conditionDesc = new $className($two, $one);

        $this::assertNotNull($conditionAsc->getKey());
        $this::assertSame($conditionAsc->getKey(), $conditionDesc->getKey());
    }

    public function testGetParameters(): void
    {
        $one = new ConditionImplementation(new KeywordConstant(ConstantName::TRUE), 'first');
        $two = new ConditionImplementation(new KeywordConstant(ConstantName::FALSE), 'second');

        $className = $this->getTestedClassName();
        $conditionNoParameters = new $className($one, $two);
        $this::assertInstanceOf(
            EmptyParameterHolder::class,
            $conditionNoParameters->getParameterHolder()
        );

        $conditionParameters = new $className(
            new ParametrizedCondition($one, ['foo' => 'bar']),
            new ParametrizedCondition($two, ['baz' => 'quux'])
        );
        $this::assertEquals(
            ['foo' => 'bar', 'baz' => 'quux'],
            $conditionParameters->getParameterHolder()->getParameters()
        );
    }
}
