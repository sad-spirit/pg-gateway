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
    exceptions\InvalidArgumentException,
    holders\SimpleParameterHolder
};
use sad_spirit\pg_builder\nodes\ScalarExpression;

/**
 * A decorator around Condition that keeps values of parameters used by that Condition
 */
final class ParametrizedCondition extends Condition implements Parametrized
{
    private readonly Condition $wrapped;

    /**
     * Constructor
     *
     * @param array<string, mixed> $parameters
     */
    public function __construct(Condition $wrapped, private readonly array $parameters)
    {
        if ($wrapped instanceof Parametrized) {
            throw new InvalidArgumentException(\sprintf(
                "%s already implements Parametrized interface",
                $wrapped::class
            ));
        }
        $this->wrapped = $wrapped;
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
