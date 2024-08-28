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
    SelectBuilder,
    SelectFragment,
    TableGateway,
    TableLocator,
    exceptions\InvalidArgumentException,
    holders\ParameterHolderFactory,
    walkers\ReplaceTableAliasWalker
};
use sad_spirit\pg_gateway\fragments\join_strategies\InlineStrategy;
use sad_spirit\pg_builder\Statement;

/**
 * Fragment for joining a SelectProxy object to the current statement
 *
 * The actual merging of the two statements is performed by an implementation of JoinStrategy
 */
class JoinFragment implements SelectFragment, Parametrized
{
    use VariablePriority;

    private SelectBuilder $joined;
    private ?Condition $condition;
    private bool $usedForCount;
    private JoinStrategy $strategy;
    private ?string $explicitAlias;
    private ?string $alias = null;

    public function __construct(
        SelectBuilder $joined,
        Condition $condition = null,
        JoinStrategy $strategy = null,
        bool $usedForCount = true,
        int $priority = Fragment::PRIORITY_DEFAULT,
        ?string $explicitAlias = null
    ) {
        $this->joined = $joined;
        $this->condition = $condition;
        $this->strategy = $strategy ?? new InlineStrategy();
        $this->usedForCount = $usedForCount;
        $this->setPriority($priority);
        $this->explicitAlias = $explicitAlias;
    }

    /**
     * Returns the alias for the joined table
     *
     * @return string
     */
    protected function getAlias(): string
    {
        return $this->alias ??= $this->explicitAlias ?? TableLocator::generateAlias();
    }

    public function applyTo(Statement $statement, bool $isCount = false): void
    {
        if (!isset($statement->where)) {
            throw new InvalidArgumentException(\sprintf(
                "Joins can only be applied to Statements containing a WHERE clause, instance of %s given",
                \get_class($statement)
            ));
        }

        $alias  = $this->getAlias();
        $select = clone $this->joined->createSelectAST();
        $select->dispatch(new ReplaceTableAliasWalker(TableGateway::ALIAS_SELF, $alias));

        $condition = null === $this->condition ? null : $this->condition->generateExpression();

        $this->strategy->join(
            $statement,
            $select,
            $condition,
            $alias,
            $isCount
        );
    }

    public function getKey(): ?string
    {
        $joinedKey    = $this->joined->getKey();
        $conditionKey = null === $this->condition ? 'none' : $this->condition->getKey();
        $strategyKey  = $this->strategy->getKey();
        $aliasKey     = null === $this->explicitAlias ? '' : '.' . TableLocator::hash($this->explicitAlias);

        if (null === $joinedKey || null === $conditionKey || null === $strategyKey) {
            return null;
        }

        return $joinedKey . '.' . $conditionKey . '.' . $strategyKey . $aliasKey;
    }

    public function isUsedForCount(): bool
    {
        return $this->usedForCount;
    }

    public function getParameterHolder(): ParameterHolder
    {
        return ParameterHolderFactory::create($this->joined, $this->condition);
    }
}
