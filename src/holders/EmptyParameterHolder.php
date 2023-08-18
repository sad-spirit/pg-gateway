<?php

/*
 * This file is part of sad_spirit/pg_gateway package
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace sad_spirit\pg_gateway\holders;

use sad_spirit\pg_gateway\{
    KeyEquatable,
    ParameterHolder,
    exceptions\LogicException,
    fragments\ClosureFragment
};

/**
 * A "Null Object" implementation of ParameterHolder
 */
final class EmptyParameterHolder implements ParameterHolder
{
    public function getOwner(): KeyEquatable
    {
        return new ClosureFragment(function () {
            throw new LogicException('This function is not expected to be called');
        });
    }

    public function getParameters(): array
    {
        return [];
    }
}
