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
    Fragment,
    FragmentList,
    PrimaryKeyAccess,
    SelectProxy,
    StatementType,
    builders\PrimaryKeyBuilder,
    exceptions\InvalidArgumentException,
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

    /** Whether to use ON CONFLICT ... SELECT construct available in Postgres 19+ */
    private ?bool $onConflictSelect = null;

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
                $insert = $this->tableLocator->getStatementFactory()->insert(new InsertTarget(
                    $this->definition->getName()->createNode(),
                    new Identifier(self::ALIAS_SELF)
                ));
                $this->addOnConflictAndReturningClauses($insert, $fragments);
                $fragments->applyTo($insert);
                return $insert;
            },
            $this->generateStatementKey(StatementType::Upsert, $fragments)
        );
    }

    private function addOnConflictAndReturningClauses(Insert $insert, FragmentList $fragments): void
    {
        $setFragments = $fragments->filter(
            static fn (Fragment $fragment): bool => $fragment instanceof SetClauseFragment
        )
            ->getSortedFragments();

        if ([] === $setFragments) {
            throw new InvalidArgumentException('Cannot add an ON CONFLICT clause without a list of affected columns');
        }

        // Even if we have multiple SetClauseFragment instances, the last one will overwrite everything in the query
        /** @var SetClauseFragment $fragment */
        $fragment          = \array_pop($setFragments);
        /** @var non-empty-array<string> $primaryKeyColumns */
        $primaryKeyColumns = $this->definition->getPrimaryKey()->getNames();
        $nonPrimaryKey     = \array_diff($fragment->getColumnNames(), $primaryKeyColumns);
        $target            = new IndexParameters();

        foreach ($primaryKeyColumns as $pk) {
            $target[]            = new IndexElement(new Identifier($pk));
            $insert->returning[] = new TargetElement(new ColumnReference($pk));
        }

        if ([] !== $nonPrimaryKey) {
            $set = new SetClauseList(\array_map(
                static fn(string $column): SingleSetClause =>
                    new SingleSetClause(new SetTargetElement($column), new ColumnReference('excluded', $column)),
                $nonPrimaryKey
            ));
            $insert->onConflict = new OnConflictClause(OnConflictAction::UPDATE, $target, $set);

        } elseif (
            $this->onConflictSelect ??=
                false !== ($version = \pg_parameter_status($this->getConnection()->getNative(), 'server_version'))
                && \version_compare($version, '19beta', 'ge')
        ) {
            $insert->onConflict = new OnConflictClause(OnConflictAction::SELECT, $target);

        } else {
            // "DO INSTEAD NOTHING" clause leads to no rows returned by RETURNING clause, thus a fake update
            $pkey = \reset($primaryKeyColumns);
            $insert->onConflict = new OnConflictClause(OnConflictAction::UPDATE, $target, new SetClauseList([
                new SingleSetClause(new SetTargetElement($pkey), new ColumnReference('excluded', $pkey))
            ]));
        }
    }
}
