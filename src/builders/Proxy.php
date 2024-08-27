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

use sad_spirit\pg_gateway\Fragment;
use sad_spirit\pg_gateway\FragmentBuilder;

/**
 * Interface for fragment builders that proxy the calls to another Builder
 *
 * @since 0.4.0
 */
interface Proxy extends FragmentBuilder
{
    /**
     * Returns the fragment built by a non-proxied part
     *
     * The {@see getFragment()} method will return the fragment built by the proxied Builder
     *
     * @return Fragment
     */
    public function getOwnFragment(): Fragment;
}
