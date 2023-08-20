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
use sad_spirit\pg_gateway\fragments\target_list\alias_strategies\ClosureStrategy;

class ClosureStrategyTest extends TestCase
{
    public function testGetAlias(): void
    {
        $strategy = new ClosureStrategy(function (string $column) {
            $parts = \explode('_', $column);
            return \array_shift($parts) . \implode('', \array_map('ucfirst', $parts));
        });

        $this::assertEquals('userLogin', $strategy->getAlias('user_login'));
        $this::assertEquals('roleName', $strategy->getAlias('role_name'));
        $this::assertNull($strategy->getAlias('foo'));
    }
}
