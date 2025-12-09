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

namespace sad_spirit\pg_gateway\fragments\target_list\alias_strategies;

use sad_spirit\pg_gateway\fragments\target_list\ColumnAliasStrategy;
use sad_spirit\pg_gateway\TableLocator;

/**
 * Creates an alias by using preg_replace() on column name
 */
final readonly class PregReplaceStrategy implements ColumnAliasStrategy
{
    /**
     * Constructor, $pattern and $replacement will be passed to preg_replace() eventually
     *
     * @param non-empty-string|non-empty-string[] $pattern
     * @param string|string[]                     $replacement
     */
    public function __construct(private string|array $pattern, private string|array $replacement)
    {
    }

    public function getAlias(string $column): ?string
    {
        $result = \preg_replace($this->pattern, $this->replacement, $column);
        return (null === $result || $result === $column) ? null : $result;
    }

    public function getKey(): ?string
    {
        return TableLocator::hash($this);
    }
}
