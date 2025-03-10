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
    AdHocStatement,
    Fragment,
    FragmentBuilder,
    FragmentList,
    SelectBuilder,
    SelectProxy,
    StatementType,
    TableDefinition,
    TableGateway,
    TableLocator,
    TableSelect,
    builders\FragmentListBuilder,
    fragments\ClosureFragment,
    fragments\InsertSelectFragment,
    fragments\SetClauseFragment
};
use sad_spirit\pg_builder\{
    Delete,
    Insert,
    NativeStatement,
    SelectCommon,
    Update
};
use sad_spirit\pg_builder\nodes\{
    Identifier,
    lists\SetClauseList,
    range\InsertTarget,
    range\UpdateOrDeleteTarget
};
use sad_spirit\pg_wrapper\{
    Connection,
    Result
};

/**
 * A generic implementation of TableGateway
 *
 * @psalm-import-type FragmentsInput from TableGateway
 */
class GenericTableGateway implements TableGateway, AdHocStatement
{
    protected TableLocator $tableLocator;
    protected TableDefinition $definition;

    public function __construct(TableDefinition $definition, TableLocator $tableLocator)
    {
        $this->definition   = $definition;
        $this->tableLocator = $tableLocator;
    }

    public function getConnection(): Connection
    {
        return $this->tableLocator->getConnection();
    }

    public function getDefinition(): TableDefinition
    {
        return $this->definition;
    }

    /**
     * Creates a specific builder for the current table
     */
    public function createBuilder(): FragmentListBuilder
    {
        return $this->tableLocator->createBuilder($this->definition->getName());
    }

    public function delete(
        null|iterable|\Closure|Fragment|FragmentBuilder $fragments = null,
        array $parameters = []
    ): Result {
        $fragmentList = $this->convertFragments($fragments, $parameters);

        return $this->execute($this->createDeleteStatement($fragmentList), $fragmentList);
    }

    public function insert(
        array|SelectCommon|SelectBuilder $values,
        null|iterable|\Closure|Fragment|FragmentBuilder $fragments = null,
        array $parameters = []
    ): Result {
        $fragmentList = $this->convertFragments($fragments, $parameters);

        if ($values instanceof SelectBuilder) {
            $fragmentList->add(new InsertSelectFragment($values));
        } elseif ($values instanceof SelectCommon) {
            $fragmentList->add(new ClosureFragment(
                static function (Insert $insert) use ($values): void {
                    $insert->values = $values;
                }
            ));
        } elseif ([] !== $values) {
            $fragmentList->add(new SetClauseFragment(
                $this->definition->getColumns(),
                $this->tableLocator,
                $values
            ));
        }

        return $this->execute($this->createInsertStatement($fragmentList), $fragmentList);
    }

    public function select(
        null|iterable|\Closure|Fragment|FragmentBuilder $fragments = null,
        array $parameters = []
    ): TableSelect {
        return new TableSelect($this->tableLocator, $this, $this->convertFragments($fragments, $parameters));
    }

    public function update(
        array $set,
        null|iterable|\Closure|Fragment|FragmentBuilder $fragments = null,
        array $parameters = []
    ): Result {
        $native = $this->createUpdateStatement($list = new FragmentList(
            new SetClauseFragment($this->definition->getColumns(), $this->tableLocator, $set),
            $this->convertFragments($fragments, $parameters)
        ));

        return $this->execute($native, $list);
    }

    /**
     * Converts $fragments and $parameters passed to a method defined in TableGateway to an instance of FragmentList
     *
     * @param FragmentsInput       $fragments
     * @param array<string, mixed> $parameters
     */
    protected function convertFragments(
        null|iterable|\Closure|Fragment|FragmentBuilder $fragments,
        array $parameters
    ): FragmentList {
        if (!$fragments instanceof \Closure) {
            return FragmentList::normalize($fragments)
                ->mergeParameters($parameters);
        } else {
            $fragments($builder = $this->createBuilder());
            return $builder->getFragment()
                ->mergeParameters($parameters);
        }
    }

    public function deleteWithAST(\Closure $closure, array $parameters = []): Result
    {
        return $this->delete(new ClosureFragment($closure), $parameters);
    }

    public function insertWithAST(
        array|SelectCommon|SelectBuilder $values,
        \Closure $closure,
        array $parameters = []
    ): Result {
        return $this->insert($values, new ClosureFragment($closure), $parameters);
    }

    public function selectWithAST(\Closure $closure, array $parameters = []): SelectProxy
    {
        return $this->select(new ClosureFragment($closure), $parameters);
    }

    public function updateWithAST(array $set, \Closure $closure, array $parameters = []): Result
    {
        return $this->update($set, new ClosureFragment($closure), $parameters);
    }

    /**
     * Executes the given $statement possibly using parameters from $fragments
     */
    private function execute(NativeStatement $statement, FragmentList $fragments): Result
    {
        return [] === $statement->getParameterTypes()
            ? $this->getConnection()->execute($statement->getSql())
            : $statement->executeParams($this->getConnection(), $fragments->getParameters());
    }

    /**
     * Generates a DELETE statement using given fragments
     */
    public function createDeleteStatement(FragmentList $fragments): NativeStatement
    {
        return $this->tableLocator->createNativeStatementUsingCache(
            function () use ($fragments): Delete {
                $delete = $this->tableLocator->getStatementFactory()->delete(new UpdateOrDeleteTarget(
                    $this->definition->getName()->createNode(),
                    new Identifier(self::ALIAS_SELF)
                ));
                $fragments->applyTo($delete);

                return $delete;
            },
            $this->generateStatementKey(StatementType::Delete, $fragments)
        );
    }

    /**
     * Generates an INSERT statement using given fragments
     */
    public function createInsertStatement(FragmentList $fragments): NativeStatement
    {
        return $this->tableLocator->createNativeStatementUsingCache(
            function () use ($fragments): Insert {
                $insert = $this->tableLocator->getStatementFactory()->insert(new InsertTarget(
                    $this->definition->getName()->createNode(),
                    new Identifier(TableGateway::ALIAS_SELF)
                ));
                $fragments->applyTo($insert);
                return $insert;
            },
            $this->generateStatementKey(StatementType::Insert, $fragments)
        );
    }

    /**
     * Generates an UPDATE statement using given fragments
     */
    public function createUpdateStatement(FragmentList $fragments): NativeStatement
    {
        return $this->tableLocator->createNativeStatementUsingCache(
            function () use ($fragments): Update {
                $update = $this->tableLocator->getStatementFactory()->update(
                    new UpdateOrDeleteTarget(
                        $this->definition->getName()->createNode(),
                        new Identifier(TableGateway::ALIAS_SELF)
                    ),
                    new SetClauseList()
                );
                $fragments->applyTo($update);
                return $update;
            },
            $this->generateStatementKey(StatementType::Update, $fragments)
        );
    }

    /**
     * Returns a cache key for the statement being generated
     */
    protected function generateStatementKey(StatementType $type, FragmentList $fragments): ?string
    {
        if (null === $fragmentKey = $fragments->getKey()) {
            return null;
        }
        return \sprintf(
            '%s.%s.%s.%s',
            $this->getConnection()->getConnectionId(),
            $type->name,
            TableLocator::hash($this->definition->getName()),
            $fragmentKey
        );
    }
}
