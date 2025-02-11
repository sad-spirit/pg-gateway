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

namespace sad_spirit\pg_gateway\tests\fragments\target_list;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_gateway\fragments\target_list\ConditionAppender;
use sad_spirit\pg_gateway\tests\NormalizeWhitespace;
use sad_spirit\pg_gateway\tests\assets\ConditionImplementation;
use sad_spirit\pg_builder\{
    Select,
    StatementFactory,
    enums\ConstantName
};
use sad_spirit\pg_builder\nodes\expressions\KeywordConstant;

class ConditionAppenderTest extends TestCase
{
    use NormalizeWhitespace;

    public function testKeyIsNullIfConditionKeyIsNull(): void
    {
        $fragment = new ConditionAppender(
            new ConditionImplementation(new KeywordConstant(ConstantName::FALSE), null)
        );

        $this::assertNull($fragment->getKey());
    }

    public function testKeyDependsOnConditionKey(): void
    {
        $fragment = new ConditionAppender(
            new ConditionImplementation(new KeywordConstant(ConstantName::FALSE), 'some_key')
        );

        $this::assertNotNull($fragment->getKey());
        $this::assertStringContainsString('some_key', $fragment->getKey());
    }

    public function testKeyDependsOnAlias(): void
    {
        $fragmentOne = new ConditionAppender(
            new ConditionImplementation(new KeywordConstant(ConstantName::FALSE), 'some_key'),
            'alias_one'
        );
        $fragmentTwo = new ConditionAppender(
            new ConditionImplementation(new KeywordConstant(ConstantName::FALSE), 'some_key'),
            'alias_two'
        );
        $fragmentThree = new ConditionAppender(
            new ConditionImplementation(new KeywordConstant(ConstantName::FALSE), 'some_key'),
            'alias_two'
        );

        $this::assertNotEquals($fragmentOne->getKey(), $fragmentTwo->getKey());
        $this::assertEquals($fragmentTwo->getKey(), $fragmentThree->getKey());
    }

    public function testModifyTargetList(): void
    {
        $factory = new StatementFactory();

        /** @var Select $select */
        $select = $factory->createFromString('select self.foo as bar, quux.xyzzy');
        (new ConditionAppender(
            new ConditionImplementation(new KeywordConstant(ConstantName::NULL)),
            'baz'
        ))->applyTo($select);

        (new ConditionAppender(
            new ConditionImplementation(new KeywordConstant(ConstantName::TRUE)),
        ))->applyTo($select);

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select self.foo as bar, quux.xyzzy, null as baz, true',
            $factory->createFromAST($select)->getSql()
        );
    }
}
