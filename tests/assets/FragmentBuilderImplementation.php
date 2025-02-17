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

namespace sad_spirit\pg_gateway\tests\assets;

use sad_spirit\pg_gateway\Fragment;
use sad_spirit\pg_gateway\FragmentBuilder;

class FragmentBuilderImplementation implements FragmentBuilder
{
    public function __construct(private readonly Fragment $fragment)
    {
    }

    public function getFragment(): Fragment
    {
        return $this->fragment;
    }
}
