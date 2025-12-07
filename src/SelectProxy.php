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

namespace sad_spirit\pg_gateway;

use sad_spirit\pg_wrapper\Result;

/**
 * Interface for classes executing SELECT queries on demand
 *
 * An instance of the class implementing this interface should contain all the data needed
 * to execute `SELECT` (and `SELECT COUNT(*)`), with actual queries executed only when
 * calling `getIterator()` and `executeCount()`, respectively.
 *
 * Unlike `delete()` / `insert()` / `update()` methods that immediately execute the built query and return `Result`,
 * {@see \sad_spirit\pg_gateway\TableGateway::select() TableGateway::select()}
 * returns an object implementing this interface:
 *   - it is frequently needed to additionally execute the query that returns the total number of rows
 *     that satisfy the given conditions (e.g. for pagination)
 *   - several such objects may be combined to create a complex query
 *
 * The most simple case still stays simple thanks to IteratorAggregate:
 * ```PHP
 * foreach ($gateway->select(...) as $row) {
 *    // ...
 * }
 * ```
 *
 * @extends \IteratorAggregate<int, array>
 */
interface SelectProxy extends SelectBuilder, Parametrized, TableAccessor, \IteratorAggregate
{
    /**
     * Executes the "SELECT COUNT(*)" query with current fragments and returns the resultant value
     *
     * Fragments that are not applicable for this type of query (checked by
     * {@see \sad_spirit\pg_gateway\SelectFragment::isUsedForCount() SelectFragment::isUsedForCount()})
     * will be omitted.
     *
     * NB: There is no `int` return typehint as `COUNT()` returns `bigint` in Postgres and that may be out of range for
     * PHP's `int` type on 32-bit builds
     *
     * @return int|numeric-string
     */
    public function executeCount(): int|string;

    /**
     * Executes the "SELECT [target list]" query with current fragments and returns its result
     *
     * @return Result
     */
    public function getIterator(): Result;
}
