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

namespace sad_spirit\pg_gateway\conditions;

use sad_spirit\pg_gateway\{
    Condition,
    ParameterHolder,
    Parametrized,
    holders\SimpleParameterHolder
};
use sad_spirit\pg_builder\nodes\ScalarExpression;

/**
 * A decorator around Condition that keeps values of parameters used by that Condition
 */
final class ParametrizedCondition extends Condition implements Parametrized
{
    private Condition $wrapped;
    private array $parameters;

    public function __construct(Condition $wrapped, array $parameters)
    {
        $this->wrapped = $wrapped;
        $this->parameters = $parameters;
    }

    protected function generateExpressionImpl(): ScalarExpression
    {
        return $this->wrapped->generateExpressionImpl();
    }

    public function getKey(): ?string
    {
        return $this->wrapped->getKey();
    }

    public function getParameterHolder(): ParameterHolder
    {
        return new SimpleParameterHolder($this->wrapped, $this->parameters);
    }
}
