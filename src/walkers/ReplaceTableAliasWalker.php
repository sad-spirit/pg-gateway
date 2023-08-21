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

namespace sad_spirit\pg_gateway\walkers;

use sad_spirit\pg_builder\BlankWalker;
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    Identifier,
    range\FromElement
};

/**
 * Replaces a table alias throughout the query with another one
 *
 * When creating a join between two TableGateways / SelectProxies we need to replace the default "self" alias
 * of the second gateway's table and "joined" alias in join condition
 */
class ReplaceTableAliasWalker extends BlankWalker
{
    private string $oldAlias;
    private string $newAlias;

    public function __construct(string $oldAlias, string $newAlias)
    {
        $this->oldAlias = $oldAlias;
        $this->newAlias = $newAlias;
    }

    public function walkColumnReference(ColumnReference $node): void
    {
        if (
            null !== $node->relation
            && $this->oldAlias === $node->relation->value
            && null !== ($parent = $node->getParentNode())
            // If not null, then probably a field of a real table named "self" (e.g. "foo.self.bar") is accessed
            && null === $node->schema
        ) {
            $parent->replaceChild($node, new ColumnReference(
                new Identifier($this->newAlias),
                clone $node->column
            ));
        }
    }

    public function walkRangeItemAliases(FromElement $rangeItem): void
    {
        if (
            null !== $rangeItem->tableAlias
            && $this->oldAlias === $rangeItem->tableAlias->value
        ) {
            $rangeItem->setTableAlias(new Identifier($this->newAlias));
        }
    }
}
