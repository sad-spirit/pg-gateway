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
 * Represents properties of a table column
 */
final readonly class Column
{
    /**
     * Constructor, sets the column's properties
     *
     * @param string             $name     Column name
     * @param bool               $nullable Whether column is nullable
     * @param int|numeric-string $typeOID  OID of the column data type
     */
    public function __construct(public string $name, public bool $nullable, public int|string $typeOID)
    {
    }
}
