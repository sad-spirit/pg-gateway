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

use sad_spirit\pg_gateway\{
    KeyEquatable,
    ParameterHolder,
    Parametrized
};

/**
 * Factory for ParameterHolder implementations
 */
class ParameterHolderFactory
{
    /**
     * Creates an implementation of ParameterHolder based on arguments that actually are implementations of Parametrized
     */
    public static function create(?KeyEquatable ...$maybeParametrized): ParameterHolder
    {
        $holders = \array_filter(\array_map(
            fn(?KeyEquatable $item) => $item instanceof Parametrized ? $item->getParameterHolder() : null,
            $maybeParametrized
        ));

        return match (\count($holders)) {
            0 => new EmptyParameterHolder(),
            1 => \reset($holders),
            default => new RecursiveParameterHolder(...$holders),
        };
    }
}
