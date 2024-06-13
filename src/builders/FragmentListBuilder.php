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
    FragmentList,
    TableDefinition,
    TableLocator
};

/**
 * Base class for fluent fragment builders
 *
 * @since 0.2.0
 */
abstract class FragmentListBuilder implements FragmentBuilder
{
    protected TableDefinition $definition;
    protected TableLocator $tableLocator;
    private FragmentList $list;

    final public function __construct(TableDefinition $definition, TableLocator $tableLocator)
    {
        $this->definition   = $definition;
        $this->tableLocator = $tableLocator;
        $this->list         = new FragmentList();
    }

    final public function getFragment(): FragmentList
    {
        return $this->list;
    }

    /**
     * Adds a fragment to the list
     *
     * @param Fragment|FragmentBuilder $fragment
     * @return $this
     */
    final public function add(object $fragment): self
    {
        $this->list->add($fragment);

        return $this;
    }
}
