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

namespace sad_spirit\pg_gateway\tests\assets;

use sad_spirit\pg_gateway\fragments\TargetListManipulator;
use sad_spirit\pg_builder\nodes\lists\TargetList;
use sad_spirit\pg_builder\nodes\TargetElement;

/**
 * An implementation of TargetListManipulator which replaces everything in the list with one given item
 */
class TargetListManipulatorImplementation extends TargetListManipulator
{
    public function __construct(private readonly TargetElement $item, private readonly ?string $key = null)
    {
    }

    public function modifyTargetList(TargetList $targetList): void
    {
        $targetList->replace([$this->item]);
    }

    public function getKey(): ?string
    {
        return $this->key;
    }
}
