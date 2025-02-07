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

namespace sad_spirit\pg_gateway\conditions;

use sad_spirit\pg_gateway\{
    Condition,
    Fragment,
    TableGateway,
    TableLocator,
    exceptions\InvalidArgumentException,
    exceptions\UnexpectedValueException,
    fragments\WhereClauseFragment,
    metadata\PrimaryKey
};
use sad_spirit\pg_builder\converters\TypeNameNodeHandler;
use sad_spirit\pg_builder\enums\LogicalOperator;
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    ScalarExpression,
    expressions\LogicalExpression,
    expressions\NamedParameter,
    expressions\OperatorExpression,
    expressions\TypecastExpression
};

/**
 * A condition for finding a table row by its primary key
 */
final class PrimaryKeyCondition extends Condition
{
    private PrimaryKey $primaryKey;
    private TypeNameNodeHandler $converterFactory;

    public function __construct(PrimaryKey $primaryKey, TypeNameNodeHandler $converterFactory)
    {
        // Sanity check: primary key actually is defined
        if (0 === \count($primaryKey)) {
            throw new UnexpectedValueException("No columns in table's primary key");
        }
        $this->primaryKey = $primaryKey;
        $this->converterFactory = $converterFactory;
    }

    public function getFragment(): Fragment
    {
        return new WhereClauseFragment($this, Fragment::PRIORITY_HIGHEST);
    }

    /**
     * Possibly converts the given PK value to an array and checks that array keys are corresponding to PK columns
     *
     * @param mixed $value Either an array ['primary key column' => value, ...] or a value for a
     *                     single-column primary key
     * @return array Array of the format ['primary key column' => value, ...]
     */
    public function normalizeValue($value): array
    {
        $columns = $this->primaryKey->getNames();

        if (!\is_array($value)) {
            if (1 === \count($columns)) {
                return [$columns[0] => $value];
            } else {
                throw new InvalidArgumentException(\sprintf(
                    "Expecting an array for a composite primary key value, %s given",
                    \is_object($value) ? 'object(' . \get_class($value) . ')' : \gettype($value)
                ));
            }
        }

        foreach ($columns as $column) {
            if (!\array_key_exists($column, $value)) {
                throw new InvalidArgumentException("Primary key column '$column' not found in array");
            }
        }
        if (\count($value) > \count($columns)) {
            $unknown = \array_diff(\array_keys($value), $columns);
            throw new InvalidArgumentException(
                "Indexes '" . \implode("', '", $unknown)
                . "' in array do not correspond to primary key columns"
            );
        }

        return $value;
    }

    protected function generateExpressionImpl(): ScalarExpression
    {
        $expression = new LogicalExpression([], LogicalOperator::AND);
        foreach ($this->primaryKey as $column) {
            $expression[] = new OperatorExpression(
                '=',
                new ColumnReference(TableGateway::ALIAS_SELF, $column->getName()),
                new TypecastExpression(
                    new NamedParameter($column->getName()),
                    $this->converterFactory->createTypeNameNodeForOID($column->getTypeOID())
                )
            );
        }
        return $expression;
    }

    public function getKey(): ?string
    {
        return TableLocator::hash([self::class, $this->primaryKey->getAll()]);
    }
}
