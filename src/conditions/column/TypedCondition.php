<?php

/*
 * This file is part of sad_spirit/pg_gateway package
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace sad_spirit\pg_gateway\conditions\column;

use sad_spirit\pg_builder\converters\TypeNameNodeHandler;
use sad_spirit\pg_gateway\conditions\ColumnCondition;
use sad_spirit\pg_gateway\metadata\Column;

/**
 * Base class for Conditions that require type data for a column
 */
abstract class TypedCondition extends ColumnCondition
{
    protected TypeNameNodeHandler $converterFactory;

    public function __construct(Column $column, TypeNameNodeHandler $converterFactory)
    {
        parent::__construct($column);
        $this->converterFactory = $converterFactory;
    }
}
