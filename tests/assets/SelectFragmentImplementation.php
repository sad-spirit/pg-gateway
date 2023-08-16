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

namespace sad_spirit\pg_gateway\tests\assets;

use sad_spirit\pg_builder\Statement;
use sad_spirit\pg_gateway\fragments\ClosureFragment;
use sad_spirit\pg_gateway\SelectFragment;

/**
 * An implementation of SelectFragment used for testing TableSelect
 */
class SelectFragmentImplementation extends ClosureFragment implements SelectFragment
{
    private bool $useForCount;

    public function __construct(\Closure $closure, bool $useForCount = true)
    {
        parent::__construct($closure);
        $this->useForCount = $useForCount;
    }

    public function applyTo(Statement $statement, bool $isCount = false): void
    {
        parent::applyTo($statement);
    }

    public function isUsedForCount(): bool
    {
        return $this->useForCount;
    }
}
