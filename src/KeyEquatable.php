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

namespace sad_spirit\pg_gateway;

/**
 * Implementations of this interface are considered "equal" when building a query if their keys are equal
 *
 * As we'd prefer loading generated SQL from cache, rather than generating it each time when a query
 * is used, we need a means to generate cache key for a query without generating SQL itself.
 * That key will depend on some unique identifiers for query's parts, thus this interface.
 *
 * Keys are also used by FragmentList to discard duplicate fragments: those may appear when several Fragments
 * have the same Fragment they depend on, e.g. a CTE or a join to a related table
 *
 * Classes implementing this interface should either be immutable, receiving all their dependencies in constructor,
 * or should return null from <code>getKey()</code>.
 */
interface KeyEquatable
{
    /**
     * Returns a string that uniquely identifies this object based on its properties
     *
     * Returning null means that this fragment (and consequently the query using it) cannot be cached.
     * This is the case with e.g. ad-hoc queries using Closures.
     *
     * @return string|null
     */
    public function getKey(): ?string;
}
