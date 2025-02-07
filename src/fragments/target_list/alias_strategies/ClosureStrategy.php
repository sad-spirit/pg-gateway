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

namespace sad_spirit\pg_gateway\fragments\target_list\alias_strategies;

use sad_spirit\pg_gateway\fragments\target_list\ColumnAliasStrategy;

/**
 * Creates an alias by running the provided callback on the column name
 */
readonly class ClosureStrategy implements ColumnAliasStrategy
{
    public function __construct(private \Closure $closure, private ?string $key = null)
    {
    }

    public function getAlias(string $column): ?string
    {
        $result = ($this->closure)($column);
        return (null === $result || $column === (string)$result) ? null : (string)$result;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }
}
