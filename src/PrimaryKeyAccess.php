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

use sad_spirit\pg_wrapper\Result;

/**
 * Interface for gateways to tables that have a primary key defined
 */
interface PrimaryKeyAccess
{
    /**
     * Deletes a row with the given primary key
     */
    public function deleteByPrimaryKey(mixed $primaryKey): Result;

    /**
     * Returns an object that can SELECT a row with the given primary key
     */
    public function selectByPrimaryKey(mixed $primaryKey): SelectProxy;

    /**
     * Updates a row with the given primary key using the given values
     */
    public function updateByPrimaryKey(mixed $primaryKey, array $set): Result;

    /**
     * Executes an "UPSERT" (INSERT ... ON CONFLICT DO UPDATE ...) query with the given values
     *
     * @return array Primary key of the row inserted / updated
     */
    public function upsert(array $values): array;
}
