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
 * Interface for classes transferring query parameter values with their related Fragments/Conditions
 *
 * Objects implementing this interface are used to "bubble" all parameters up to the FragmentList and to ensure
 * that several parameters with the same name but different values do not appear anywhere.
 */
interface ParameterHolder
{
    /**
     * Returns the Fragment/Condition using the parameters
     *
     * @return KeyEquatable
     */
    public function getOwner(): KeyEquatable;

    /**
     * Returns query parameters
     *
     * @return array<string, mixed>
     */
    public function getParameters(): array;
}
