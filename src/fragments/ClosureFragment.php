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

namespace sad_spirit\pg_gateway\fragments;

use sad_spirit\pg_builder\Statement;
use sad_spirit\pg_gateway\Fragment;

/**
 * Wrapper around closure passed to a TableGateway method as $fragments parameter
 */
class ClosureFragment implements Fragment
{
    private \Closure $closure;

    public function __construct(\Closure $closure)
    {
        $this->closure = $closure;
    }

    final public function getKey(): ?string
    {
        // Queries with ad-hoc fragments should never be cached
        return null;
    }

    public function getPriority(): int
    {
        return Fragment::PRIORITY_DEFAULT;
    }

    public function apply(Statement $statement): void
    {
        ($this->closure)($statement);
    }
}
