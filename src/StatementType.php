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

namespace sad_spirit\pg_gateway;

/**
 * Contains types of statements created by gateways
 */
enum StatementType: string
{
    case Count = 'count';
    case Delete = 'delete';
    case Insert = 'insert';
    case Select = 'select';
    case Update = 'update';
    case Upsert = 'upsert';
}
