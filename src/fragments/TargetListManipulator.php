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

namespace sad_spirit\pg_gateway\fragments;

use sad_spirit\pg_gateway\{
    Fragment,
    FragmentBuilder,
    KeyEquatable
};
use sad_spirit\pg_builder\nodes\lists\TargetList;

/**
 * Modifies TargetList Node, which represents either a list of output expressions in SELECT or RETURNING clause
 * in DELETE / INSERT / UPDATE
 */
abstract class TargetListManipulator implements KeyEquatable, FragmentBuilder
{
    /**
     * Modifies an instance of TargetList that is attached to the query being processed
     *
     * This will be called from {@see SelectListFragment} or {@see ReturningClauseFragment}
     *
     * @param TargetList $targetList
     * @return void
     */
    abstract public function modifyTargetList(TargetList $targetList): void;

    /**
     * Returns the built fragment
     *
     * As with Conditions, implementing the FragmentBuilder interface allows passing an instance
     * of TargetListManipulator to a query method of TableGateway. As this returns an instance of SelectListFragment,
     * by default the output list of SELECT will be modified.
     *
     * @return Fragment
     */
    public function getFragment(): Fragment
    {
        return new SelectListFragment($this);
    }
}
