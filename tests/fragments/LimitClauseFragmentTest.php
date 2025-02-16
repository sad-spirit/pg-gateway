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
    exceptions\InvalidArgumentException,
    fragments\LimitClauseFragment,
    holders\EmptyParameterHolder,
    tests\NormalizeWhitespace
};
use sad_spirit\pg_builder\StatementFactory;

/**
 * Test for a Fragment adding `LIMIT` clause to `SELECT`
 */
class LimitClauseFragmentTest extends TestCase
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
        $fragment = new LimitClauseFragment();

        $fragment->applyTo($select);
        $this::assertStringEqualsStringNormalizingWhitespace(
            'select self.* from a_table as self limit $1',
            self::$statementFactory->createFromAST($select)->getSql()
        );
    }

    #[DataProvider('nonApplicableStatementsProvider')]
    public function testDoesNotApplyToAnythingButSelect(string $sql): void
    {
        $fragment  = new LimitClauseFragment();
        $statement = self::$statementFactory->createFromString($sql);

        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('SELECT statements');
        $fragment->applyTo($statement);
    }

    public function testGetParameterHolder(): void
    {
        $limitNoParam = new LimitClauseFragment();
        $limitFive    = new LimitClauseFragment(5);

        $this::assertInstanceOf(EmptyParameterHolder::class, $limitNoParam->getParameterHolder());
        $this::assertEquals(['limit' => 5], $limitFive->getParameterHolder()->getParameters());
    }

    public function testKeyDoesNotDependOnParameter(): void
    {
        $limitNoParam = new LimitClauseFragment();
        $limitOne     = new LimitClauseFragment(1);
        $limitFive    = new LimitClauseFragment(5);

        $this::assertNotNull($limitNoParam->getKey());
        $this::assertEquals($limitNoParam->getKey(), $limitOne->getKey());
        $this::assertEquals($limitOne->getKey(), $limitFive->getKey());
    }
}
