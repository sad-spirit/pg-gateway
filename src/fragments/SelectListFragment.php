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
    ParameterHolder,
    Parametrized,
    SelectFragment,
    exceptions\InvalidArgumentException,
    holders\EmptyParameterHolder
};
use sad_spirit\pg_builder\{
    Select,
    Statement
};

/**
 * Modifies the list of expressions returned by SELECT statement
 */
final readonly class SelectListFragment implements SelectFragment, Parametrized
{
    public function __construct(private TargetListManipulator $manipulator)
    {
    }

    public function applyTo(Statement $statement, bool $isCount = false): void
    {
        if (!$statement instanceof Select) {
            throw new InvalidArgumentException(\sprintf(
                "This fragment can only be applied to SELECT statements, instance of %s given",
                $statement::class
            ));
        }
        $this->manipulator->modifyTargetList($statement->list);
    }

    public function isUsedForCount(): bool
    {
        return false;
    }

    public function getPriority(): int
    {
        return Fragment::PRIORITY_LOWER;
    }

    public function getKey(): ?string
    {
        return null === ($key = $this->manipulator->getKey())
            ? null
            : 'select-list.' . $key;
    }

    public function getParameterHolder(): ParameterHolder
    {
        return $this->manipulator instanceof Parametrized
            ? $this->manipulator->getParameterHolder()
            : new EmptyParameterHolder();
    }
}
