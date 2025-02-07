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

namespace sad_spirit\pg_gateway\fragments;

use sad_spirit\pg_gateway\{
    Condition,
    Fragment,
    ParameterHolder,
    Parametrized,
    exceptions\InvalidArgumentException,
    holders\EmptyParameterHolder
};
use sad_spirit\pg_builder\Statement;

/**
 * Adds a Condition to the WHERE clause of a Statement
 *
 * Conditions are added to query using where->and() method, not by replacing $statement->where->condition,
 * as this allows using multiple fragments that modify WHERE clause.
 */
final class WhereClauseFragment implements Fragment, Parametrized
{
    use VariablePriority;

    public function __construct(private readonly Condition $condition, int $priority = self::PRIORITY_DEFAULT)
    {
        $this->setPriority($priority);
    }

    public function applyTo(Statement $statement): void
    {
        if (!isset($statement->where)) {
            throw new InvalidArgumentException(\sprintf(
                "WhereClauseFragment instances can only be added to Statements containing a WHERE clause, %s given",
                $statement::class
            ));
        }

        $statement->where->and($this->condition->generateExpression());
    }

    public function getKey(): ?string
    {
        $conditionKey = $this->condition->getKey();
        return null === $conditionKey ? null : 'where.' . $conditionKey;
    }

    public function getParameterHolder(): ParameterHolder
    {
        return $this->condition instanceof Parametrized
            ? $this->condition->getParameterHolder()
            : new EmptyParameterHolder();
    }
}
