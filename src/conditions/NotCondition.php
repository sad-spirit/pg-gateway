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

namespace sad_spirit\pg_gateway\conditions;

use sad_spirit\pg_gateway\{
    Condition,
    ParameterHolder,
    Parametrized,
    holders\EmptyParameterHolder
};
use sad_spirit\pg_builder\nodes\{
    ScalarExpression,
    expressions\NegatableExpression,
    expressions\NotExpression
};

/**
 * Applies NOT operator to the given Condition
 */
final class NotCondition extends Condition implements Parametrized
{
    public function __construct(private readonly Condition $child)
    {
    }

    protected function generateExpressionImpl(): ScalarExpression
    {
        $childExpression = $this->child->generateExpression();
        if ($childExpression instanceof NotExpression) {
            return $childExpression->argument;
        } elseif ($childExpression instanceof NegatableExpression) {
            $childExpression->not = !$childExpression->not;
            return $childExpression;
        } else {
            return new NotExpression($childExpression);
        }
    }

    public function getKey(): ?string
    {
        return null !== ($key = $this->child->getKey())
            ? 'not.' . $key
            : null;
    }

    public function getParameterHolder(): ParameterHolder
    {
        return $this->child instanceof Parametrized
            ? $this->child->getParameterHolder()
            : new EmptyParameterHolder();
    }
}
