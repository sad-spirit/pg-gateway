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

use sad_spirit\pg_gateway\metadata\TableName;

/**
 * Interface for classes creating instances of TableDefinition
 *
 * @since 0.2.0
 */
interface TableDefinitionFactory
{
    /**
     * Returns an implementation of TableDefinition for the given table name
     */
    public function create(TableName $name): TableDefinition;
}
