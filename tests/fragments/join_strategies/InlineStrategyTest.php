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
 * @noinspection SqlCheckUsingColumns
 */

declare(strict_types=1);

namespace sad_spirit\pg_gateway\tests\fragments\join_strategies;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use sad_spirit\pg_gateway\exceptions\UnexpectedValueException;
use sad_spirit\pg_gateway\fragments\join_strategies\InlineStrategy;
use sad_spirit\pg_gateway\tests\NormalizeWhitespace;
use sad_spirit\pg_builder\{
    Delete,
    Select,
    StatementFactory,
    Update
};
use sad_spirit\pg_builder\nodes\expressions\NumericConstant;

class InlineStrategyTest extends TestCase
{
    use NormalizeWhitespace;

    private StatementFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new StatementFactory();
    }

    public function testJoinToDelete(): void
    {
        /** @var Delete $delete */
        $delete = $this->factory->createFromString("delete from foo as self where self.id > 2");
        /** @var Select $select */
        $select = $this->factory->createFromString(
            "with baz as (select 1) select gw_1.* from bar as gw_1 where gw_1.id < 3 order by gw_1.name"
        );
        (new InlineStrategy())->join(
            $delete,
            $select,
            $this->factory->getParser()->parseExpression('self.id = joined.id'),
            'gw_1',
            false
        );
        $this::assertStringEqualsStringNormalizingWhitespace(
            'with baz as ( select 1 ) delete from foo as self using bar as gw_1 '
            . 'where self.id > 2 and gw_1.id < 3 and self.id = gw_1.id',
            $this->factory->createFromAST($delete)->getSql()
        );
    }

    public function testJoinToUpdate(): void
    {
        /** @var Update $update */
        $update = $this->factory->createFromString(
            "with baz as (select 1) update foo as self set title = 'new title' where self.id > 2"
        );
        /** @var Select $select */
        $select = $this->factory->createFromString(
            "select gw_1.* from bar as gw_1 where gw_1.id > self.id"
        );
        (new InlineStrategy())->join(
            $update,
            $select,
            null,
            'gw_1',
            false
        );
        $this::assertStringEqualsStringNormalizingWhitespace(
            "with baz as ( select 1 ) update foo as self set title = 'new title' "
            . "from bar as gw_1 where self.id > 2 and gw_1.id > self.id",
            $this->factory->createFromAST($update)->getSql()
        );
    }

    public function testJoinToSelect(): void
    {
        /** @var Select $base */
        $base = $this->factory->createFromString(
            "select self.*, gw_2.* from foo as self, baz as gw_2 where self.id <= gw_2.id order by self.title limit 10"
        );
        /** @var Select $joined */
        $joined = $this->factory->createFromString(
            "select gw_1.* from bar as gw_1 where gw_1.title ~* 'something' order by gw_1.title"
        );
        (new InlineStrategy())->join(
            $base,
            $joined,
            $this->factory->getParser()->parseExpression('self.id >= joined.id'),
            'gw_1',
            false
        );
        $this::assertStringEqualsStringNormalizingWhitespace(
            "select self.*, gw_2.*, gw_1.* from foo as self, baz as gw_2, bar as gw_1 "
            . "where  self.id <= gw_2.id and gw_1.title ~* 'something' and self.id >= gw_1.id "
            . "order by self.title, gw_1.title limit 10",
            $this->factory->createFromAST($base)->getSql()
        );
    }

    public function testJoinToSelectCount(): void
    {
        /** @var Select $base */
        $base = $this->factory->createFromString(
            "select count(self.*) from foo as self, baz as gw_2 where self.id <= gw_2.id"
        );
        /** @var Select $joined */
        $joined = $this->factory->createFromString(
            "select gw_1.* from bar as gw_1 where gw_1.title ~* 'something' order by gw_1.title"
        );
        (new InlineStrategy())->join(
            $base,
            $joined,
            $this->factory->getParser()->parseExpression('self.id >= joined.id'),
            'gw_1',
            true
        );
        $this::assertStringEqualsStringNormalizingWhitespace(
            "select count(self.*) from foo as self, baz as gw_2, bar as gw_1 "
            . "where self.id <= gw_2.id and gw_1.title ~* 'something' and self.id >= gw_1.id",
            $this->factory->createFromAST($base)->getSql()
        );
    }

    #[DataProvider('addClauseProvider')]
    public function testCannotInline(\Closure $addClause): void
    {
        $base   = $this->factory->select('self.*', 'foo as self');
        $joined = $this->factory->select('gw_1.*', 'bar as gw_1');
        $addClause($joined);

        $this::expectException(UnexpectedValueException::class);
        $this::expectExceptionMessage('cannot inline');
        (new InlineStrategy())->join(
            $base,
            $joined,
            null,
            'gw_1',
            false
        );
    }

    public static function addClauseProvider(): array
    {
        return [
            [function (Select $select) {
                $select->limit = new NumericConstant('10');
            }],
            [function (Select $select) {
                $select->offset = new NumericConstant('20');
            }],
            [function (Select $select) {
                $select->locking[] = 'for update';
            }],
            [function (Select $select) {
                $select->distinct = true;
            }],
            [function (Select $select) {
                $select->group[] = 'self.title';
            }],
            [function (Select $select) {
                $select->having->and('count(self.field) > 1');
            }],
            [function (Select $select) {
                $select->window[] = 'win95 as (partition by self.field)';
            }]
        ];
    }
}
