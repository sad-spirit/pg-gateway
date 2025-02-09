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

use sad_spirit\pg_gateway\{
    Fragment,
    FragmentBuilder,
    SelectProxy
};
use sad_spirit\pg_gateway\fragments\with\SelectProxyFragment;
use sad_spirit\pg_builder\nodes\Identifier;
use sad_spirit\pg_builder\nodes\lists\IdentifierList;

/**
 * Builder for WithClauseFragment
 *
 * Currently, this only builds SelectProxyFragment as it has far more parameters
 *
 * @since 0.2.0
 */
class WithClauseBuilder implements FragmentBuilder
{
    private readonly Identifier $alias;
    private ?IdentifierList $columnAliases = null;
    private ?bool $materialized = null;
    private bool $recursive = false;
    private int $priority = Fragment::PRIORITY_DEFAULT;

    public function __construct(private readonly SelectProxy $select, string $alias)
    {
        $this->alias = new Identifier($alias);
    }

    public function getFragment(): Fragment
    {
        return new SelectProxyFragment(
            $this->select,
            $this->alias,
            $this->columnAliases,
            $this->materialized,
            $this->recursive,
            $this->priority
        );
    }

    /**
     * Sets column aliases for the Common Table Expression
     *
     * @param array<string|Identifier> $aliases
     * @return $this
     */
    public function columnAliases(array $aliases): self
    {
        $this->columnAliases = new IdentifierList($aliases);

        return $this;
    }

    /**
     * Sets the MATERIALIZED option for the Common Table Expression
     *
     * @return $this
     */
    public function materialized(): self
    {
        $this->materialized = true;

        return $this;
    }

    /**
     * Sets the NOT MATERIALIZED option for the Common Table Expression
     *
     * @return $this
     */
    public function notMaterialized(): self
    {
        $this->materialized = false;

        return $this;
    }

    /**
     * Enables the RECURSIVE option for the WITH clause
     *
     * @return $this
     */
    public function recursive(): self
    {
        $this->recursive = true;

        return $this;
    }

    /**
     * Sets the priority for the fragment
     *
     * Order may be important for the CTEs in the WITH clause without the RECURSIVE option, see
     * https://www.postgresql.org/docs/current/sql-select.html#SQL-WITH
     * > Without RECURSIVE, WITH queries can only reference sibling WITH queries that are earlier in the WITH list.
     *
     * @return $this
     */
    public function priority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }
}
