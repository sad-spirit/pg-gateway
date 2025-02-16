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

declare(strict_types=1);

namespace sad_spirit\pg_gateway\tests\fragments\target_list\alias_strategies;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_gateway\fragments\target_list\alias_strategies\PregReplaceStrategy;

class PregReplaceStrategyTest extends TestCase
{
    public function testStringPatternReplacement(): void
    {
        $strategy = new PregReplaceStrategy('/^[^_]+_/', '');

        $this::assertEquals('login', $strategy->getAlias('user_login'));
        $this::assertEquals('name', $strategy->getAlias('role_name'));
        $this::assertNull($strategy->getAlias('foo'));
    }

    public function testArrayPatternReplacement(): void
    {
        $strategy = new PregReplaceStrategy(['/^user_/', '/^role_/'], ['loser_', 'chore_']);

        $this::assertEquals('loser_login', $strategy->getAlias('user_login'));
        $this::assertEquals('chore_name', $strategy->getAlias('role_name'));
        $this::assertNull($strategy->getAlias('category_title'));
    }

    public function testNullOnError(): void
    {
        $strategy = new PregReplaceStrategy('/^[^_]+_/u', '');

        $this::assertNull($strategy->getAlias('prefix_ident' . \chr(240) . 'ifier'));
    }

    public function testGetKey(): void
    {
        $strategyOne   = new PregReplaceStrategy('foo', 'bar');
        $strategyTwo   = new PregReplaceStrategy('foo', 'bar');
        $strategyThree = new PregReplaceStrategy('bar', 'foo');

        $this::assertEquals($strategyOne->getKey(), $strategyTwo->getKey());
        $this::assertNotEquals($strategyOne->getKey(), $strategyThree->getKey());
    }
}
