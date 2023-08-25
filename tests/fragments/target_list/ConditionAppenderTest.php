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
use sad_spirit\pg_builder\Select;
use sad_spirit\pg_builder\StatementFactory;
use sad_spirit\pg_builder\nodes\expressions\KeywordConstant;

class ConditionAppenderTest extends TestCase
{
    use NormalizeWhitespace;

    public function testKeyIsNullIfConditionKeyIsNull(): void
    {
        $manipulator = new ConditionAppender(
            new ConditionImplementation(new KeywordConstant(KeywordConstant::FALSE), null)
        );

        $this::assertNull($manipulator->getKey());
    }

    public function testKeyDependsOnConditionKey(): void
    {
        $manipulator = new ConditionAppender(
            new ConditionImplementation(new KeywordConstant(KeywordConstant::FALSE), 'some_key')
        );

        $this::assertNotNull($manipulator->getKey());
        $this::assertStringContainsString('some_key', $manipulator->getKey());
    }

    public function testKeyDependsOnAlias(): void
    {
        $manipulatorOne = new ConditionAppender(
            new ConditionImplementation(new KeywordConstant(KeywordConstant::FALSE), 'some_key'),
            'alias_one'
        );
        $manipulatorTwo = new ConditionAppender(
            new ConditionImplementation(new KeywordConstant(KeywordConstant::FALSE), 'some_key'),
            'alias_two'
        );
        $manipulatorThree = new ConditionAppender(
            new ConditionImplementation(new KeywordConstant(KeywordConstant::FALSE), 'some_key'),
            'alias_two'
        );

        $this::assertNotEquals($manipulatorOne->getKey(), $manipulatorTwo->getKey());
        $this::assertEquals($manipulatorTwo->getKey(), $manipulatorThree->getKey());
    }

    public function testModifyTargetList(): void
    {
        $factory = new StatementFactory();

        /** @var Select $select */
        $select = $factory->createFromString('select self.foo as bar, quux.xyzzy');
        (new ConditionAppender(
            new ConditionImplementation(new KeywordConstant(KeywordConstant::NULL)),
            'baz'
        ))->modifyTargetList($select->list);

        (new ConditionAppender(
            new ConditionImplementation(new KeywordConstant(KeywordConstant::TRUE)),
        ))->modifyTargetList($select->list);

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select self.foo as bar, quux.xyzzy, null as baz, true',
            $factory->createFromAST($select)->getSql()
        );
    }
}
