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

namespace sad_spirit\pg_gateway\fragments\join_strategies;

/**
 * Possible join types for {@see LateralSubselectStrategy}
 */
enum LateralSubselectJoinType: string
{
    /** A special "join type" that triggers adding the subselect as a separate item of FROM clause */
    case APPEND = 'append';
    case INNER = 'inner';
    case LEFT = 'left';
}
