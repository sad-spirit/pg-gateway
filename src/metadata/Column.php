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
 * Represents properties of a table column
 */
final class Column
{
    private string $name;
    private bool $nullable;
    /** @var int|numeric-string */
    private $typeOID;

    /**
     * Constructor, sets the column's properties
     *
     * @param string             $name     Column name
     * @param bool               $nullable Whether column is nullable
     * @param int|numeric-string $typeOID  OID of the column data type
     */
    public function __construct(string $name, bool $nullable, $typeOID)
    {
        $this->name = $name;
        $this->nullable = $nullable;
        $this->typeOID = $typeOID;
    }

    /**
     * Returns the column name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns whether the column is nullable
     *
     * @return bool
     */
    public function isNullable(): bool
    {
        return $this->nullable;
    }

    /**
     * Returns OID of the column data type
     *
     * If the column type is a domain, then OID of the base type will be returned
     *
     * @return int|numeric-string
     */
    public function getTypeOID()
    {
        return $this->typeOID;
    }
}
