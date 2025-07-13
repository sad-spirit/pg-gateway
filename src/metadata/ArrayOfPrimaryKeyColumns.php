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

namespace sad_spirit\pg_gateway\metadata;

/**
 * Implementation of methods defined in PrimaryKey using an array of Column instances
 *
 * @psalm-require-implements PrimaryKey
 * @since 0.3.0
 */
trait ArrayOfPrimaryKeyColumns
{
    /**
     * Columns of the table's primary key
     * @var Column[]
     */
    protected array $columns = [];

    /**
     * Whether table's primary key is automatically generated
     * @var bool
     */
    protected bool $generated = false;

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->columns);
    }

    /**
     * Returns the number of columns in table's primary key
     *
     * {@inheritDoc}
     */
    public function count(): int
    {
        return \count($this->columns);
    }

    /**
     * Returns the columns of the table's primary key
     *
     * @return Column[]
     */
    public function getAll(): array
    {
        return $this->columns;
    }

    /**
     * Returns names of the columns in the table's primary key
     *
     * @return string[]
     */
    public function getNames(): array
    {
        return \array_map(fn (Column $column): string => $column->name, $this->columns);
    }

    /**
     * Returns whether table's primary key is automatically generated
     */
    public function isGenerated(): bool
    {
        return $this->generated;
    }
}
