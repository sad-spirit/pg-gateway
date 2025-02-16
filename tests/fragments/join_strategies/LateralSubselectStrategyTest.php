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
use sad_spirit\pg_gateway\fragments\join_strategies\LateralSubselectJoinType;
use sad_spirit\pg_gateway\fragments\join_strategies\LateralSubselectStrategy;
use sad_spirit\pg_gateway\tests\NormalizeWhitespace;
use sad_spirit\pg_builder\Select;
use sad_spirit\pg_builder\StatementFactory;

class LateralSubselectStrategyTest extends TestCase
{
    use NormalizeWhitespace;

    private StatementFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new StatementFactory();
    }

    public function testAppend(): void
    {
        /** @var Select $base */
        $base   = $this->factory->createFromString(
            "select self.* from foo as self"
        );
        /** @var Select $joined */
        $joined = $this->factory->createFromString(
            "select array_agg(gw_1.field) from gw_1"
        );

        ($strategy = new LateralSubselectStrategy())->join(
            $base,
            clone $joined,
            $this->factory->getParser()->parseExpression('self.id = joined.foo_id'),
            'gw_1',
            false
        );
        $this::assertStringEqualsStringNormalizingWhitespace(
            <<<SQL
select self.*, {$strategy->getSubselectAlias()}.*
from foo as self, lateral (
        select array_agg(gw_1.field)
        from gw_1
        where self.id = gw_1.foo_id
    ) as {$strategy->getSubselectAlias()}
SQL
            ,
            $this->factory->createFromAST($base)->getSql()
        );

        $base   = $this->factory->createFromString(
            "select count(self.*) from foo as self"
        );

        ($strategy = new LateralSubselectStrategy())->join(
            $base,
            $joined,
            $this->factory->getParser()->parseExpression('self.id = joined.foo_id'),
            'gw_1',
            true
        );
        $this::assertStringEqualsStringNormalizingWhitespace(
            <<<SQL
select count(self.*)
from foo as self, lateral (
        select array_agg(gw_1.field)
        from gw_1
        where self.id = gw_1.foo_id
    ) as {$strategy->getSubselectAlias()}
SQL
            ,
            $this->factory->createFromAST($base)->getSql()
        );
    }

    public function testJoin(): void
    {
        /** @var Select $base */
        $base   = $this->factory->createFromString(
            "select self.* from foo as self"
        );
        /** @var Select $joined */
        $joined = $this->factory->createFromString(
            "select array_agg(gw_1.field) from gw_1"
        );
        ($strategy = new LateralSubselectStrategy(LateralSubselectJoinType::LEFT))->join(
            $base,
            clone $joined,
            $this->factory->getParser()->parseExpression('self.id = joined.foo_id'),
            'gw_1',
            false
        );

        $this::assertStringEqualsStringNormalizingWhitespace(
            <<<SQL
select self.*, {$strategy->getSubselectAlias()}.*
from foo as self left join lateral (
        select array_agg(gw_1.field)
        from gw_1
        where self.id = gw_1.foo_id
    ) as {$strategy->getSubselectAlias()} on true
SQL
            ,
            $this->factory->createFromAST($base)->getSql()
        );

        $base   = $this->factory->createFromString(
            "select count(self.*) from foo as self"
        );
        ($strategy = new LateralSubselectStrategy(LateralSubselectJoinType::LEFT))->join(
            $base,
            clone $joined,
            $this->factory->getParser()->parseExpression('self.id = joined.foo_id'),
            'gw_1',
            true
        );

        $this::assertStringEqualsStringNormalizingWhitespace(
            <<<SQL
select count(self.*)
from foo as self left join lateral (
        select array_agg(gw_1.field)
        from gw_1
        where self.id = gw_1.foo_id
    ) as {$strategy->getSubselectAlias()} on true
SQL
            ,
            $this->factory->createFromAST($base)->getSql()
        );
    }
}
