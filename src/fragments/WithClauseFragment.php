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

use sad_spirit\pg_gateway\Fragment;
use sad_spirit\pg_gateway\Parametrized;
use sad_spirit\pg_builder\Statement;
use sad_spirit\pg_builder\nodes\WithClause;

/**
 * Adds Common Table Expressions to the WITH clause
 *
 * @since 0.2.0
 */
abstract class WithClauseFragment implements Fragment, Parametrized
{
    use VariablePriority;

    public function __construct(int $priority = self::PRIORITY_DEFAULT)
    {
        $this->setPriority($priority);
    }

    final public function applyTo(Statement $statement): void
    {
        $statement->with->merge(clone $this->createWithClause($statement));
    }

    /**
     * Creates a WithClause object that will be merged into Statement
     *
     * We are creating a WithClause rather than a CommonTableExpression as $recursive property belongs to the former
     *
     * @param Statement $statement
     * @return WithClause
     */
    abstract protected function createWithClause(Statement $statement): WithClause;
}
