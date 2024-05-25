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
    Condition,
    FragmentList,
    SelectProxy,
    TableGateway,
    TableLocator,
    TableSelect,
    builders\ColumnsBuilder,
    builders\ExistsBuilder,
    builders\JoinBuilder,
    builders\ScalarSubqueryBuilder,
    exceptions\InvalidArgumentException,
    metadata\Columns,
    metadata\PrimaryKey,
    metadata\References,
    metadata\TableColumns,
    metadata\TableName,
    metadata\TablePrimaryKey,
    metadata\TableReferences
};
use sad_spirit\pg_gateway\conditions\{
    NotCondition,
    ParametrizedCondition,
    SqlStringCondition,
    column\AnyCondition,
    column\BoolCondition,
    column\IsNullCondition,
    column\NotAllCondition,
    column\OperatorCondition
};
use sad_spirit\pg_gateway\fragments\{
    ClosureFragment,
    InsertSelectFragment,
    LimitClauseFragment,
    OffsetClauseFragment,
    OrderByClauseFragment,
    ReturningClauseFragment,
    SelectListFragment,
    SetClauseFragment,
    TargetListManipulator,
    target_list\ConditionAppender,
    target_list\SqlStringAppender
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
    OrderByElement,
    QualifiedName,
    lists\SetClauseList,
    range\InsertTarget,
    range\UpdateOrDeleteTarget
};
use sad_spirit\pg_wrapper\{
    Connection,
    ResultSet
};

/**
 * A generic implementation of TableGateway
 */
class GenericTableGateway implements TableGateway
{
    private TableName $name;
    protected TableLocator $tableLocator;
    private ?TableColumns $columns = null;
    private ?TablePrimaryKey $primaryKey = null;
    private ?TableReferences $references = null;

    /**
     * Creates an instance of GenericTableGateway or its subclass based on table's primary key
     *
     * @param TableName $name
     * @param TableLocator $tableLocator
     * @return self
     */
    public static function create(TableName $name, TableLocator $tableLocator): self
    {
        $primaryKey = new TablePrimaryKey($tableLocator->getConnection(), $name);

        switch (\count($primaryKey)) {
            case 0:
                $gateway = new self($name, $tableLocator);
                break;

            case 1:
                $gateway = new PrimaryKeyTableGateway($name, $tableLocator);
                break;

            default:
                $gateway = new CompositePrimaryKeyTableGateway($name, $tableLocator);
        }

        $gateway->primaryKey = $primaryKey;
        return $gateway;
    }

    public function __construct(TableName $name, TableLocator $tableLocator)
    {
        $this->name = $name;
        $this->tableLocator = $tableLocator;
    }

    public function getName(): TableName
    {
        return $this->name;
    }

    public function getConnection(): Connection
    {
        return $this->tableLocator->getConnection();
    }

    public function getColumns(): Columns
    {
        return $this->columns ??= new TableColumns($this->getConnection(), $this->name);
    }

    public function getPrimaryKey(): PrimaryKey
    {
        return $this->primaryKey ??= new TablePrimaryKey($this->getConnection(), $this->name);
    }

    public function getReferences(): References
    {
        return $this->references ??= new TableReferences($this->getConnection(), $this->name);
    }

    public function delete($fragments = null, array $parameters = []): ResultSet
    {
        $fragmentList = FragmentList::normalize($fragments)
            ->mergeParameters($parameters);

        return $this->execute($this->createDeleteStatement($fragmentList), $fragmentList);
    }

    /**
     * {@inheritDoc}
     * @psalm-suppress RedundantConditionGivenDocblockType
     */
    public function insert($values, $fragments = null, array $parameters = []): ResultSet
    {
        $fragmentList = FragmentList::normalize($fragments)
            ->mergeParameters($parameters);

        if ($values instanceof SelectProxy) {
            $fragmentList->add(new InsertSelectFragment($values));
        } elseif ($values instanceof SelectCommon) {
            $fragmentList->add(new ClosureFragment(
                static function (Insert $insert) use ($values) {
                    $insert->values = $values;
                }
            ));
        } elseif (\is_array($values)) {
            if ([] !== $values) {
                $fragmentList->add(new SetClauseFragment(
                    $this->getColumns(),
                    $this->tableLocator,
                    $values
                ));
            }
        } else {
            throw new InvalidArgumentException(sprintf(
                "\$values should be either of: an array, an instance of SelectCommon,"
                . " an implementation of SelectProxy; %s given",
                \is_object($values) ? 'object(' . \get_class($values) . ')' : \gettype($values)
            ));
        }

        return $this->execute($this->createInsertStatement($fragmentList), $fragmentList);
    }

    public function select($fragments = null, array $parameters = []): TableSelect
    {
        return new TableSelect($this->tableLocator, $this, $fragments, $parameters);
    }

    public function update(array $set, $fragments = null, array $parameters = []): ResultSet
    {
        $native = $this->createUpdateStatement($list = new FragmentList(
            new SetClauseFragment($this->getColumns(), $this->tableLocator, $set),
            FragmentList::normalize($fragments)
                ->mergeParameters($parameters)
        ));

        return $this->execute($native, $list);
    }

    /**
     * Executes the given $statement possibly using parameters from $fragments
     *
     * @param NativeStatement $statement
     * @param FragmentList $fragments
     * @return ResultSet
     */
    private function execute(NativeStatement $statement, FragmentList $fragments): ResultSet
    {
        return [] === $statement->getParameterTypes()
            ? $this->getConnection()->execute($statement->getSql())
            : $statement->executeParams($this->getConnection(), $fragments->getParameters());
    }

    /**
     * Generates a DELETE statement using given fragments
     *
     * @param FragmentList $fragments
     * @return NativeStatement
     */
    public function createDeleteStatement(FragmentList $fragments): NativeStatement
    {
        return $this->tableLocator->createNativeStatementUsingCache(
            function () use ($fragments): Delete {
                $delete = $this->tableLocator->getStatementFactory()->delete(new UpdateOrDeleteTarget(
                    $this->getName()->createNode(),
                    new Identifier(self::ALIAS_SELF)
                ));
                $fragments->applyTo($delete);

                return $delete;
            },
            $this->generateStatementKey(self::STATEMENT_DELETE, $fragments)
        );
    }

    /**
     * Generates an INSERT statement using given fragments
     *
     * @param FragmentList $fragments
     * @return NativeStatement
     */
    public function createInsertStatement(FragmentList $fragments): NativeStatement
    {
        return $this->tableLocator->createNativeStatementUsingCache(
            function () use ($fragments): Insert {
                $insert = $this->tableLocator->getStatementFactory()->insert(new InsertTarget(
                    $this->getName()->createNode(),
                    new Identifier(TableGateway::ALIAS_SELF)
                ));
                $fragments->applyTo($insert);
                return $insert;
            },
            $this->generateStatementKey(self::STATEMENT_INSERT, $fragments)
        );
    }

    /**
     * Generates an UPDATE statement using given fragments
     *
     * @param FragmentList $fragments
     * @return NativeStatement
     */
    public function createUpdateStatement(FragmentList $fragments): NativeStatement
    {
        return $this->tableLocator->createNativeStatementUsingCache(
            function () use ($fragments): Update {
                $update = $this->tableLocator->getStatementFactory()->update(
                    new UpdateOrDeleteTarget(
                        $this->getName()->createNode(),
                        new Identifier(TableGateway::ALIAS_SELF)
                    ),
                    new SetClauseList()
                );
                $fragments->applyTo($update);
                return $update;
            },
            $this->generateStatementKey(self::STATEMENT_UPDATE, $fragments)
        );
    }

    /**
     * Returns a cache key for the statement being generated
     */
    protected function generateStatementKey(string $statementType, FragmentList $fragments): ?string
    {
        if (null === ($fragmentKey = $fragments->getKey())) {
            return null;
        }
        return \sprintf(
            '%s.%s.%s.%s',
            $this->getConnection()->getConnectionId(),
            $statementType,
            TableLocator::hash($this->getName()),
            $fragmentKey
        );
    }

    /**
     * Creates a `self.column = any(:column::column_type[])` SQL condition
     *
     * This is roughly equivalent to `column IN (...values)` but requires only one placeholder
     *
     * @param string $column
     * @param array $values
     * @return ParametrizedCondition
     */
    public function any(string $column, array $values): ParametrizedCondition
    {
        return new ParametrizedCondition(
            new AnyCondition(
                $this->getColumns()->get($column),
                $this->tableLocator->getTypeConverterFactory()
            ),
            [$column => $values]
        );
    }

    /**
     * Creates a `self.column` Condition for a column of `bool` type
     *
     * @param string $column
     * @return BoolCondition
     */
    public function column(string $column): BoolCondition
    {
        return new BoolCondition($this->getColumns()->get($column));
    }

    /**
     * Creates a `NOT self.column` Condition for a column of `bool` type
     *
     * @param string $column
     * @return NotCondition
     */
    public function notColumn(string $column): NotCondition
    {
        return new NotCondition($this->column($column));
    }

    /**
     * Creates a `self.column IS NULL` Condition
     *
     * @param string $column
     * @return IsNullCondition
     */
    public function isNull(string $column): IsNullCondition
    {
        return new IsNullCondition($this->getColumns()->get($column));
    }

    /**
     * Creates a `self.column IS NOT NULL` Condition
     *
     * @param string $column
     * @return NotCondition
     */
    public function isNotNull(string $column): NotCondition
    {
        return new NotCondition($this->isNull($column));
    }

    /**
     * Creates a `self.column <> all(:column::column_type[])` Condition
     *
     * This is roughly equivalent to `self.column NOT IN (...values)` but requires only one placeholder
     *
     * @param string $column
     * @param array $values
     * @return ParametrizedCondition
     */
    public function notAll(string $column, array $values): ParametrizedCondition
    {
        return new ParametrizedCondition(
            new NotAllCondition(
                $this->getColumns()->get($column),
                $this->tableLocator->getTypeConverterFactory()
            ),
            [$column => $values]
        );
    }

    /**
     * Creates a `self.column <OPERATOR> :column::column_type` condition
     *
     * The value will be actually passed separately as a query parameter
     *
     * @param string $column
     * @param string $operator
     * @param mixed $value
     * @return ParametrizedCondition
     */
    public function operatorCondition(string $column, string $operator, $value): ParametrizedCondition
    {
        return new ParametrizedCondition(
            new OperatorCondition(
                $this->getColumns()->get($column),
                $this->tableLocator->getTypeConverterFactory(),
                $operator
            ),
            [$column => $value]
        );
    }

    /**
     * Creates a `self.column = :column::column_type` condition
     *
     * The value will be actually passed separately as a query parameter
     *
     * @param string $column
     * @param mixed $value
     * @return ParametrizedCondition
     */
    public function equal(string $column, $value): ParametrizedCondition
    {
        return $this->operatorCondition($column, '=', $value);
    }

    /**
     * Creates a Condition based on the given SQL expression
     *
     * @param string $sql
     * @param array $parameters
     * @return ParametrizedCondition
     */
    public function sqlCondition(string $sql, array $parameters = []): ParametrizedCondition
    {
        return new ParametrizedCondition(
            new SqlStringCondition($this->tableLocator->getParser(), $sql),
            $parameters
        );
    }

    /**
     * Creates a Builder for configuring a list of columns returned by a SELECT statement
     *
     * @return ColumnsBuilder
     */
    public function outputColumns(): ColumnsBuilder
    {
        return new ColumnsBuilder($this, false);
    }

    /**
     * Creates a Builder for configuring a list of columns in the RETURNING clause
     *
     * @return ColumnsBuilder
     */
    public function returningColumns(): ColumnsBuilder
    {
        return new ColumnsBuilder($this, true);
    }

    /**
     * Creates a builder for configuring a scalar subquery to be added to the output list of a SELECT statement
     *
     * While the companion `returningSubquery()` method is possible, it's unlikely to be used
     *
     * @param SelectProxy $select
     * @return ScalarSubqueryBuilder
     */
    public function outputSubquery(SelectProxy $select): ScalarSubqueryBuilder
    {
        return new ScalarSubqueryBuilder($this, $select);
    }

    /**
     * Adds expression(s) to the list of columns returned by a SELECT statement
     *
     * @param string|Condition $expression
     * @param string|null $alias
     * @return SelectListFragment
     */
    public function outputExpression($expression, ?string $alias = null): SelectListFragment
    {
        return new SelectListFragment($this->expressionToManipulator($expression, $alias));
    }

    /**
     * Adds expression(s) to the list of columns in the RETURNING clause
     *
     * @param string|Condition $expression
     * @param string|null $alias
     * @return ReturningClauseFragment
     */
    public function returningExpression($expression, ?string $alias = null): ReturningClauseFragment
    {
        return new ReturningClauseFragment($this->expressionToManipulator($expression, $alias));
    }

    /**
     * Returns the proper TargetListManipulator for the given expression
     *
     * @param string|Condition $expression
     * @param string|null $alias
     * @return TargetListManipulator
     * @psalm-suppress RedundantConditionGivenDocblockType
     * @psalm-suppress DocblockTypeContradiction
     */
    private function expressionToManipulator($expression, ?string $alias = null): TargetListManipulator
    {
        if (\is_string($expression)) {
            return new SqlStringAppender($this->tableLocator->getParser(), $expression, $alias);
        } elseif ($expression instanceof Condition) {
            return new ConditionAppender($expression, $alias);
        } else {
            throw new InvalidArgumentException(\sprintf(
                "An SQL string or Condition instance expected, %s given",
                \is_object($expression) ? 'object(' . \get_class($expression) . ')' : \gettype($expression)
            ));
        }
    }

    /**
     * Creates a Builder for configuring a join to the given table
     *
     * @param string|QualifiedName|TableGateway|SelectProxy $joined
     * @return JoinBuilder
     */
    public function join($joined): JoinBuilder
    {
        return new JoinBuilder($this, $this->normalizeSelect($joined));
    }

    /**
     * Creates a Builder for configuring a `[NOT] EXISTS(...)` condition
     *
     * @param string|QualifiedName|TableGateway|SelectProxy $select
     * @return ExistsBuilder
     */
    public function exists($select): ExistsBuilder
    {
        return new ExistsBuilder($this, $this->normalizeSelect($select));
    }

    /**
     * Tries to convert a parameter passed to join() or exists() to SelectProxy
     *
     * @param string|QualifiedName|TableGateway|SelectProxy $select
     * @return SelectProxy
     * @psalm-suppress RedundantConditionGivenDocblockType
     * @psalm-suppress DocblockTypeContradiction
     */
    private function normalizeSelect($select): SelectProxy
    {
        if (\is_string($select) || $select instanceof QualifiedName) {
            $realSelect = $this->tableLocator->get($select)
                ->select();
        } elseif ($select instanceof TableGateway) {
            $realSelect = $select->select();
        } elseif ($select instanceof SelectProxy) {
            $realSelect = $select;
        } else {
            throw new InvalidArgumentException(\sprintf(
                "A table name, TableGateway or SelectProxy instance expected, %s given",
                \is_object($select) ? 'object(' . \get_class($select) . ')' : \gettype($select)
            ));
        }

        return $realSelect;
    }

    /**
     * Returns a Fragment that sets the `ORDER BY` list of a `SELECT` query to the given expressions
     *
     * As setting the list basically involves embedding custom incoming SQL into query, this is the default restricted
     * version that allows only column names and ordinal numbers as sort expressions. It is not a good idea to
     * use unchecked user input anyway, white-lists of allowed sort expressions are preferable.
     *
     * @param iterable<OrderByElement|string>|string $orderBy
     * @return OrderByClauseFragment
     */
    public function orderBy($orderBy): OrderByClauseFragment
    {
        return new OrderByClauseFragment($this->tableLocator->getParser(), $orderBy);
    }

    /**
     * Returns a Fragment that sets the `ORDER BY` list of a `SELECT` query to the given expressions (unsafe version)
     *
     * This version should be used explicitly if sorting by arbitrary expressions is needed. User input should
     * NEVER be used with this method.
     *
     * @param iterable<OrderByElement|string>|string $orderBy
     * @return OrderByClauseFragment
     */
    public function orderByUnsafe($orderBy): OrderByClauseFragment
    {
        return new OrderByClauseFragment($this->tableLocator->getParser(), $orderBy, false);
    }

    /**
     * Returns a Fragment that adds the `LIMIT` clause to a `SELECT` query
     *
     * The actual value for `LIMIT` is not embedded into SQL, but passed as a query parameter
     *
     * @param int $limit
     * @return LimitClauseFragment
     */
    public function limit(int $limit): LimitClauseFragment
    {
        return new LimitClauseFragment($limit);
    }

    /**
     * Returns a Fragment that adds the `OFFSET` clause to a `SELECT` query
     *
     * The actual value for `OFFSET` is not embedded into SQL, but passed as a query parameter
     *
     * @param int $offset
     * @return OffsetClauseFragment
     */
    public function offset(int $offset): OffsetClauseFragment
    {
        return new OffsetClauseFragment($offset);
    }
}
