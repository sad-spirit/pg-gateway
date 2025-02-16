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

namespace sad_spirit\pg_gateway\builders\proxies;

use sad_spirit\pg_gateway\{
    Fragment,
    SelectProxy
};
use sad_spirit\pg_gateway\builders\{
    FluentBuilder,
    Proxy,
    WithClauseBuilder
};

/**
 * WithClauseBuilder subclass that proxies the methods of a FluentBuilder instance
 *
 * @template Owner of FluentBuilder
 * @mixin Owner
 * @since 0.4.0
 */
class WithClauseBuilderProxy extends WithClauseBuilder implements Proxy
{
    /** @template-use FluentBuilderWrapper<Owner> */
    use FluentBuilderWrapper;

    /**
     * @param Owner $owner
     */
    public function __construct(FluentBuilder $owner, SelectProxy $select, string $alias)
    {
        parent::__construct($select, $alias);
        $this->owner = $owner;
    }

    public function getOwnFragment(): Fragment
    {
        return parent::getFragment();
    }
}
