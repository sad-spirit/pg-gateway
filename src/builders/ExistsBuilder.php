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
    conditions\ExistsCondition,
    conditions\NotCondition,
    fragments\WhereClauseFragment
};

/**
 * Builder for ExistsCondition
 *
 * We don't (yet?) have a ConditionBuilder, so this is essentially a builder for WhereClauseFragment
 * which happens to also implement a getCondition() method
 */
class ExistsBuilder extends AdditionalSelectBuilder
{
    private ?Condition $joinCondition = null;
    private bool $not = false;

    public function getFragment(): Fragment
    {
        return new WhereClauseFragment($this->getCondition());
    }

    /**
     * Returns the "[NOT] EXISTS(...)" condition
     */
    public function getCondition(): Condition
    {
        $exists = new ExistsCondition($this->wrapAdditional(), $this->joinCondition, $this->alias);
        return $this->not ? new NotCondition($exists) : $exists;
    }

    /**
     * Sets the join condition between the base and the checked tables
     *
     * The Condition will be added to the WHERE clause of the query inside EXISTS()
     *
     * @return $this
     */
    public function joinOn(Condition $condition): self
    {
        $this->joinCondition = $condition;

        return $this;
    }

    /**
     * Sets the join condition based on a FOREIGN KEY constraint between the base and the checked tables
     *
     * The Condition will be added to the WHERE clause of the query inside EXISTS()
     *
     * @param string[] $keyColumns If there are several FOREIGN KEY constraints between the tables,
     *                             specify the columns on the child side that should be part of the key
     * @return $this
     */
    public function joinOnForeignKey(array $keyColumns = []): self
    {
        return $this->joinOn($this->createForeignKeyCondition($keyColumns));
    }

    /**
     * Sets the self-join condition based on a recursive FOREIGN KEY constraint
     *
     * The Condition will be added to the WHERE clause of the query inside EXISTS()
     *
     * @param bool $fromChild Whether the base table should be on the child side (default) of the join or the parent one
     * @param string[] $keyColumns In the unlikely event that there are several recursive FOREIGN KEY constraints
     *                             this specifies the columns on the child side that should be part of the key
     * @return $this
     */
    public function joinOnRecursiveForeignKey(bool $fromChild = true, array $keyColumns = []): self
    {
        return $this->joinOn($this->createForeignKeyCondition($keyColumns, $fromChild));
    }

    /**
     * Specifies that a NOT EXISTS() condition will be generated
     *
     * @return $this
     */
    public function not(): self
    {
        $this->not = true;

        return $this;
    }
}
