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

namespace sad_spirit\pg_gateway\fragments\target_list;

use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    TargetElement,
    lists\TargetList
};
use sad_spirit\pg_gateway\{
    TableGateway,
    TableLocator,
    fragments\TargetListFragment,
};

/**
 * Replaces TargetList elements having "self.column_name" for expression with "self.*" shorthand
 *
 * This is usually a no-op for SELECT output list as that already contains "self.*" by default
 */
final class SelfColumnsShorthand extends TargetListFragment
{
    protected function modifyTargetList(TargetList $targetList): void
    {
        (new SelfColumnsNone())->modifyTargetList($targetList);

        $targetList[] = new TargetElement(new ColumnReference(TableGateway::ALIAS_SELF, '*'));
    }

    public function getKey(): ?string
    {
        return TableLocator::hash(self::class);
    }
}
