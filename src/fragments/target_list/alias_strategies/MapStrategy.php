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
use sad_spirit\pg_gateway\TableLocator;

/**
 * Finds an alias in explicitly provided mapping 'column name' => 'alias'
 */
class MapStrategy implements ColumnAliasStrategy
{
    /** @var array<string, string> */
    private array $columnMap;

    public function __construct(array $columnMap)
    {
        $this->columnMap = $columnMap;
    }

    public function getAlias(string $column): ?string
    {
        return \array_key_exists($column, $this->columnMap) ? $this->columnMap[$column] : null;
    }

    public function getKey(): ?string
    {
        return TableLocator::hash($this);
    }
}
