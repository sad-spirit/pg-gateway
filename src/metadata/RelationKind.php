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
 * Represents possible kinds of relations that gateways can access
 *
 * The backing values directly correspond to those in `pg_class.relkind` column.
 * We don't care about indexes and sequences, so cases for these are not available.
 *
 * This cannot be used with EnumConverter as the type of `relkind` field is not enum, it's `char` (a single character).
 */
enum RelationKind: string
{
    case OrdinaryTable = 'r';
    case View = 'v';
    case MaterializedView = 'm';
    case ForeignTable = 'f';
    case PartitionedTable = 'p';

    public function toReadable(): string
    {
        return match ($this) {
            self::OrdinaryTable => 'ordinary table',
            self::View => 'view',
            self::MaterializedView => 'materialized view',
            self::ForeignTable => 'foreign table',
            self::PartitionedTable => 'partitioned table'
        };
    }
}
