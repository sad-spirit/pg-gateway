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

namespace sad_spirit\pg_gateway\gateways;

use sad_spirit\pg_builder\{
    Delete,
    Select
};
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    FunctionCall,
    QualifiedName,
    Star,
    expressions\InExpression,
    expressions\NamedParameter,
    expressions\RowExpression,
    expressions\TypecastExpression,
    lists\FunctionArgumentList,
    lists\TargetList,
    range\FunctionCall as RangeFunctionCall
};
use sad_spirit\pg_gateway\{
    Condition,
    FragmentList,
    TableGateway,
    exceptions\InvalidArgumentException,
    fragments\ClosureFragment,
    fragments\WhereClauseFragment
};
use sad_spirit\pg_gateway\conditions\column\{
    NotAllCondition,
    OperatorCondition
};

/**
 * Table gateway implementation for tables with a multi-column primary key
 *
 * We assume that such a table is generally used for defining an M:N relationship and provide a method that allows
 * to replace all records related to a key from one side of relationship
 */
class CompositePrimaryKeyTableGateway extends PrimaryKeyTableGateway
{
    /**
     * "Upsert"s the given $rows adding the given $keyPart, deletes rows with that $keyPart that were not in $rows
     *
     * This can e.g. be used for replacing items in users basket:
     * <code>
     * $basketGateway->replaceRelated(
     *     ['user_id' => $user->id],
     *     [
     *         ['item_id' => $itemOne->id, 'amount' => $itemOne->amount, ...]
     *         ...
     *     ]
     * );
     * </code>
     * after that call the basket for the given user will contain only the items from $rows
     *
     * @param array<string, mixed> $primaryKeyPart Part of primary key for rows, ['field name' => 'field value', ...].
     * @param iterable<array>      $rows           Other fields for rows being stored
     * @return list<array<string, mixed>> Primary keys of stored rows
     */
    public function replaceRelated(array $primaryKeyPart, iterable $rows): array
    {
        if ([] === $primaryKeyPart) {
            throw new InvalidArgumentException("Primary key part should contain a value for at least one column");
        }
        $pkeyColumns = $this->getAdditionalPrimaryKeyColumns($primaryKeyPart);

        $primaryKeys = [];
        foreach ($rows as $row) {
            $primaryKeys[] = $this->upsert(\array_merge($row, $primaryKeyPart));
        }

        if (1 === \count($pkeyColumns)) {
            $this->deleteRelatedSingleColumn($primaryKeyPart, \reset($pkeyColumns), $primaryKeys);
        } else {
            $this->deleteRelatedMultipleColumns($primaryKeyPart, $pkeyColumns, $primaryKeys);
        }

        return $primaryKeys;
    }

    /**
     * Returns primary key columns not appearing in $keyPart
     *
     * @param non-empty-array<string, mixed> $keyPart
     * @return string[]
     */
    private function getAdditionalPrimaryKeyColumns(array $keyPart): array
    {
        $pkeyColumns = \array_flip($this->definition->getPrimaryKey()->getNames());
        foreach (\array_keys($keyPart) as $column) {
            if (!isset($pkeyColumns[$column])) {
                throw new InvalidArgumentException("Column '$column' is not a part of primary key");
            } else {
                unset($pkeyColumns[$column]);
            }
        }
        if ([] === $pkeyColumns) {
            throw new InvalidArgumentException(
                "\$keyPart should contain only a subset of primary key columns"
            );
        }
        return \array_keys($pkeyColumns);
    }

    /**
     * Deletes the rows having the same $keyPart, when primary key has exactly one extra column
     *
     * The method builds a query similar to
     * <pre>
     * delete from table_name
     * where keyPart1 = :keyPart1 and ... and keyPartN = :keyPartN and
     *       otherPart <> all(:otherPart)
     * </pre>
     * and runs it using primary keys returned by upsert(). This essentially removes all related records,
     * whose keys were not upsert()ed this time.
     *
     * @param non-empty-array<string, mixed> $keyPart     Part of primary key for rows,
     *                                                    ['column name' => 'column value', ...]
     * @param string                         $otherPart   Name of the remaining primary key column
     * @param list<array<string, mixed>>     $primaryKeys Primary keys for stored rows (as returned by upsert())
     */
    protected function deleteRelatedSingleColumn(array $keyPart, string $otherPart, array $primaryKeys): void
    {
        $native = $this->createDeleteStatement(new FragmentList(new WhereClauseFragment(Condition::and(
            new NotAllCondition(
                $this->definition->getColumns()->get($otherPart),
                $this->tableLocator->getTypeConverterFactory()
            ),
            ...\array_map(
                fn (string $column): OperatorCondition => new OperatorCondition(
                    $this->definition->getColumns()->get($column),
                    $this->tableLocator->getTypeConverterFactory(),
                    '='
                ),
                \array_keys($keyPart)
            )
        ))));

        $otherPartValues = \array_map(fn ($value): mixed => $value[$otherPart], $primaryKeys);

        $native->executeParams($this->getConnection(), \array_merge([$otherPart => $otherPartValues], $keyPart));
    }

    /**
     * Deletes the rows having the same $keyPart, when primary key has more than one extra columns
     *
     * The query is not cached as we are adding a closure-based fragment
     *
     * @param non-empty-array<string, mixed> $keyPart     Part of primary key for rows,
     *                                                    ['column name' => 'column value', ...]
     * @param string[]                       $otherParts  Names of the remaining primary key columns
     * @param list<array<string, mixed>>     $primaryKeys Primary keys for stored rows (as returned by upsert())
     */
    protected function deleteRelatedMultipleColumns(array $keyPart, array $otherParts, array $primaryKeys): void
    {
        $fragments = new FragmentList(new ClosureFragment(
            function (Delete $delete) use ($otherParts): void {
                $unnestArgs = new FunctionArgumentList();
                foreach ($otherParts as $column) {
                    $typeName = $this->tableLocator->createTypeNameNodeForOID(
                        $this->definition->getColumns()->get($column)->getTypeOID()
                    );
                    $typeName->bounds = [-1];

                    $unnestArgs[] = new TypecastExpression(new NamedParameter($column), $typeName);
                }

                $select         = new Select(new TargetList([new Star()]));
                $select->from[] = new RangeFunctionCall(new FunctionCall(new QualifiedName('unnest'), $unnestArgs));

                $delete->where->and(new InExpression(
                    new RowExpression(\array_map(
                        fn ($value): ColumnReference => new ColumnReference(TableGateway::ALIAS_SELF, $value),
                        $otherParts
                    )),
                    $select,
                    true
                ));
            }
        ));

        $fragments->add(new WhereClauseFragment(Condition::and(...\array_map(
            fn (string $column): OperatorCondition => new OperatorCondition(
                $this->definition->getColumns()->get($column),
                $this->tableLocator->getTypeConverterFactory(),
                '='
            ),
            \array_keys($keyPart)
        ))));

        $native = $this->createDeleteStatement($fragments);

        $otherPartsValues = [];
        foreach ($otherParts as $column) {
            $otherPartsValues[$column] = \array_map(
                fn ($value): mixed => $value[$column],
                $primaryKeys
            );
        }

        $native->executeParams($this->getConnection(), \array_merge($otherPartsValues, $keyPart));
    }
}
