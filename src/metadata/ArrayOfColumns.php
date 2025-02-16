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

use sad_spirit\pg_gateway\exceptions\OutOfBoundsException;

/**
 * Implementation of methods defined in Columns using an array of Column instances
 *
 * @psalm-require-implements Columns
 * @since 0.3.0
 */
trait ArrayOfColumns
{
    /**
     * Table columns
     * @var array<string, Column>
     */
    private array $columns = [];

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->columns);
    }

    public function count(): int
    {
        return \count($this->columns);
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, Column>
     */
    public function getAll(): array
    {
        return $this->columns;
    }

    /**
     * {@inheritDoc}
     *
     * @return string[]
     */
    public function getNames(): array
    {
        return \array_keys($this->columns);
    }

    public function has(string $column): bool
    {
        return \array_key_exists($column, $this->columns);
    }

    public function get(string $column): Column
    {
        if (!\array_key_exists($column, $this->columns)) {
            throw new OutOfBoundsException(\sprintf("Column %s does not exist", $column));
        }
        return $this->columns[$column];
    }
}
