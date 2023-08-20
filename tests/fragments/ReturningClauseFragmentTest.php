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
 * @noinspection SqlWithoutWhere
 * @noinspection SqlResolve
 */

declare(strict_types=1);

namespace sad_spirit\pg_gateway\tests\fragments;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_gateway\tests\assets\TargetListManipulatorImplementation;
use sad_spirit\pg_gateway\tests\NormalizeWhitespace;
use sad_spirit\pg_gateway\{
    exceptions\InvalidArgumentException,
    fragments\ReturningClauseFragment
};
use sad_spirit\pg_builder\{
    StatementFactory,
    nodes\ColumnReference,
    nodes\Identifier,
    nodes\TargetElement
};

/**
 * Test for a fragment adding stuff to RETURNING clause
 */
class ReturningClauseFragmentTest extends TestCase
{
    use NormalizeWhitespace;

    private static ?StatementFactory $statementFactory = null;

    public static function setUpBeforeClass(): void
    {
        self::$statementFactory = new StatementFactory();
    }

    /**
     * @dataProvider applicableStatementsProvider
     */
    public function testAppliesToDmlStatements(string $sql): void
    {
        $fragment  = new ReturningClauseFragment(new TargetListManipulatorImplementation(new TargetElement(
            new ColumnReference('foo'),
            new Identifier('bar')
        )));
        $statement = self::$statementFactory->createFromString($sql);
        $fragment->applyTo($statement);

        $this::assertStringContainsString(
            'returning foo as bar',
            self::$statementFactory->createFromAST($statement)->getSql()
        );
    }

    public function testDoesNotApplyToSelect(): void
    {
        $fragment  = new ReturningClauseFragment(new TargetListManipulatorImplementation(new TargetElement(
            new ColumnReference('foo'),
            new Identifier('bar')
        )));
        $statement = self::$statementFactory->createFromString('select one, two, three from a_table');

        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('a RETURNING clause');
        $fragment->applyTo($statement);
    }

    public function testNullKeyForManipulatorNullKey(): void
    {
        $fragment  = new ReturningClauseFragment(new TargetListManipulatorImplementation(
            new TargetElement(
                new ColumnReference('foo'),
                new Identifier('bar')
            ),
            null
        ));

        $this::assertNull($fragment->getKey());
    }

    public function testGetKey(): void
    {
        $fragment  = new ReturningClauseFragment(new TargetListManipulatorImplementation(
            new TargetElement(
                new ColumnReference('foo'),
                new Identifier('bar')
            ),
            'some_key'
        ));

        $this::assertStringContainsString('some_key', $fragment->getKey());
        $this::assertNotEquals('some_key', $fragment->getKey());
    }

    public function applicableStatementsProvider(): array
    {
        return [
            ['delete from a_table'],
            ['update a_table set foo = null'],
            ['insert into a_table default values']
        ];
    }
}
