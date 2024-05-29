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

use sad_spirit\pg_builder\SelectCommon;
use sad_spirit\pg_wrapper\Result;

/**
 * Interface for classes executing SELECT queries on demand
 *
 * An instance of the class implementing this interface should contain all the data needed
 * to execute "SELECT" (and "SELECT COUNT(*)"), with actual queries executed only when
 * calling getIterator() and executeCount(), respectively.
 *
 * Unlike delete() / insert() / update() methods that immediately execute the built query and return Result,
 * TableGateway::select() returns an object implementing this interface:
 *   - it is frequently needed to additionally execute the query that returns the total number of rows
 *     that satisfy the given conditions (e.g. for pagination)
 *   - several such objects may be combined to create a complex query
 *
 * The most simple case still stays simple thanks to IteratorAggregate:
 * <code>
 * foreach ($gateway->select(...) as $row) {
 *    ...
 * }
 * </code>
 *
 * @extends \IteratorAggregate<int, array>
 */
interface SelectProxy extends KeyEquatable, Parametrized, TableAccessor, \IteratorAggregate
{
    /**
     * Executes the "SELECT COUNT(*)" query with current fragments and returns the resultant value
     *
     * Fragments that are not applicable for this type of query (checked by {@see SelectFragment::isUsedForCount()})
     * will be omitted.
     *
     * NB: There is no "int" return typehint as "COUNT()" returns bigint in Postgres and that may be out of range for
     * PHP's int type on 32-bit builds
     *
     * @return int|numeric-string
     */
    public function executeCount();

    /**
     * Executes the "SELECT [target list]" query with current fragments and returns its result
     *
     * @return Result
     */
    public function getIterator(): Result;

    /**
     * Returns the AST representing this SELECT statement
     *
     * This method is used when embedding the select query into a bigger statement via e.g. JOIN or EXISTS(...)
     *
     * @return SelectCommon
     */
    public function createSelectAST(): SelectCommon;
}
