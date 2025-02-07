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
    exceptions\InvalidArgumentException,
    holders\EmptyParameterHolder
};
use sad_spirit\pg_builder\Statement;

/**
 * Changes the RETURNING clause of DELETE / INSERT / UPDATE
 */
final readonly class ReturningClauseFragment implements Fragment, Parametrized
{
    public function __construct(private TargetListManipulator $manipulator)
    {
    }

    public function applyTo(Statement $statement): void
    {
        if (!isset($statement->returning)) {
            throw new InvalidArgumentException(\sprintf(
                "This fragment can only be applied to statements having a RETURNING clause, instance of %s given",
                $statement::class
            ));
        }
        $this->manipulator->modifyTargetList($statement->returning);
    }

    public function getPriority(): int
    {
        return Fragment::PRIORITY_LOWER;
    }

    public function getKey(): ?string
    {
        return null === ($key = $this->manipulator->getKey())
            ? null
            : 'returning.' . $key;
    }

    public function getParameterHolder(): ParameterHolder
    {
        return $this->manipulator instanceof Parametrized
            ? $this->manipulator->getParameterHolder()
            : new EmptyParameterHolder();
    }
}
