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

/**
 * Interface for classes creating SELECT statements on demand
 *
 * This is a common interface for queries created by {@see TableGateway::select()} and those created by Parser
 * from SQL strings.
 *
 * @since 0.4.0
 */
interface SelectBuilder extends KeyEquatable
{
    /**
     * Returns the AST representing this SELECT statement
     *
     * This method is used when embedding the select query into a bigger statement via e.g. JOIN or EXISTS(...)
     *
     * @return SelectCommon
     */
    public function createSelectAST(): SelectCommon;
}
