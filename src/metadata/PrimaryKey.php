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

/**
 * Contains information about table's primary key
 *
 * @extends \IteratorAggregate<int, Column>
 */
interface PrimaryKey extends \IteratorAggregate, \Countable
{
    /**
     * Returns the columns of the table's primary key
     *
     * @return Column[]
     */
    public function getAll(): array;

    /**
     * Returns names of the columns in the table's primary key
     *
     * @return string[]
     */
    public function getNames(): array;

    /**
     * Returns whether table's primary key is automatically generated
     */
    public function isGenerated(): bool;
}
