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

    /**
     * Returns the column name
     *
     * @deprecated Since 0.10.0: use {@see $name} property
     */
    public function getName(): string
    {
        @\trigger_error(\sprintf(
            'The "%s()" method is deprecated since release 0.10.0, '
            . 'use $name property instead.',
            __METHOD__
        ), \E_USER_DEPRECATED);
        return $this->name;
    }

    /**
     * Returns whether the column is nullable
     *
     * @deprecated Since 0.10.0: use {@see $nullable} property
     */
    public function isNullable(): bool
    {
        @\trigger_error(\sprintf(
            'The "%s()" method is deprecated since release 0.10.0, '
            . 'use $nullable property instead.',
            __METHOD__
        ), \E_USER_DEPRECATED);
        return $this->nullable;
    }

    /**
     * Returns OID of the column data type
     *
     * If the column type is a domain, then OID of the base type will be returned
     *
     * @return int|numeric-string
     *
     * @deprecated Since 0.10.0: use {@see $typeOID} property
     */
    public function getTypeOID(): int|string
    {
        @\trigger_error(\sprintf(
            'The "%s()" method is deprecated since release 0.10.0, '
            . 'use $typeOID property instead.',
            __METHOD__
        ), \E_USER_DEPRECATED);
        return $this->typeOID;
    }
}
