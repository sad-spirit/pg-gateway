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

namespace sad_spirit\pg_gateway\builders\proxies;

use sad_spirit\pg_gateway\{
    FragmentList,
    builders\FluentBuilder,
    builders\Proxy,
    exceptions\BadMethodCallException
};

/**
 * A trait for classes proxying calls to a FluentBuilder instance
 *
 * @psalm-require-implements Proxy
 * @template Owner of FluentBuilder
 * @since 0.4.0
 */
trait FluentBuilderWrapper
{
    /** @var Owner */
    private FluentBuilder $owner;

    /**
     * Prevents cloning, as the instance of FluentBuilder should be cloned rather than proxy
     */
    private function __clone()
    {
    }

    /**
     * Returns the fragment list built by a proxied FluentBuilder (including the fragment from the proxy class)
     */
    public function getFragment(): FragmentList
    {
        return $this->owner->getFragment();
    }

    /**
     * Returns the proxied builder, explicitly ending work with the proxy
     *
     * This is needed when the result of the fluent calls should be an instance of FluentBuilder, not Proxy
     * <code>
     *     $prototype = clone $builder->outputColumns()
     *          ->primaryKey()
     *          ->end();
     * </code>
     * or when calling methods of the proxied builder having the same names as those of the Proxy:
     * <code>
     *     $builder
     *          ->outputColumns()
     *              ->primaryKey()
     *              ->end()
     *          ->primaryKey(123);
     * </code>
     *
     * @return Owner
     */
    public function end(): FluentBuilder
    {
        return $this->owner;
    }

    /**
     * Delegates all non-existing methods to the proxied class
     */
    public function __call(string $name, array $arguments)
    {
        if (\method_exists($this->owner, $name)) {
            return \call_user_func_array([$this->owner, $name], $arguments);
        } elseif (\method_exists($this->owner, '__call')) {
            return $this->owner->__call($name, $arguments);
        }
        throw new BadMethodCallException("The method '{$name}' is not available in " . $this->owner::class);
    }
}
