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

namespace sad_spirit\pg_gateway\fragments;

use sad_spirit\pg_gateway\Fragment;

/**
 * Base class for fragments that define a custom applyTo() method and can be cached, unlike ClosureFragment
 *
 * @since 0.2.0
 */
abstract class CustomFragment implements Fragment
{
    use VariablePriority;

    public function __construct(private readonly ?string $key, int $priority = Fragment::PRIORITY_DEFAULT)
    {
        $this->setPriority($priority);
    }

    public function getKey(): ?string
    {
        return $this->key;
    }
}
