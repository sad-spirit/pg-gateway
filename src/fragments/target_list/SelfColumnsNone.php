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

use sad_spirit\pg_gateway\{
    TableGateway,
    TableLocator,
    fragments\TargetListManipulator
};
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    TargetElement,
    lists\TargetList
};

/**
 * Removes TargetList elements having "self.column_name" for expression
 *
 * This is usually a no-op for RETURNING clauses since those are empty by default
 */
class SelfColumnsNone extends TargetListManipulator
{
    public function modifyTargetList(TargetList $targetList): void
    {
        /** @var TargetElement $item */
        foreach ($targetList as $index => $item) {
            if (
                $item->expression instanceof ColumnReference
                && null === $item->expression->schema
                && null !== $item->expression->relation
                && TableGateway::ALIAS_SELF === $item->expression->relation->value
            ) {
                unset($targetList[$index]);
            }
        }
    }

    public function getKey(): ?string
    {
        return TableLocator::hash(\get_class($this));
    }
}
