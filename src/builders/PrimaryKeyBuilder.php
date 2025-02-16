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

namespace sad_spirit\pg_gateway\builders;

use sad_spirit\pg_gateway\{
    TableDefinition,
    TableLocator,
    conditions\ParametrizedCondition,
    conditions\PrimaryKeyCondition
};

/**
 * A trait for creating a PrimaryKeyCondition
 *
 * @since 0.2.0
 */
trait PrimaryKeyBuilder
{
    protected TableDefinition $definition;
    protected TableLocator $tableLocator;

    /**
     * Creates a condition on a primary key, can be used to combine with other Fragments
     */
    public function createPrimaryKey(mixed $value): ParametrizedCondition
    {
        $condition = new PrimaryKeyCondition(
            $this->definition->getPrimaryKey(),
            $this->tableLocator->getTypeConverterFactory()
        );
        return new ParametrizedCondition($condition, $condition->normalizeValue($value));
    }
}
