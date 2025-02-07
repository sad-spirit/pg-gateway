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
    Condition,
    TableLocator,
    fragments\TargetListManipulator
};
use sad_spirit\pg_builder\nodes\{
    Identifier,
    TargetElement,
    lists\TargetList
};

/**
 * Adds an expression created by Condition to the TargetList
 */
class ConditionAppender extends TargetListManipulator
{
    public function __construct(private readonly Condition $condition, private readonly ?string $alias = null)
    {
    }

    public function modifyTargetList(TargetList $targetList): void
    {
        $targetList[] = new TargetElement(
            $this->condition->generateExpression(),
            null === $this->alias ? null : new Identifier($this->alias)
        );
    }

    public function getKey(): ?string
    {
        $conditionKey = $this->condition->getKey();
        $aliasKey     = null === $this->alias ? '' : '.' . TableLocator::hash($this->alias);

        return null === $conditionKey
            ? null
            : 'output.' . $conditionKey . $aliasKey;
    }
}
