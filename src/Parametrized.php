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
 * Interface for parts of a query that may contain named parameters
 *
 * This should be a means to pass parameters alongside query fragments, *values* of parameters should not affect
 * the query being built. The same query may e.g. be prepared and run with completely different parameter values.
 */
interface Parametrized
{
    /**
     * Returns values for named parameters
     *
     * @return ParameterHolder
     */
    public function getParameterHolder(): ParameterHolder;
}
