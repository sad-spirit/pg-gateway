<?php

/*
 * This file is part of sad_spirit/pg_gateway package
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

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_gateway\{
    exceptions\InvalidArgumentException,
    fragments\OffsetClauseFragment,
    holders\EmptyParameterHolder,
    tests\NormalizeWhitespace
};
use sad_spirit\pg_builder\StatementFactory;

/**
 * Test for a Fragment adding `OFFSET` clause to `SELECT`
 */
class OffsetClauseFragmentTest extends TestCase
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
        $fragment = new OffsetClauseFragment();

        $fragment->applyTo($select);
        $this::assertStringEqualsStringNormalizingWhitespace(
            'select self.* from a_table as self offset $1',
            self::$statementFactory->createFromAST($select)->getSql()
        );
    }

    /**
     * @dataProvider nonApplicableStatementsProvider
     */
    public function testDoesNotApplyToAnythingButSelect(string $sql): void
    {
        $fragment  = new OffsetClauseFragment();
        $statement = self::$statementFactory->createFromString($sql);

        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('SELECT statements');
        $fragment->applyTo($statement);
    }

    public function testGetParameterHolder(): void
    {
        $offsetNoParam = new OffsetClauseFragment();
        $offsetFive    = new OffsetClauseFragment(5);

        $this::assertInstanceOf(EmptyParameterHolder::class, $offsetNoParam->getParameterHolder());
        $this::assertEquals(['offset' => 5], $offsetFive->getParameterHolder()->getParameters());
    }

    public function testKeyDoesNotDependOnParameter(): void
    {
        $offsetNoParam = new OffsetClauseFragment();
        $offsetOne     = new OffsetClauseFragment(1);
        $offsetFive    = new OffsetClauseFragment(5);

        $this::assertNotNull($offsetNoParam->getKey());
        $this::assertEquals($offsetNoParam->getKey(), $offsetOne->getKey());
        $this::assertEquals($offsetOne->getKey(), $offsetFive->getKey());
    }
}
