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
     *
     * @param mixed $primaryKey
     * @return Result
     */
    public function deleteByPrimaryKey($primaryKey): Result;

    /**
     * Returns an object that can SELECT a row with the given primary key
     *
     * @param mixed $primaryKey
     * @return SelectProxy
     */
    public function selectByPrimaryKey($primaryKey): SelectProxy;

    /**
     * Updates a row with the given primary key using the given values
     *
     * @param mixed $primaryKey
     * @param array $set
     * @return Result
     */
    public function updateByPrimaryKey($primaryKey, array $set): Result;

    /**
     * Executes an "UPSERT" (INSERT ... ON CONFLICT DO UPDATE ...) query with the given values
     *
     * @param array $values
     * @return array Primary key of the row inserted / updated
     */
    public function upsert(array $values): array;
}
