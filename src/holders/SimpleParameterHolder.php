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

namespace sad_spirit\pg_gateway\holders;

use sad_spirit\pg_gateway\KeyEquatable;
use sad_spirit\pg_gateway\ParameterHolder;

/**
 * "Scalar" parameter holder, for Fragments/Conditions that do not aggregate Parametrized dependencies
 */
final readonly class SimpleParameterHolder implements ParameterHolder
{
    public function __construct(private KeyEquatable $owner, private array $parameters)
    {
    }

    public function getOwner(): KeyEquatable
    {
        return $this->owner;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }
}
