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

use sad_spirit\pg_gateway\Condition;
use sad_spirit\pg_gateway\Fragment;
use sad_spirit\pg_gateway\fragments\target_list\SubqueryAppender;

/**
 * Builder for SubqueryAppender
 */
class ScalarSubqueryBuilder extends AdditionalSelectBuilder
{
    private ?Condition $joinCondition = null;
    private ?string $columnAlias = null;
    private bool $asArray = false;
    private bool $returningRow = false;

    public function getFragment(): Fragment
    {
        return new SubqueryAppender(
            $this->wrapAdditional(),
            $this->joinCondition,
            $this->alias,
            $this->columnAlias,
            $this->returningRow,
            $this->asArray
        );
    }

    /**
     * Sets the join condition between the base table and the subquery table
     *
     * The Condition will be added to the WHERE clause of subquery table
     *
     * @return $this
     */
    public function joinOn(Condition $condition): self
    {
        $this->joinCondition = $condition;

        return $this;
    }

    /**
     * Sets the join condition based on a FOREIGN KEY constraint between the base table and the subquery table
     *
     * The Condition will be added to the WHERE clause of subquery table
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
     * The Condition will be added to the WHERE clause of subquery table
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
     * Another name for alias() method, to prevent confusion with columnAlias()
     *
     * @return $this
     */
    public function tableAlias(string $alias): self
    {
        return $this->alias($alias);
    }

    /**
     * Sets the alias for subquery expression in the TargetList, `(SELECT ...) as $alias`
     *
     * @return $this
     */
    public function columnAlias(string $alias): self
    {
        $this->columnAlias = $alias;

        return $this;
    }

    /**
     * Wraps the subquery in an ARRAY() constructor, allowing to return more than one row
     *
     * @link https://github.com/sad-spirit/pg-gateway/issues/1
     * @since 0.10.0
     * @return $this
     */
    public function asArray(): self
    {
        $this->asArray = true;

        return $this;
    }

    /**
     * Replaces the column list of the SELECT by a ROW() constructor with these columns
     *
     * This allows returning more than one column from a (formerly) scalar subquery
     *
     * @since 0.10.0
     * @return $this
     */
    public function returningRow(): self
    {
        $this->returningRow = true;

        return $this;
    }
}
