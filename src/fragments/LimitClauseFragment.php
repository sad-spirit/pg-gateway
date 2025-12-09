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

namespace sad_spirit\pg_gateway\fragments;

use sad_spirit\pg_gateway\{
    ParameterHolder,
    Parametrized,
    SelectFragment,
    exceptions\InvalidArgumentException,
    holders\EmptyParameterHolder,
    holders\SimpleParameterHolder
};
use sad_spirit\pg_builder\SelectCommon;
use sad_spirit\pg_builder\Statement;
use sad_spirit\pg_builder\nodes\expressions\NamedParameter;

/**
 * Adds a `LIMIT :limit` clause, possibly keeps a value for that parameter
 */
final readonly class LimitClauseFragment implements SelectFragment, Parametrized
{
    public function __construct(private ?int $limit = null)
    {
    }

    public function applyTo(Statement $statement, bool $isCount = false): void
    {
        if (!$statement instanceof SelectCommon) {
            throw new InvalidArgumentException(\sprintf(
                "LimitClauseFragment instances can only be applied to SELECT statements, %s given",
                $statement::class
            ));
        }
        $statement->limit = new NamedParameter('limit');
    }

    public function getParameterHolder(): ParameterHolder
    {
        return null === $this->limit
            ? new EmptyParameterHolder()
            : new SimpleParameterHolder($this, ['limit' => $this->limit]);
    }

    public function getPriority(): int
    {
        return self::PRIORITY_DEFAULT;
    }

    public function getKey(): ?string
    {
        return 'limit';
    }

    public function isUsedForCount(): bool
    {
        return false;
    }
}
