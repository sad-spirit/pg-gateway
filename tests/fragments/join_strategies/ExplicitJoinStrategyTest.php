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
 * @noinspection SqlWithoutWhere
 * @noinspection SqlResolve
 * @noinspection SqlCheckUsingColumns
 */

declare(strict_types=1);

namespace sad_spirit\pg_gateway\tests\fragments\join_strategies;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_gateway\exceptions\LogicException;
use sad_spirit\pg_gateway\tests\NormalizeWhitespace;
use sad_spirit\pg_gateway\fragments\join_strategies\ExplicitJoinType;
use sad_spirit\pg_gateway\fragments\join_strategies\ExplicitJoinStrategy;
use sad_spirit\pg_builder\Select;
use sad_spirit\pg_builder\StatementFactory;

class ExplicitJoinStrategyTest extends TestCase
{
    use NormalizeWhitespace;

    private StatementFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new StatementFactory();
    }

    public function testInnerJoinSimple(): void
    {
        /** @var Select $base */
        $base = $this->factory->createFromString(
            "select self.*, m_2.* from foo as self, baz as m_2 where self.id <= m_2.id order by self.title limit 10"
        );
        /** @var Select $joined */
        $joined = $this->factory->createFromString(
            "select self.* from bar as self where self.title ~* 'something' order by self.title"
        );
        (new ExplicitJoinStrategy())->join(
            $base,
            clone $joined,
            $this->factory->getParser()->parseExpression('self.id >= joined.id'),
            'm_1',
            false
        );
        $this::assertStringEqualsStringNormalizingWhitespace(
            <<<SQL
select self.*, m_2.*, m_1.*
from foo as self inner join bar as m_1 on self.id >= m_1.id, baz as m_2
where  self.id <= m_2.id and m_1.title ~* 'something'
order by self.title, m_1.title limit 10
SQL
            ,
            $this->factory->createFromAST($base)->getSql()
        );

        $base = $this->factory->createFromString(
            "select count(self.*) from foo as self, baz as m_2 where self.id <= m_2.id"
        );
        (new ExplicitJoinStrategy())->join(
            $base,
            $joined,
            null,
            'm_1',
            true
        );
        $this::assertStringEqualsStringNormalizingWhitespace(
            <<<SQL
select count(self.*)
from foo as self inner join bar as m_1 on true, baz as m_2
where  self.id <= m_2.id and m_1.title ~* 'something'
SQL
            ,
            $this->factory->createFromAST($base)->getSql()
        );
    }

    public function testInnerJoinSubselect(): void
    {
        /** @var Select $base */
        $base = $this->factory->createFromString(
            "select self.* from foo as self where self.id > 0 order by self.title limit 10"
        );
        /** @var Select $joined */
        $joined = $this->factory->createFromString(
            "select self.*, m_2.* from bar as self, baz as m_2 "
            . "where self.title ~* 'something' and m_2.id > self.id order by self.title"
        );
        ($strategy = new ExplicitJoinStrategy())->join(
            $base,
            clone $joined,
            $this->factory->getParser()->parseExpression('self.id > joined.id'),
            'custom',
            false
        );
        $this::assertStringEqualsStringNormalizingWhitespace(
            <<<SQL
select self.*, custom.*
from foo as self inner join 
     (
        select {$strategy->getJoinedAlias()}.*, m_2.*
        from bar as {$strategy->getJoinedAlias()}, baz as m_2
        where {$strategy->getJoinedAlias()}.title ~* 'something' and m_2.id > {$strategy->getJoinedAlias()}.id
        order by {$strategy->getJoinedAlias()}.title
     ) as custom on self.id > custom.id
where self.id > 0
order by self.title limit 10
SQL
            ,
            $this->factory->createFromAST($base)->getSql()
        );

        $base = $this->factory->createFromString(
            "select count(self.*) from foo as self where self.id > 0"
        );
        ($strategy = new ExplicitJoinStrategy())->join(
            $base,
            $joined,
            $this->factory->getParser()->parseExpression('self.id < joined.id'),
            'bespoke',
            true
        );
        $this::assertStringEqualsStringNormalizingWhitespace(
            <<<SQL
select count(self.*)
from foo as self inner join 
     (
        select {$strategy->getJoinedAlias()}.*, m_2.*
        from bar as {$strategy->getJoinedAlias()}, baz as m_2
        where {$strategy->getJoinedAlias()}.title ~* 'something' and m_2.id > {$strategy->getJoinedAlias()}.id
        order by {$strategy->getJoinedAlias()}.title
     ) as bespoke on self.id < bespoke.id
where self.id > 0
SQL
            ,
            $this->factory->createFromAST($base)->getSql()
        );
    }

    public function testOuterJoinSimple(): void
    {
        /** @var Select $base */
        $base = $this->factory->createFromString(
            "select self.*, m_1.*, m_2.* from bar as m_1, foo as self join baz as m_2 on self.id = m_2.id "
            . "where self.id > m_1.id order by self.title limit 10"
        );
        /** @var Select $joined */
        $joined = $this->factory->createFromString(
            "with cte as (select 1) select self.* from quux as self"
        );

        (new ExplicitJoinStrategy(ExplicitJoinType::Left))->join(
            $base,
            clone $joined,
            $this->factory->getParser()->parseExpression('self.id = joined.id'),
            'm_3',
            false
        );
        $this::assertStringEqualsStringNormalizingWhitespace(
            <<<SQL
with cte as ( select 1 )
select self.*, m_1.*, m_2.*, m_3.*
from bar as m_1,
     foo as self inner join baz as m_2 on self.id = m_2.id 
         left join quux as m_3 on self.id = m_3.id
where self.id > m_1.id
order by self.title limit 10
SQL
            ,
            $this->factory->createFromAST($base)->getSql()
        );

        $base = $this->factory->createFromString(
            "select count(self.*) from bar as m_1, foo as self join baz as m_2 on self.id = m_2.id "
            . "where self.id > m_1.id"
        );

        (new ExplicitJoinStrategy(ExplicitJoinType::Left))->join(
            $base,
            $joined,
            $this->factory->getParser()->parseExpression('self.id = joined.id'),
            'm_3',
            true
        );
        $this::assertStringEqualsStringNormalizingWhitespace(
            <<<SQL
with cte as ( select 1 )
select count(self.*)
from bar as m_1,
     foo as self inner join baz as m_2 on self.id = m_2.id 
         left join quux as m_3 on self.id = m_3.id
where self.id > m_1.id
SQL
            ,
            $this->factory->createFromAST($base)->getSql()
        );
    }

    public function testOuterJoinSubselect(): void
    {
        /** @var Select $base */
        $base = $this->factory->createFromString(
            "select self.*, m_1.* from foo as self join bar as m_1 on self.id = m_1.id "
            . "order by self.title limit 10"
        );
        /** @var Select $joined */
        $joined = $this->factory->createFromString(
            "select self.* from baz as self where self.title ~* 'something'"
        );
        ($strategy = new ExplicitJoinStrategy(ExplicitJoinType::Left))->join(
            $base,
            $joined,
            $this->factory->getParser()->parseExpression('self.id > joined.id'),
            'm_2',
            false
        );
        /** @var Select $second */
        $second = $this->factory->createFromString('select * from another as self where self.id = 123');
        ($strategy2 = new ExplicitJoinStrategy(ExplicitJoinType::Left))->join(
            $base,
            $second,
            $this->factory->getParser()->parseExpression('self.id < joined.id'),
            'nthr',
            false
        );
        $this::assertStringEqualsStringNormalizingWhitespace(
            <<<SQL
select self.*, m_1.*, m_2.*, nthr.*
from foo as self inner join bar as m_1 on self.id = m_1.id
        left join (
            select {$strategy->getJoinedAlias()}.* from baz as {$strategy->getJoinedAlias()}
            where {$strategy->getJoinedAlias()}.title ~* 'something'
        ) as m_2 on self.id > m_2.id
        left join (
            select * from another as {$strategy2->getJoinedAlias()} where {$strategy2->getJoinedAlias()}.id = 123
        ) as nthr on self.id < nthr.id
order by self.title limit 10
SQL
            ,
            $this->factory->createFromAST($base)->getSql()
        );
    }

    public function testSubselectJoinWithMissingJoinFields(): void
    {
        /** @var Select $base */
        $base = $this->factory->createFromString('select self.* from foo as self order by self.title');
        /** @var Select $joined */
        $joined = $this->factory->createFromString('select self.not_id from bar as self limit 10');

        $this::expectException(LogicException::class);
        $this::expectExceptionMessage("column 'id' for joined table is not selected, but present in join condition");

        (new ExplicitJoinStrategy(ExplicitJoinType::Inner))->join(
            $base,
            $joined,
            $this->factory->getParser()->parseExpression('self.id = joined.id'),
            'e_1',
            false
        );
    }

    public function testSubselectJoinWithRenamedJoinFields(): void
    {
        /** @var Select $base */
        $base = $this->factory->createFromString('select self.* from foo as self order by self.title');
        /** @var Select $joined */
        $joined = $this->factory->createFromString('select self.id as alias from bar as self limit 10');

        ($strategy = new ExplicitJoinStrategy(ExplicitJoinType::Inner))->join(
            $base,
            $joined,
            $this->factory->getParser()->parseExpression('self.id = joined.id'),
            'r_1',
            false
        );
        $this::assertStringEqualsStringNormalizingWhitespace(
            <<<SQL
select self.*, r_1.*
from foo as self inner join (
            select {$strategy->getJoinedAlias()}.id as alias from bar as {$strategy->getJoinedAlias()} limit 10
        ) as r_1 on self.id = r_1.alias
order by self.title
SQL
            ,
            $this->factory->createFromAST($base)->getSql()
        );
    }

    public function testSubselectJoinWithCustomSelect(): void
    {
        /** @var Select $base */
        $base   = $this->factory->createFromString('select self.* from foo as self order by self.title');
        /** @var Select $first */
        $first  = $this->factory->createFromString('select a.id, a.foo as alias from bar as a limit 10');
        /** @var Select $second */
        $second = $this->factory->createFromString("select b.* from baz as b where b.title ~* 'something'");
        /** @var Select $third */
        $third  = $this->factory->createFromString('select * from quux as c where c.id = 123');

        (new ExplicitJoinStrategy(ExplicitJoinType::Inner))->join(
            $base,
            $first,
            $this->factory->getParser()->parseExpression('self.id = joined.id and self.foo = joined.alias'),
            'custom',
            false
        );
        (new ExplicitJoinStrategy(ExplicitJoinType::Left))->join(
            $base,
            $second,
            $this->factory->getParser()->parseExpression('self.id = joined.wtf'),
            'bespoke',
            false
        );
        (new ExplicitJoinStrategy(ExplicitJoinType::Right))->join(
            $base,
            $third,
            $this->factory->getParser()->parseExpression('self.foo = joined.nthr'),
            'tailored',
            false
        );
        $this::assertStringEqualsStringNormalizingWhitespace(
            <<<SQL
select self.*, custom.*, bespoke.*, tailored.*
from foo as self
        inner join (
            select a.id, a.foo as alias from bar as a limit 10
        ) as custom on 
            self.id = custom.id
                and self.foo = custom.alias
        left join (
            select b.* from baz as b where b.title ~* 'something'
        ) as bespoke on self.id = bespoke.wtf
        right join (
            select * from quux as c where c.id = 123
        ) as tailored on self.foo = tailored.nthr
order by self.title
SQL
            ,
            $this->factory->createFromAST($base)->getSql()
        );
    }

    public function testSubselectJoinWithCustomSelectMissingFields(): void
    {
        /** @var Select $base */
        $base   = $this->factory->createFromString('select self.* from foo as self order by self.title');
        /** @var Select $custom */
        $custom = $this->factory->createFromString(
            <<<SQL
select a.id as alias, coalesce(a.foo, b.foo) as bid
from bar as a, baz as b
where a.id = b.id
SQL
        );
        $this::expectException(LogicException::class);
        $this::expectExceptionMessage(
            "columns 'foo', 'nthr' for joined table are not selected, but present in join condition"
        );

        (new ExplicitJoinStrategy(ExplicitJoinType::Left))->join(
            $base,
            $custom,
            $this->factory->getParser()->parseExpression(
                'self.id = joined.bid and self.foo = joined.foo and joined.nthr is null'
            ),
            'wtf',
            false
        );
    }

    public function testSimpleJoinWithOnlyAJoinedFieldAsCondition(): void
    {
        /** @var Select $base */
        $base = $this->factory->createFromString(
            "select self.* from foo as self order by self.title limit 10"
        );
        /** @var Select $joined */
        $joined = $this->factory->createFromString(
            "select self.* from bar as self where self.title ~* 'something' order by self.title"
        );
        (new ExplicitJoinStrategy())->join(
            $base,
            clone $joined,
            $this->factory->getParser()->parseExpression('joined.foo'),
            'm_1',
            false
        );
        $this::assertStringEqualsStringNormalizingWhitespace(
            <<<SQL
select self.*, m_1.*
from foo as self inner join bar as m_1 on m_1.foo
where  m_1.title ~* 'something'
order by self.title, m_1.title limit 10
SQL
            ,
            $this->factory->createFromAST($base)->getSql()
        );
    }
    public function testSubselectJoinWithOnlyAJoinedFieldAsCondition(): void
    {
        /** @var Select $base */
        $base = $this->factory->createFromString('select self.* from foo as self');
        /** @var Select $joined */
        $joined = $this->factory->createFromString('select self.id, self.foo from bar as self limit 10');

        ($strategy = new ExplicitJoinStrategy(ExplicitJoinType::Inner))->join(
            $base,
            $joined,
            $this->factory->getParser()->parseExpression('joined.foo'),
            'r_1',
            false
        );
        $this::assertStringEqualsStringNormalizingWhitespace(
            <<<SQL
select self.*, r_1.*
from foo as self inner join (
            select {$strategy->getJoinedAlias()}.id, {$strategy->getJoinedAlias()}.foo
            from bar as {$strategy->getJoinedAlias()}
            limit 10
        ) as r_1 on r_1.foo
SQL
            ,
            $this->factory->createFromAST($base)->getSql()
        );
    }
}
