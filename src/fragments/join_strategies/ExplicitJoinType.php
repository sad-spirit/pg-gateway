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

namespace sad_spirit\pg_gateway\fragments\join_strategies;

/**
 * Possible join types for {@see ExplicitJoinStrategy}
 */
enum ExplicitJoinType: string
{
    case LEFT = 'left';
    case RIGHT = 'right';
    case INNER = 'inner';
    case FULL = 'full';
}
