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

    public function getFragment(): Fragment
    {
        return new SubqueryAppender($this->additional, $this->joinCondition, $this->alias, $this->columnAlias);
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
     * Another name for {@see alias()} method, to prevent confusion with {@see columnAlias()}
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
}
