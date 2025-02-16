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

/**
 * @noinspection SqlResolve
 */

declare(strict_types=1);

namespace sad_spirit\pg_gateway\tests\fragments;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use sad_spirit\pg_gateway\{
    Fragment,
    FragmentList,
    exceptions\InvalidArgumentException,
    exceptions\UnexpectedValueException,
    fragments\OrderByClauseFragment,
    tests\NormalizeWhitespace
};
use sad_spirit\pg_builder\{
    StatementFactory,
    nodes\ColumnReference,
    nodes\OrderByElement
};

/**
 * Test for Fragment updating the `ORDER BY` clause of a `SELECT` statement
 */
class OrderByClauseFragmentTest extends TestCase
{
    use NormalizeWhitespace;
    use NonSelectStatements;

    private static ?StatementFactory $statementFactory = null;

    public static function setUpBeforeClass(): void
    {
        self::$statementFactory = new StatementFactory();
    }

    public function testAppliesToSelect(): void
    {
        $select   = self::$statementFactory->createFromString('select self.* from a_table as self');
        $fragment = new OrderByClauseFragment(self::$statementFactory->getParser(), 'foo, 2');

        $fragment->applyTo($select);
        $this::assertStringEqualsStringNormalizingWhitespace(
            'select self.* from a_table as self order by foo, 2',
            self::$statementFactory->createFromAST($select)->getSql()
        );
    }

    #[DataProvider('nonApplicableStatementsProvider')]
    public function testDoesNotApplyToAnythingButSelect(string $sql): void
    {
        $fragment  = new OrderByClauseFragment(self::$statementFactory->getParser(), 'foo, bar');
        $statement = self::$statementFactory->createFromString($sql);

        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('SELECT statements');
        $fragment->applyTo($statement);
    }

    public function testKeyDependsOnSql(): void
    {
        $fragmentOne = new OrderByClauseFragment(self::$statementFactory->getParser(), 'foo');
        $fragmentTwo = new OrderByClauseFragment(
            self::$statementFactory->getParser(),
            [new OrderByElement(new ColumnReference('foo'))]
        );

        $this::assertNotNull($fragmentOne->getKey());
        $this::assertNotNull($fragmentTwo->getKey());
        $this::assertNotEquals($fragmentOne->getKey(), $fragmentTwo->getKey());
    }

    public function testKeyDependsOnRestrictedOption(): void
    {
        $fragmentOne = new OrderByClauseFragment(self::$statementFactory->getParser(), 'foo', false);
        $fragmentTwo = new OrderByClauseFragment(self::$statementFactory->getParser(), 'foo', true);

        $this::assertNotNull($fragmentOne->getKey());
        $this::assertNotEquals($fragmentOne->getKey(), $fragmentTwo->getKey());
    }

    public function testKeyDependsOnMergeOption(): void
    {
        $fragmentOne = new OrderByClauseFragment(self::$statementFactory->getParser(), 'foo', true, true);
        $fragmentTwo = new OrderByClauseFragment(self::$statementFactory->getParser(), 'foo', true, false);

        $this::assertNotNull($fragmentOne->getKey());
        $this::assertNotEquals($fragmentOne->getKey(), $fragmentTwo->getKey());
    }

    #[DataProvider('arbitraryExpressionsProvider')]
    public function testRestrictedOptionDisallowsArbitraryExpressions(string $sql): void
    {
        $statement = self::$statementFactory->createFromString('select self.* from a_table as self');
        $fragment  = new OrderByClauseFragment(self::$statementFactory->getParser(), $sql, true);

        $this::expectException(UnexpectedValueException::class);
        $this::expectExceptionMessage('column names or ordinal numbers');
        $fragment->applyTo($statement);
    }

    #[DataProvider('arbitraryExpressionsProvider')]
    public function testAllowArbitraryExpressionsWithoutRestricted(string $sql): void
    {
        $select    = 'select self.* from a_table as self';
        $statement = self::$statementFactory->createFromString($select);
        $fragment  = new OrderByClauseFragment(self::$statementFactory->getParser(), $sql, false);

        $fragment->applyTo($statement);
        $this::assertStringEqualsStringNormalizingWhitespace(
            'select self.* from a_table as self order by ' . $sql,
            self::$statementFactory->createFromAST($statement)->getSql()
        );
    }

    public function testReplace(): void
    {
        $statement   = self::$statementFactory->createFromString(
            'select self.* from a_table as self order by something'
        );
        $fragmentOne = (new OrderByClauseFragment(
            self::$statementFactory->getParser(),
            'foo',
            true,
            false,
            Fragment::PRIORITY_LOW
        ));
        $fragmentTwo = (new OrderByClauseFragment(
            self::$statementFactory->getParser(),
            'bar',
            true,
            false,
            Fragment::PRIORITY_HIGH
        ));

        (new FragmentList($fragmentOne, $fragmentTwo))
            ->applyTo($statement);

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select self.* from a_table as self order by foo',
            self::$statementFactory->createFromAST($statement)->getSql()
        );
    }

    public function testMerge(): void
    {
        $statement   = self::$statementFactory->createFromString(
            'select self.* from a_table as self order by something'
        );
        $fragmentOne = (new OrderByClauseFragment(
            self::$statementFactory->getParser(),
            'foo',
            true,
            true,
            Fragment::PRIORITY_LOW
        ));
        $fragmentTwo = (new OrderByClauseFragment(
            self::$statementFactory->getParser(),
            'bar',
            true,
            true,
            Fragment::PRIORITY_HIGH
        ));

        (new FragmentList($fragmentOne, $fragmentTwo))
            ->applyTo($statement);

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select self.* from a_table as self order by something, bar, foo',
            self::$statementFactory->createFromAST($statement)->getSql()
        );
    }

    public static function arbitraryExpressionsProvider(): array
    {
        return [
            ['foo + bar'],
            ['coalesce(foo, bar)'],
            ['case when foo then bar else baz end'],
            ['foo::bar']
        ];
    }
}
