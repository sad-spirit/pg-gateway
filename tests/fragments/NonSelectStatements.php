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

/**
 * @noinspection SqlResolve
 * @noinspection SqlWithoutWhere
 */

namespace sad_spirit\pg_gateway\tests\fragments;

trait NonSelectStatements
{
    public static function nonApplicableStatementsProvider(): array
    {
        return [
            ['delete from a_table'],
            ['update a_table set foo = null'],
            ['insert into a_table default values']
        ];
    }
}
