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

namespace sad_spirit\pg_gateway\tests\assets;

use sad_spirit\pg_gateway\Condition;
use sad_spirit\pg_builder\nodes\ScalarExpression;

/**
 * Condition implementation returning whatever was passed to its constructor
 */
class ConditionImplementation extends Condition
{
    public function __construct(private readonly ScalarExpression $where, private readonly ?string $key = null)
    {
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
