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

use sad_spirit\pg_builder\{
    Delete,
    Insert,
    SelectCommon,
    Update
};
use sad_spirit\pg_wrapper\Result;

/**
 * Performs custom queries to the given table by directly modifying the query ASTs
 *
 * The methods in this interface are intended for one-off queries that do not need to be cached. Internally these are
 * backed by ClosureFragment.
 *
 * @since 0.4.0
 */
interface AdHocStatement
{
    /**
     * Generates a DELETE statement using the given closure and executes it with given parameters
     *
     * @param \Closure(Delete): mixed $closure Accepts a base DELETE statement created by the gateway and
     *                                         configures it as needed
     * @param array<string, mixed> $parameters
     * @return Result
     */
    public function deleteWithAST(\Closure $closure, array $parameters = []): Result;

    /**
     * Generates an INSERT statement for the given values using the given closure and executes it with given parameters
     *
     * @param array<string, mixed>|SelectCommon|SelectProxy $values This is either an array with table columns' values
     *                                                  or a SELECT statement that is used directly for the $values
     *                                                  property of Insert object being created
     *                                                  or a proxy for SELECT statement generated by another Gateway
     * @param \Closure(Insert): mixed $closure Accepts a base INSERT statement created by the gateway and
     *                                         configures it as needed
     * @param array<string, mixed> $parameters
     * @return Result
     */
    public function insertWithAST($values, \Closure $closure, array $parameters = []): Result;

    /**
     * Returns an object that can execute SELECT / SELECT COUNT(*) queries using the given closure with given parameters
     *
     * @param \Closure(SelectCommon): mixed $closure Accepts a base SELECT statement created by the gateway and
     *                                               configures it as needed
     * @param array<string, mixed> $parameters
     * @return SelectProxy
     */
    public function selectWithAST(\Closure $closure, array $parameters = []): SelectProxy;

    /**
     * Generates an UPDATE statement for the given columns using the given closure and executes it with given parameters
     *
     * @param array<string, mixed> $set New values for columns. The array may have instances of Expression or
     *                                  Nodes implementing ScalarExpression as values, those will be directly
     *                                  inserted into generated SQL
     * @param \Closure(Update): mixed $closure Accepts a base UPDATE statement created by the gateway and
     *                                         configures it as needed
     * @param array<string, mixed> $parameters
     * @return Result
     */
    public function updateWithAST(array $set, \Closure $closure, array $parameters = []): Result;
}