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

namespace sad_spirit\pg_gateway;

/**
 * Interface for complex fragment builders
 *
 * {@see Fragment}s themselves should usually be immutable, receiving all their dependencies in constructor,
 * thus a builder may help in managing complex dependencies.
 */
interface FragmentBuilder
{
    /**
     * Returns the built fragment
     */
    public function getFragment(): Fragment;
}
