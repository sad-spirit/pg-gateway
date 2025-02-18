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
            "select m_1.* from bar as m_1 where m_1.title ~* 'something' order by m_1.title"
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
            "select m_1.*, m_2.* from bar as m_1, baz as m_2 "
            . "where m_1.title ~* 'something' and m_2.id > m_1.id order by m_1.title"
        );
        ($strategy = new ExplicitJoinStrategy())->join(
            $base,
            clone $joined,
            $this->factory->getParser()->parseExpression('self.id > joined.id'),
            'm_1',
            false
        );
        $this::assertStringEqualsStringNormalizingWhitespace(
            <<<SQL
select self.*, {$strategy->getSubselectAlias()}.*
from foo as self inner join 
     (
        select m_1.*, m_2.*
        from bar as m_1, baz as m_2
        where m_1.title ~* 'something' and m_2.id > m_1.id
        order by m_1.title
     ) as {$strategy->getSubselectAlias()} on self.id > {$strategy->getSubselectAlias()}.id
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
            'm_1',
            true
        );
        $this::assertStringEqualsStringNormalizingWhitespace(
            <<<SQL
select count(self.*)
from foo as self inner join 
     (
        select m_1.*, m_2.*
        from bar as m_1, baz as m_2
        where m_1.title ~* 'something' and m_2.id > m_1.id
        order by m_1.title
     ) as {$strategy->getSubselectAlias()} on self.id < {$strategy->getSubselectAlias()}.id
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
            "with cte as (select 1) select m_3.* from quux as m_3"
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
            "select m_2.* from baz as m_2 where m_2.title ~* 'something'"
        );
        ($strategy = new ExplicitJoinStrategy(ExplicitJoinType::Left))->join(
            $base,
            $joined,
            $this->factory->getParser()->parseExpression('self.id > joined.id'),
            'm_2',
            false
        );
        $this::assertStringEqualsStringNormalizingWhitespace(
            <<<SQL
select self.*, m_1.*, {$strategy->getSubselectAlias()}.*
from foo as self inner join bar as m_1 on self.id = m_1.id
        left join (
            select m_2.* from baz as m_2 where m_2.title ~* 'something'
        ) as {$strategy->getSubselectAlias()} on self.id > {$strategy->getSubselectAlias()}.id
order by self.title limit 10
SQL
            ,
            $this->factory->createFromAST($base)->getSql()
        );
    }
}
