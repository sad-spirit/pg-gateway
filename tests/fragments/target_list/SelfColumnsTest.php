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
        (new SelfColumnsNone())->applyTo($this->select);

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select two.self, three.self.col',
            $this->statementFactory->createFromAST($this->select)->getSql()
        );
    }

    public function testColumnsShorthand(): void
    {
        (new SelfColumnsShorthand())->applyTo($this->select);

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select two.self, three.self.col, self.*',
            $this->statementFactory->createFromAST($this->select)->getSql()
        );
    }

    public function testColumnsList(): void
    {
        (new SelfColumnsList(
            ['id', 'contents'],
            new MapStrategy(['contents' => 'malcontents'])
        ))
            ->applyTo($this->select);

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
        $fragment = new SelfColumnsList(['id']);

        $this::assertNotNull($fragment->getKey());
    }

    public function testKeyIsNullForNullAliasStrategyKey(): void
    {
        $fragment = new SelfColumnsList(
            ['id'],
            new ClosureStrategy(fn (string $column): string => 'foo_' . $column)
        );

        $this::assertNull($fragment->getKey());
    }

    public function testSameKeyForSameColumns(): void
    {
        $fragmentOne   = new SelfColumnsList(['id']);
        $fragmentTwo   = new SelfColumnsList(['id']);
        $fragmentThree = new SelfColumnsList(['name']);

        $this::assertNotNull($fragmentOne->getKey());
        $this::assertEquals($fragmentOne->getKey(), $fragmentTwo->getKey());
        $this::assertNotEquals($fragmentTwo->getKey(), $fragmentThree->getKey());
    }

    public function testKeyDependsOnStrategy(): void
    {
        $fragmentOne = new SelfColumnsList(['foo', 'bar'], $strategyOne = new MapStrategy(['foo' => 'bar']));
        $fragmentTwo = new SelfColumnsList(['foo', 'bar'], new MapStrategy(['bar' => 'foo']));

        $this::assertNotEquals($fragmentOne->getKey(), $fragmentTwo->getKey());
        $this::assertStringContainsString($strategyOne->getKey(), $fragmentOne->getKey());
    }
}
