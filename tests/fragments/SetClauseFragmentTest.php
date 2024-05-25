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

use sad_spirit\pg_gateway\{
    Expression,
    TableLocator,
    exceptions\InvalidArgumentException,
    exceptions\OutOfBoundsException,
    fragments\SetClauseFragment,
    metadata\Columns,
    metadata\TableColumns,
    metadata\TableName,
    tests\DatabaseBackedTest,
    tests\NormalizeWhitespace
};
use sad_spirit\pg_builder\nodes\{
    QualifiedName,
    SetToDefault,
    expressions\StringConstant
};

/**
 * Test for fragment populating SET clauses for UPDATE statements
 */
class SetClauseFragmentTest extends DatabaseBackedTest
{
    use NormalizeWhitespace;

    private static ?Columns $columns = null;
    private static ?TableLocator $tableLocator = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::executeSqlFromFile(self::$connection, 'update-drop.sql', 'update-create.sql');

        self::$columns      = new TableColumns(self::$connection, new TableName('update_test'));
        self::$tableLocator = new TableLocator(self::$connection);
    }

    public static function tearDownAfterClass(): void
    {
        self::executeSqlFromFile(self::$connection, 'update-drop.sql');
        self::$connection   = null;
        self::$tableLocator = null;
        self::$columns      = null;
    }

    public function testDisallowsEmptySet(): void
    {
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('At least one column');

        new SetClauseFragment(self::$columns, self::$tableLocator, []);
    }

    public function testDisallowsUnknownColumns(): void
    {
        $this::expectException(OutOfBoundsException::class);
        $this::expectExceptionMessage('actual table columns');
        new SetClauseFragment(self::$columns, self::$tableLocator, ['foo' => 'foo value', 'bar' => 'bar value']);
    }

    public function testReturnsLiteralsAsParameters(): void
    {
        $fragment = new SetClauseFragment(
            self::$columns,
            self::$tableLocator,
            ['id' => 666, 'title' => new SetToDefault()]
        );
        $this::assertEquals(['id' => 666], $fragment->getParameterHolder()->getParameters());
    }

    public function testKeyDoesNotDependOnParameters(): void
    {
        $fragmentOne = new SetClauseFragment(self::$columns, self::$tableLocator, ['title' => 'some title']);
        $fragmentTwo = new SetClauseFragment(self::$columns, self::$tableLocator, ['title' => 'another title']);

        $this::assertNotNull($fragmentOne->getKey());
        $this::assertEquals($fragmentOne->getKey(), $fragmentTwo->getKey());
    }

    public function testKeyDependsOnColumns(): void
    {
        $fragmentOne = new SetClauseFragment(self::$columns, self::$tableLocator, ['id' => 666]);
        $fragmentTwo = new SetClauseFragment(self::$columns, self::$tableLocator, ['title' => 'another title']);

        $this::assertNotEquals($fragmentOne->getKey(), $fragmentTwo->getKey());
    }

    public function testKeyDependsOnExpressions(): void
    {
        $fragmentOne = new SetClauseFragment(self::$columns, self::$tableLocator, ['added' => new SetToDefault()]);
        $fragmentTwo = new SetClauseFragment(
            self::$columns,
            self::$tableLocator,
            ['added' => new Expression("current_timestamp + '1 month'::interval")]
        );

        $this::assertNotNull($fragmentOne->getKey());
        $this::assertNotEquals($fragmentOne->getKey(), $fragmentTwo->getKey());
    }

    public function testApplyToInsert(): void
    {
        $fragment  = new SetClauseFragment(
            self::$columns,
            self::$tableLocator,
            [
                'id'    => 666,
                'title' => new SetToDefault(),
                'added' => new Expression("current_timestamp + '1 month'::interval")
            ]
        );
        $statement = self::$tableLocator->getStatementFactory()->insert(new QualifiedName('update_test'));
        $fragment->applyTo($statement);

        $this::assertStringEqualsStringNormalizingWhitespace(
            <<<SQL
            insert into update_test (id, title, added)
            values (:id::int4, default, current_timestamp + '1 month'::interval)
            SQL,
            $statement->dispatch(self::$tableLocator->getStatementFactory()->getBuilder())
        );
    }

    public function testApplyToUpdate(): void
    {
        $fragment  = new SetClauseFragment(
            self::$columns,
            self::$tableLocator,
            [
                'id'    => 666,
                'title' => new StringConstant('A new title'),
                'added' => new Expression("now()")
            ]
        );
        $statement = self::$tableLocator->getStatementFactory()
            ->update('update_test', []);
        $fragment->applyTo($statement);

        $this::assertStringEqualsStringNormalizingWhitespace(
            <<<SQL
            update update_test
            set id = :id::int4, title = 'A new title', added = now()
            SQL,
            $statement->dispatch(self::$tableLocator->getStatementFactory()->getBuilder())
        );
    }

    /**
     * @dataProvider nonApplicableStatementsProvider
     */
    public function testDoesNotApplyToOtherStatements(string $sql): void
    {
        $fragment  = new SetClauseFragment(self::$columns, self::$tableLocator, ['title' => 'some title']);
        $statement = self::$tableLocator->createFromString($sql);

        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('INSERT or UPDATE');
        $fragment->applyTo($statement);
    }

    public function nonApplicableStatementsProvider(): array
    {
        return [
            ['select * from update_test'],
            ['delete from update_test where false']
        ];
    }
}
