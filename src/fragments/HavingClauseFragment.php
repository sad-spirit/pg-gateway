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

namespace sad_spirit\pg_gateway\fragments;

use sad_spirit\pg_gateway\{
    Condition,
    ParameterHolder,
    Parametrized,
    SelectFragment,
    exceptions\InvalidArgumentException,
    holders\EmptyParameterHolder
};
use sad_spirit\pg_builder\Select;
use sad_spirit\pg_builder\Statement;

/**
 * Adds a Condition to the HAVING clause of a SELECT Statement
 *
 * Conditions are added to query using having->and() method, not by replacing $statement->having->condition,
 * as this allows using multiple fragments that modify HAVING clause.
 */
final class HavingClauseFragment implements SelectFragment, Parametrized
{
    use VariablePriority;

    public function __construct(private readonly Condition $condition, int $priority = self::PRIORITY_DEFAULT)
    {
        $this->setPriority($priority);
    }

    public function applyTo(Statement $statement, bool $isCount = false): void
    {
        if (!$statement instanceof Select) {
            throw new InvalidArgumentException(\sprintf(
                "HavingClauseFragment instances can only be added to SELECT Statements, %s given",
                $statement::class
            ));
        }

        $statement->having->and($this->condition->generateExpression());
    }

    public function getKey(): ?string
    {
        $conditionKey = $this->condition->getKey();
        return null === $conditionKey ? null : 'having.' . $conditionKey;
    }

    /**
     * {@inheritDoc}
     *
     * Adding a HAVING clause to a query that should return a total number of rows does not make much sense
     */
    public function isUsedForCount(): bool
    {
        return false;
    }

    public function getParameterHolder(): ParameterHolder
    {
        return $this->condition instanceof Parametrized
            ? $this->condition->getParameterHolder()
            : new EmptyParameterHolder();
    }
}
