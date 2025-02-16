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
    Fragment,
    FragmentBuilder,
    FragmentList,
    TableDefinition,
    TableLocator,
    fragments\ParametrizedFragment
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
    /** @var Proxy[] */
    private array $proxies = [];

    final public function __construct(TableDefinition $definition, TableLocator $tableLocator)
    {
        $this->definition   = $definition;
        $this->tableLocator = $tableLocator;
        $this->list         = new FragmentList();
    }

    final public function __clone()
    {
        // A clone should contain all data of the prototype *at the given moment*, so we merge all proxy fragments
        // and remove proxies. Leaving is not an option, as they proxy the prototype rather than the clone.
        $this->list    = new FragmentList($this->list, $this->getProxyFragments());
        $this->proxies = [];
    }

    final public function getFragment(): FragmentList
    {
        return new FragmentList($this->list, $this->getProxyFragments());
    }

    /**
     * Adds a fragment to the list
     *
     * @return $this
     */
    final public function add(Fragment|FragmentBuilder $fragment): self
    {
        $this->list->add($fragment);

        return $this;
    }

    /**
     * Adds a fragment to the list, wrapping it in a decorator that keeps parameter values
     *
     * @param array<string, mixed> $parameters
     * @return $this
     */
    final public function addWithParameters(Fragment $fragment, array $parameters): self
    {
        $this->list->add(new ParametrizedFragment($fragment, $parameters));

        return $this;
    }

    /**
     * Adds a proxy to the list
     *
     * The method is not public as the proxies are not meant to be added directly. They also should always wrap $this,
     * thus a public method will need relevant checks.
     */
    final protected function addProxy(Proxy $proxy): void
    {
        $this->proxies[] = $proxy;
    }

    /**
     * Returns a list of fragments generated by proxies
     */
    private function getProxyFragments(): FragmentList
    {
        return new FragmentList(...\array_map(fn (Proxy $proxy): Fragment => $proxy->getOwnFragment(), $this->proxies));
    }
}
