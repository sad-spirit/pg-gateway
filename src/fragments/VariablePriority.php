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

use sad_spirit\pg_gateway\Fragment;

/**
 * Trait for fragments that have user-specified priority
 *
 * @psalm-require-implements Fragment
 */
trait VariablePriority
{
    private int $priority = Fragment::PRIORITY_DEFAULT;

    public function getPriority(): int
    {
        return $this->priority;
    }

    protected function setPriority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }
}
