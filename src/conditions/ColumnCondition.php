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

namespace sad_spirit\pg_gateway\conditions;

use sad_spirit\pg_gateway\{
    Condition,
    TableLocator,
    metadata\Column
};

/**
 * Base class for Conditions that check the value of a specific table column
 */
abstract class ColumnCondition extends Condition
{
    public function __construct(protected Column $column)
    {
    }

    public function getKey(): ?string
    {
        return TableLocator::hash([static::class, $this->column]);
    }
}
