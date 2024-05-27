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

namespace sad_spirit\pg_gateway\gateways;

use sad_spirit\pg_gateway\{
    conditions\ParametrizedCondition,
    FragmentList,
    PrimaryKeyAccess,
    SelectProxy,
    TableSelect,
    conditions\PrimaryKeyCondition,
    fragments\SetClauseFragment
};
use sad_spirit\pg_builder\Insert;
use sad_spirit\pg_builder\NativeStatement;
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    Identifier,
    IndexElement,
    IndexParameters,
    OnConflictClause,
    SetTargetElement,
    SingleSetClause,
    TargetElement,
    lists\SetClauseList,
    range\InsertTarget,
};
use sad_spirit\pg_wrapper\Result;

/**
 * Table gateway implementation for tables that have a primary key defined
 */
class PrimaryKeyTableGateway extends GenericTableGateway implements PrimaryKeyAccess
{
    public function deleteByPrimaryKey($primaryKey): Result
    {
        $list = new FragmentList($this->primaryKey($primaryKey));

        return $this->createDeleteStatement($list)
            ->executeParams($this->getConnection(), $list->getParameters());
    }

    public function selectByPrimaryKey($primaryKey): SelectProxy
    {
        $condition = new PrimaryKeyCondition($this->getPrimaryKey(), $this->tableLocator->getTypeConverterFactory());

        return new TableSelect($this->tableLocator, $this, $condition, $condition->normalizeValue($primaryKey));
    }

    public function updateByPrimaryKey($primaryKey, array $set): Result
    {
        $list = new FragmentList(
            new SetClauseFragment($this->getColumns(), $this->tableLocator, $set),
            $this->primaryKey($primaryKey)
        );

        return $this->createUpdateStatement($list)
            ->executeParams($this->getConnection(), $list->getParameters());
    }

    /**
     * Creates a condition on a primary key, can be used to combine with other Fragments
     *
     * @param mixed $value
     * @return ParametrizedCondition
     */
    public function primaryKey($value): ParametrizedCondition
    {
        $condition = new PrimaryKeyCondition($this->getPrimaryKey(), $this->tableLocator->getTypeConverterFactory());
        return new ParametrizedCondition($condition, $condition->normalizeValue($value));
    }

    public function upsert(array $values): array
    {
        $valuesClause = new SetClauseFragment($this->getColumns(), $this->tableLocator, $values);
        $native       = $this->createUpsertStatement(new FragmentList($valuesClause));
        if ([] === $native->getParameterTypes()) {
            return $this->getConnection()->execute($native->getSql())->current();
        } else {
            return $native->executeParams(
                $this->getConnection(),
                $valuesClause->getParameterHolder()->getParameters()
            )->current();
        }
    }

    /**
     * Generates an "UPSERT" (INSERT ... ON CONFLICT DO UPDATE ...) statement using given fragments
     *
     * @param FragmentList $fragments
     * @return NativeStatement
     */
    public function createUpsertStatement(FragmentList $fragments): NativeStatement
    {
        return $this->tableLocator->createNativeStatementUsingCache(
            function () use ($fragments): Insert {
                $insert = $this->createBaseUpsertAST();
                $fragments->applyTo($insert);
                return $insert;
            },
            $this->generateStatementKey(self::STATEMENT_UPSERT, $fragments)
        );
    }

    /**
     * Generates base AST for "INSERT ... ON CONFLICT DO UPDATE ..." statement executed by upsert()
     *
     * @return Insert
     */
    protected function createBaseUpsertAST(): Insert
    {
        $insert = $this->tableLocator->getStatementFactory()->insert(new InsertTarget(
            $this->getName()->createNode(),
            new Identifier(self::ALIAS_SELF)
        ));

        $target            = new IndexParameters();
        $set               = new SetClauseList();
        $primaryKeyColumns = $this->getPrimaryKey()->getNames();
        $nonPrimaryKey     = \array_diff($this->getColumns()->getNames(), $primaryKeyColumns);

        foreach ($primaryKeyColumns as $pk) {
            $target[]            = new IndexElement(new Identifier($pk));
            $insert->returning[] = new TargetElement(new ColumnReference($pk));
        }

        // "DO INSTEAD NOTHING" clause leads to no rows returned by RETURNING clause, thus a fake update
        if ([] === $nonPrimaryKey) {
            $pkey  = reset($primaryKeyColumns);
            $set[] = new SingleSetClause(new SetTargetElement($pkey), new ColumnReference('excluded', $pkey));
        } else {
            foreach ($nonPrimaryKey as $column) {
                $set[] = new SingleSetClause(
                    new SetTargetElement($column),
                    new ColumnReference('excluded', $column)
                );
            }
        }

        $insert->onConflict = new OnConflictClause(OnConflictClause::UPDATE, $target, $set);

        return $insert;
    }
}
