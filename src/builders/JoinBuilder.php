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

namespace sad_spirit\pg_gateway\builders;

use sad_spirit\pg_gateway\{
    Condition,
    Fragment,
    fragments\JoinFragment,
    fragments\JoinStrategy
};
use sad_spirit\pg_gateway\fragments\join_strategies\{
    ExplicitJoinStrategy,
    ExplicitJoinType,
    InlineStrategy,
    LateralSubselectJoinType,
    LateralSubselectStrategy
};

/**
 * Builder for JoinFragment
 */
class JoinBuilder extends AdditionalSelectBuilder
{
    private ?Condition $condition = null;
    private ?JoinStrategy $strategy = null;
    private bool $usedForCount = true;
    private int $priority = Fragment::PRIORITY_DEFAULT;

    public function getFragment(): Fragment
    {
        return new JoinFragment(
            $this->wrapAdditional(),
            $this->condition,
            $this->strategy ?? new InlineStrategy(),
            $this->usedForCount,
            $this->priority,
            $this->alias
        );
    }

    /**
     * Uses the explicitly provided strategy for adding the joined table
     *
     * @return $this
     */
    public function strategy(JoinStrategy $strategy): self
    {
        $this->strategy = $strategy;

        return $this;
    }

    /**
     * Makes the joined table a separate item of the base statement's FROM clause
     *
     * @return $this
     */
    public function inline(): self
    {
        $this->strategy = new InlineStrategy();

        return $this;
    }

    /**
     * Adds the joined table to the base one via INNER JOIN clause
     *
     * @return $this
     */
    public function inner(): self
    {
        $this->strategy = new ExplicitJoinStrategy(ExplicitJoinType::Inner);

        return $this;
    }

    /**
     * Adds the joined table to the base one via LEFT JOIN clause
     *
     * @return $this
     */
    public function left(): self
    {
        $this->strategy = new ExplicitJoinStrategy(ExplicitJoinType::Left);

        return $this;
    }

    /**
     * Adds the joined table to the base one via RIGHT JOIN clause
     *
     * @return $this
     */
    public function right(): self
    {
        $this->strategy = new ExplicitJoinStrategy(ExplicitJoinType::Right);

        return $this;
    }

    /**
     * Adds the joined table to the base one via FULL JOIN clause
     *
     * @return $this
     */
    public function full(): self
    {
        $this->strategy = new ExplicitJoinStrategy(ExplicitJoinType::Full);

        return $this;
    }

    /**
     * Wraps the joined Select in a LATERAL subselect and adds that as a separate item
     * to the base statement's FROM clause.
     *
     * @return $this
     */
    public function lateral(): self
    {
        $this->strategy = new LateralSubselectStrategy(LateralSubselectJoinType::Append);

        return $this;
    }

    /**
     * Wraps the joined Select in a LATERAL subselect and adds that to the base table via INNER JOIN clause
     *
     * @return $this
     */
    public function lateralInner(): self
    {
        $this->strategy = new LateralSubselectStrategy(LateralSubselectJoinType::Inner);

        return $this;
    }

    /**
     * Wraps the joined Select in a LATERAL subselect and adds that to the base table via LEFT JOIN clause
     *
     * @return $this
     */
    public function lateralLeft(): self
    {
        $this->strategy = new LateralSubselectStrategy(LateralSubselectJoinType::Left);

        return $this;
    }

    /**
     * Sets the join condition between the base and the checked table
     *
     * @return $this
     */
    public function on(Condition $condition): self
    {
        $this->condition = $condition;

        return $this;
    }

    /**
     * Sets the join condition based on a FOREIGN KEY constraint between the base and the joined tables
     *
     * @param string[] $keyColumns If there are several FOREIGN KEY constraints between the tables,
     *                             specify the columns on the child side that should be part of the key
     * @return $this
     */
    public function onForeignKey(array $keyColumns = []): self
    {
        return $this->on($this->createForeignKeyCondition($keyColumns));
    }

    /**
     * Sets the self-join condition based on a recursive FOREIGN KEY constraint
     *
     * @param bool $fromChild Whether the base table should be on the child side (default) of the join or the parent one
     * @param string[] $keyColumns In the unlikely event that there are several recursive FOREIGN KEY constraints
     *                             this specifies the columns on the child side that should be part of the key
     * @return $this
     */
    public function onRecursiveForeignKey(bool $fromChild = true, array $keyColumns = []): self
    {
        return $this->on($this->createForeignKeyCondition($keyColumns, $fromChild));
    }

    /**
     * Removes the join condition
     *
     * @return $this
     */
    public function unconditional(): self
    {
        $this->condition = null;

        return $this;
    }

    /**
     * Sets the priority of the Fragment being generated
     *
     * Order may be important for the parts of the FROM clause, especially LATERAL ones. Fragments with higher
     * priorities will be added earlier.
     *
     * @return $this
     */
    public function priority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * Sets whether this join should be executed when performing the SELECT COUNT(*) query
     *
     * @return $this
     */
    public function useForCount(bool $use): self
    {
        $this->usedForCount = $use;

        return $this;
    }
}
