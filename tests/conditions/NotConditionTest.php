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
use sad_spirit\pg_gateway\{
    conditions\NotCondition,
    conditions\ParametrizedCondition,
    fragments\WhereClauseFragment,
    holders\EmptyParameterHolder
};
use sad_spirit\pg_gateway\tests\{
    NormalizeWhitespace,
    assets\ConditionImplementation
};
use sad_spirit\pg_builder\{
    Delete,
    StatementFactory,
    enums\ConstantName,
    enums\IsPredicate
};
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    expressions\IsExpression,
    expressions\KeywordConstant,
    expressions\NotExpression
};

class NotConditionTest extends TestCase
{
    use NormalizeWhitespace;

    private StatementFactory $factory;

    private Delete $delete;

    protected function setUp(): void
    {
        $this->factory = new StatementFactory();
        $this->delete  = $this->factory->delete('foo');
    }

    public function testDoubleNegation(): void
    {
        $notTrue   = new ConditionImplementation(new NotExpression(new KeywordConstant(ConstantName::TRUE)));
        $notNotOne = new NotCondition($notTrue);
        $notNotTwo = new NotCondition($notTrue);

        $fragmentNotOne = new WhereClauseFragment($notNotOne);
        $fragmentNotTwo = new WhereClauseFragment($notNotTwo);

        $fragmentNotOne->applyTo($this->delete);
        $fragmentNotOne->applyTo($this->delete);
        $fragmentNotTwo->applyTo($this->delete);

        $builder = $this->factory->getBuilder();
        $this::assertEquals(
            'true and true and true',
            self::normalizeWhitespace($this->delete->where->condition->dispatch($builder))
        );
    }

    public function testNegatableExpression(): void
    {
        $isNotNull = new NotCondition(new ConditionImplementation(
            new IsExpression(new ColumnReference('foo'), IsPredicate::NULL)
        ));
        $isNull = new NotCondition($isNotNull);

        $this::assertEquals(
            new IsExpression(new ColumnReference('foo'), IsPredicate::NULL, true),
            $isNotNull->generateExpression()
        );
        $this::assertEquals(
            new IsExpression(new ColumnReference('foo'), IsPredicate::NULL, false),
            $isNull->generateExpression()
        );
    }

    public function testGetKey(): void
    {
        $nullKey   = new ConditionImplementation(new KeywordConstant(ConstantName::TRUE), null);
        $stringKey = new ConditionImplementation(new KeywordConstant(ConstantName::TRUE), 'key');

        $nullKeyNot   = new NotCondition($nullKey);
        $stringKeyNot = new NotCondition($stringKey);

        $this::assertNull($nullKeyNot->getKey());
        $this::assertNotNull($stringKeyNot->getKey());
        $this::assertNotEquals($stringKey->getKey(), $stringKeyNot->getKey());
    }

    public function testGetParameters(): void
    {
        $child = new ConditionImplementation(new KeywordConstant(ConstantName::TRUE));

        $notChild = new NotCondition($child);
        $this::assertInstanceOf(
            EmptyParameterHolder::class,
            $notChild->getParameterHolder()
        );

        $notChildParametrized = new NotCondition(new ParametrizedCondition($child, ['foo' => 'bar']));
        $this::assertEquals(['foo' => 'bar'], $notChildParametrized->getParameterHolder()->getParameters());
    }
}
