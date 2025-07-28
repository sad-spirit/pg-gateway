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

use sad_spirit\pg_gateway\{
    FragmentList,
    PrimaryKeyAccess,
    SelectProxy,
    StatementType,
    builders\PrimaryKeyBuilder,
    fragments\SetClauseFragment
};
use sad_spirit\pg_builder\{
    Insert,
    NativeStatement,
    enums\OnConflictAction
};
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
    use PrimaryKeyBuilder;

    public function deleteByPrimaryKey(mixed $primaryKey): Result
    {
        $list = new FragmentList($this->createPrimaryKey($primaryKey));

        return $this->createDeleteStatement($list)
            ->executeParams($this->getConnection(), $list->getParameters());
    }

    public function selectByPrimaryKey(mixed $primaryKey): SelectProxy
    {
        return $this->select($this->createPrimaryKey($primaryKey));
    }

    public function updateByPrimaryKey(mixed $primaryKey, array $set): Result
    {
        $list = new FragmentList(
            new SetClauseFragment($this->definition->getColumns(), $this->tableLocator, $set),
            $this->createPrimaryKey($primaryKey)
        );

        return $this->createUpdateStatement($list)
            ->executeParams($this->getConnection(), $list->getParameters());
    }

    /**
     * {@inheritDoc}
     *
     * @psalm-suppress MixedReturnTypeCoercion
     */
    public function upsert(array $values): array
    {
        $valuesClause = new SetClauseFragment($this->definition->getColumns(), $this->tableLocator, $values);
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
     */
    public function createUpsertStatement(FragmentList $fragments): NativeStatement
    {
        return $this->tableLocator->createNativeStatementUsingCache(
            function () use ($fragments): Insert {
                $insert = $this->createBaseUpsertAST();
                $fragments->applyTo($insert);
                return $insert;
            },
            $this->generateStatementKey(StatementType::Upsert, $fragments)
        );
    }

    /**
     * Generates base AST for "INSERT ... ON CONFLICT DO UPDATE ..." statement executed by upsert()
     */
    protected function createBaseUpsertAST(): Insert
    {
        $insert = $this->tableLocator->getStatementFactory()->insert(new InsertTarget(
            $this->definition->getName()->createNode(),
            new Identifier(self::ALIAS_SELF)
        ));

        $target            = new IndexParameters();
        $set               = new SetClauseList();
        /** @var non-empty-array<string> $primaryKeyColumns */
        $primaryKeyColumns = $this->definition->getPrimaryKey()->getNames();
        $nonPrimaryKey     = \array_diff($this->definition->getColumns()->getNames(), $primaryKeyColumns);

        foreach ($primaryKeyColumns as $pk) {
            $target[]            = new IndexElement(new Identifier($pk));
            $insert->returning[] = new TargetElement(new ColumnReference($pk));
        }

        // "DO INSTEAD NOTHING" clause leads to no rows returned by RETURNING clause, thus a fake update
        if ([] === $nonPrimaryKey) {
            $pkey  = \reset($primaryKeyColumns);
            $set[] = new SingleSetClause(new SetTargetElement($pkey), new ColumnReference('excluded', $pkey));
        } else {
            foreach ($nonPrimaryKey as $column) {
                $set[] = new SingleSetClause(
                    new SetTargetElement($column),
                    new ColumnReference('excluded', $column)
                );
            }
        }

        $insert->onConflict = new OnConflictClause(OnConflictAction::UPDATE, $target, $set);

        return $insert;
    }
}
