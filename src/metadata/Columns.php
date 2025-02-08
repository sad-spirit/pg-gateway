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

namespace sad_spirit\pg_gateway\metadata;

use sad_spirit\pg_gateway\exceptions\OutOfBoundsException;

/**
 * Contains information about table columns
 *
 * @extends \IteratorAggregate<string, Column>
 */
interface Columns extends \IteratorAggregate, \Countable
{
    /**
     * Returns all columns
     *
     * @return array<string, Column>
     */
    public function getAll(): array;

    /**
     * Returns column names
     *
     * @return string[]
     */
    public function getNames(): array;

    /**
     * Checks whether the column with a given name exists
     */
    public function has(string $column): bool;

    /**
     * Returns the given column's properties
     *
     * @throws OutOfBoundsException If the column was not found
     */
    public function get(string $column): Column;
}
