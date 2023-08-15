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

namespace sad_spirit\pg_gateway\tests\assets;

use sad_spirit\pg_gateway\Condition;
use sad_spirit\pg_builder\nodes\ScalarExpression;

/**
 * Condition implementation returning whatever was passed to its constructor
 */
class ConditionImplementation extends Condition
{
    private ScalarExpression $where;
    private ?string $key;

    public function __construct(ScalarExpression $where, ?string $key = null)
    {
        $this->where = $where;
        $this->key   = $key;
    }

    protected function generateExpressionImpl(): ScalarExpression
    {
        return $this->where;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }
}
