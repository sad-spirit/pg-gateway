<?php

/*
 * This file is part of sad_spirit/pg_gateway package
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace sad_spirit\pg_gateway\tests\fragments\target_list\alias_strategies;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_gateway\fragments\target_list\alias_strategies\MapStrategy;

class MapStrategyTest extends TestCase
{
    public function testGetAlias(): void
    {
        $strategy = new MapStrategy([
            'user_login' => 'loser_login',
            'role_name'  => 'chore_name'
        ]);

        $this::assertEquals('loser_login', $strategy->getAlias('user_login'));
        $this::assertEquals('chore_name', $strategy->getAlias('role_name'));
        $this::assertNull($strategy->getAlias('category_title'));
    }

    public function testGetKey(): void
    {
        $strategyOne   = new MapStrategy(['foo' => 'bar']);
        $strategyTwo   = new MapStrategy(['foo' => 'bar']);
        $strategyThree = new MapStrategy(['bar' => 'foo']);

        $this::assertEquals($strategyOne->getKey(), $strategyTwo->getKey());
        $this::assertNotEquals($strategyOne->getKey(), $strategyThree->getKey());
    }
}
