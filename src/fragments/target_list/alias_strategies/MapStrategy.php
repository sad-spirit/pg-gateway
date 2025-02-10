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
readonly class MapStrategy implements ColumnAliasStrategy
{
    public function __construct(
        /** @var array<string, string> */
        private array $columnMap
    ) {
    }

    public function getAlias(string $column): ?string
    {
        $mapped = $this->columnMap[$column] ?? null;
        return (null === $mapped || $column === $mapped) ? null : $mapped;
    }

    public function getKey(): ?string
    {
        return TableLocator::hash($this);
    }
}
