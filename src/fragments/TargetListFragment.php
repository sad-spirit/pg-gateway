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
    Fragment,
    SelectFragment,
    exceptions\InvalidArgumentException
};
use sad_spirit\pg_builder\Select;
use sad_spirit\pg_builder\Statement;
use sad_spirit\pg_builder\nodes\lists\TargetList;

/**
 * Modifies the list of expressions returned by SELECT statement or by RETURNING clause of DELETE / INSERT / UPDATE
 */
abstract class TargetListFragment implements SelectFragment
{
    /**
     * Modifies an instance of TargetList that is attached to the query being processed
     */
    abstract protected function modifyTargetList(TargetList $targetList): void;

    public function applyTo(Statement $statement, bool $isCount = false): void
    {
        if ($statement instanceof Select) {
            $this->modifyTargetList($statement->list);
        } elseif (isset($statement->returning) && $statement->returning instanceof TargetList) {
            $this->modifyTargetList($statement->returning);
        } else {
            throw new InvalidArgumentException(\sprintf(
                "This fragment can only be applied to either SELECT statements or those having a RETURNING clause,"
                . " instance of %s given",
                $statement::class
            ));
        }
    }

    public function isUsedForCount(): bool
    {
        return false;
    }

    public function getPriority(): int
    {
        return Fragment::PRIORITY_HIGHER;
    }
}
