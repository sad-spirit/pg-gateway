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
use sad_spirit\pg_gateway\tests\NormalizeWhitespace;
use sad_spirit\pg_gateway\exceptions\InvalidArgumentException;
use sad_spirit\pg_gateway\fragments\target_list\{
    SelfColumnsList,
    SelfColumnsNone,
    SelfColumnsShorthand,
    alias_strategies\ClosureStrategy,
    alias_strategies\MapStrategy,
};
use sad_spirit\pg_builder\Select;
use sad_spirit\pg_builder\StatementFactory;

/**
 * Tests for classes that modify the list of columns returned by a statement
 */
class SelfColumnsTest extends TestCase
{
    use NormalizeWhitespace;

    private StatementFactory $statementFactory;
    private Select $select;

    protected function setUp(): void
    {
        $this->statementFactory = new StatementFactory();
        /** @noinspection PhpFieldAssignmentTypeMismatchInspection */
        $this->select           = $this->statementFactory->createFromString(
            'select self.one, two.self, three.self.col, self.four as fourth'
        );
    }

    public function testColumnsNone(): void
    {
        (new SelfColumnsNone())->modifyTargetList($this->select->list);

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select two.self, three.self.col',
            $this->statementFactory->createFromAST($this->select)->getSql()
        );
    }

    public function testColumnsShorthand(): void
    {
        (new SelfColumnsShorthand())->modifyTargetList($this->select->list);

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select two.self, three.self.col, self.*',
            $this->statementFactory->createFromAST($this->select)->getSql()
        );
    }

    public function testColumnsList(): void
    {
        $manipulator = new SelfColumnsList(
            ['id', 'contents'],
            new MapStrategy(['contents' => 'malcontents'])
        );
        $manipulator->modifyTargetList($this->select->list);

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select two.self, three.self.col, self.id, self.contents as malcontents',
            $this->statementFactory->createFromAST($this->select)->getSql()
        );
    }

    public function testDisallowEmptyColumnsList(): void
    {
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('$columns array should not be empty');
        new SelfColumnsList([]);
    }

    public function testKeyIsNotNullForMissingAliasStrategy(): void
    {
        $manipulator = new SelfColumnsList(['id']);

        $this::assertNotNull($manipulator->getKey());
    }

    public function testKeyIsNullForNullAliasStrategyKey(): void
    {
        $manipulator = new SelfColumnsList(
            ['id'],
            new ClosureStrategy(fn(string $column): string => 'foo_' . $column)
        );

        $this::assertNull($manipulator->getKey());
    }

    public function testSameKeyForSameColumns(): void
    {
        $manipulatorOne   = new SelfColumnsList(['id']);
        $manipulatorTwo   = new SelfColumnsList(['id']);
        $manipulatorThree = new SelfColumnsList(['name']);

        $this::assertNotNull($manipulatorOne->getKey());
        $this::assertEquals($manipulatorOne->getKey(), $manipulatorTwo->getKey());
        $this::assertNotEquals($manipulatorTwo->getKey(), $manipulatorThree->getKey());
    }

    public function testKeyDependsOnStrategy(): void
    {
        $manipulatorOne = new SelfColumnsList(['foo', 'bar'], $strategyOne = new MapStrategy(['foo' => 'bar']));
        $manipulatorTwo = new SelfColumnsList(['foo', 'bar'], new MapStrategy(['bar' => 'foo']));

        $this::assertNotEquals($manipulatorOne->getKey(), $manipulatorTwo->getKey());
        $this::assertStringContainsString($strategyOne->getKey(), $manipulatorOne->getKey());
    }
}
