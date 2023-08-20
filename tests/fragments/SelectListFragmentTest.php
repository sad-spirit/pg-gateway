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
 * @noinspection SqlWithoutWhere
 */

declare(strict_types=1);

namespace sad_spirit\pg_gateway\tests\fragments;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_gateway\tests\assets\TargetListManipulatorImplementation;
use sad_spirit\pg_gateway\tests\NormalizeWhitespace;
use sad_spirit\pg_gateway\{
    exceptions\InvalidArgumentException,
    fragments\SelectListFragment
};
use sad_spirit\pg_builder\{
    StatementFactory,
    nodes\ColumnReference,
    nodes\Identifier,
    nodes\TargetElement
};

/**
 * Test for a fragment adding stuff to output list of SELECT
 */
class SelectListFragmentTest extends TestCase
{
    use NormalizeWhitespace;

    private static ?StatementFactory $statementFactory = null;

    public static function setUpBeforeClass(): void
    {
        self::$statementFactory = new StatementFactory();
    }

    public function testAppliesToSelect(): void
    {
        $select   = self::$statementFactory->createFromString('select one, two, three from a_table');
        $fragment = new SelectListFragment(new TargetListManipulatorImplementation(new TargetElement(
            new ColumnReference('foo'),
            new Identifier('bar')
        )));

        $fragment->applyTo($select);
        $this::assertStringEqualsStringNormalizingWhitespace(
            'select foo as bar from a_table',
            self::$statementFactory->createFromAST($select)->getSql()
        );
    }

    /**
     * @dataProvider nonApplicableStatementsProvider
     */
    public function testDoesNotApplyToAnythingButSelect(string $sql): void
    {
        $fragment  = new SelectListFragment(new TargetListManipulatorImplementation(new TargetElement(
            new ColumnReference('foo'),
            new Identifier('bar')
        )));
        $statement = self::$statementFactory->createFromString($sql);

        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('SELECT statements');
        $fragment->applyTo($statement);
    }

    public function testNullKeyForManipulatorNullKey(): void
    {
        $fragment = new SelectListFragment(new TargetListManipulatorImplementation(
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
        $fragment = new SelectListFragment(new TargetListManipulatorImplementation(
            new TargetElement(
                new ColumnReference('foo'),
                new Identifier('bar')
            ),
            'some_key'
        ));

        $this::assertStringContainsString('some_key', $fragment->getKey());
        $this::assertNotEquals('some_key', $fragment->getKey());
    }

    public function nonApplicableStatementsProvider(): array
    {
        return [
            ['delete from a_table'],
            ['update a_table set foo = null'],
            ['insert into a_table default values']
        ];
    }
}
