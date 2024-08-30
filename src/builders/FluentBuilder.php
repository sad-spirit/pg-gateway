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

namespace sad_spirit\pg_gateway\builders;

use sad_spirit\pg_gateway\{
    Condition,
    Fragment,
    SelectBuilder,
    SelectProxy,
    SqlStringSelectBuilder,
    TableGateway,
    exceptions\InvalidArgumentException,
    metadata\TableName
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
    LimitClauseFragment,
    OffsetClauseFragment,
    OrderByClauseFragment,
    ReturningClauseFragment,
    SelectListFragment,
    TargetListManipulator,
    target_list\ConditionAppender,
    target_list\SqlStringAppender,
    with\SqlStringFragment
};
use sad_spirit\pg_builder\nodes\{
    OrderByElement,
    QualifiedName
};

/**
 * Generic Builder returned by TableLocator for tables not having a specific one
 *
 * This contains methods that were previously in GenericTableGateway
 *
 * @since 0.2.0
 */
class FluentBuilder extends FragmentListBuilder
{
    use PrimaryKeyBuilder;

    /**
     * A non-fluent version of {@see any()}
     *
     * The returned value can be combined with AND / OR before adding to the list
     *
     * @param string $column
     * @param array $values
     * @return ParametrizedCondition
     */
    public function createAny(string $column, array $values): ParametrizedCondition
    {
        return new ParametrizedCondition(
            new AnyCondition(
                $this->definition->getColumns()->get($column),
                $this->tableLocator->getTypeConverterFactory()
            ),
            [$column => $values]
        );
    }

    /**
     * A non-fluent version of {@see boolColumn()}
     *
     * The returned value can be combined with AND / OR before adding to the list
     *
     * @param string $column
     * @return BoolCondition
     */
    public function createBoolColumn(string $column): BoolCondition
    {
        return new BoolCondition($this->definition->getColumns()->get($column));
    }

    /**
     * A non-fluent version of {@see notBoolColumn()}
     *
     * The returned value can be combined with AND / OR before adding to the list
     *
     * @param string $column
     * @return NotCondition
     */
    public function createNotBoolColumn(string $column): NotCondition
    {
        return new NotCondition($this->createBoolColumn($column));
    }

    /**
     * A non-fluent version of {@see isNull()}
     *
     * The returned value can be combined with AND / OR before adding to the list
     *
     * @param string $column
     * @return IsNullCondition
     */
    public function createIsNull(string $column): IsNullCondition
    {
        return new IsNullCondition($this->definition->getColumns()->get($column));
    }

    /**
     * A non-fluent version of {@see isNotNull()}
     *
     * The returned value can be combined with AND / OR before adding to the list
     *
     * @param string $column
     * @return NotCondition
     */
    public function createIsNotNull(string $column): NotCondition
    {
        return new NotCondition($this->createIsNull($column));
    }

    /**
     * A non-fluent version of {@see notAll()}
     *
     * The returned value can be combined with AND / OR before adding to the list
     *
     * @param string $column
     * @param array $values
     * @return ParametrizedCondition
     */
    public function createNotAll(string $column, array $values): ParametrizedCondition
    {
        return new ParametrizedCondition(
            new NotAllCondition(
                $this->definition->getColumns()->get($column),
                $this->tableLocator->getTypeConverterFactory()
            ),
            [$column => $values]
        );
    }

    /**
     * A non-fluent version of {@see operatorCondition()}
     *
     * The returned value can be combined with AND / OR before adding to the list
     *
     * @param string $column
     * @param string $operator
     * @param mixed $value
     * @return ParametrizedCondition
     */
    public function createOperatorCondition(string $column, string $operator, $value): ParametrizedCondition
    {
        return new ParametrizedCondition(
            new OperatorCondition(
                $this->definition->getColumns()->get($column),
                $this->tableLocator->getTypeConverterFactory(),
                $operator
            ),
            [$column => $value]
        );
    }

    /**
     * A non-fluent version of {@see equal()}
     *
     * The returned value can be combined with AND / OR before adding to the list
     *
     * @param string $column
     * @param mixed $value
     * @return ParametrizedCondition
     */
    public function createEqual(string $column, $value): ParametrizedCondition
    {
        return $this->createOperatorCondition($column, '=', $value);
    }

    /**
     * A non-fluent version of {@see sqlCondition()}
     *
     * The returned value can be combined with AND / OR before adding to the list
     *
     * @param string $sql
     * @param array $parameters
     * @return ParametrizedCondition
     */
    public function createSqlCondition(string $sql, array $parameters = []): ParametrizedCondition
    {
        return new ParametrizedCondition(
            new SqlStringCondition($this->tableLocator->getParser(), $sql),
            $parameters
        );
    }

    /**
     * Creates a Builder for configuring a `[NOT] EXISTS(...)` condition
     *
     * The Condition returned by the Builder can be combined with AND / OR before adding to the list
     *
     * @param string|TableName|QualifiedName|TableGateway|SelectProxy $select
     * @return ExistsBuilder
     */
    public function createExists($select): ExistsBuilder
    {
        return new ExistsBuilder($this->definition, $this->normalizeSelect($select));
    }

    /**
     * Adds a `self.column = any(:column::column_type[])` SQL condition
     *
     * This is roughly equivalent to `column IN (...values)` but requires only one placeholder
     *
     * @param string $column
     * @param array $values
     * @return $this
     */
    public function any(string $column, array $values): self
    {
        return $this->add($this->createAny($column, $values));
    }

    /**
     * Adds a `self.column` Condition for a column of `bool` type
     *
     * @param string $column
     * @return $this
     */
    public function boolColumn(string $column): self
    {
        return $this->add($this->createBoolColumn($column));
    }

    /**
     * Adds a `NOT self.column` Condition for a column of `bool` type
     *
     * @param string $column
     * @return $this
     */
    public function notBoolColumn(string $column): self
    {
        return $this->add($this->createNotBoolColumn($column));
    }

    /**
     * Adds a `self.column IS NULL` Condition
     *
     * @param string $column
     * @return $this
     */
    public function isNull(string $column): self
    {
        return $this->add($this->createIsNull($column));
    }

    /**
     * Adds a `self.column IS NOT NULL` Condition
     *
     * @param string $column
     * @return $this
     */
    public function isNotNull(string $column): self
    {
        return $this->add($this->createIsNotNull($column));
    }

    /**
     * Adds a `self.column <> all(:column::column_type[])` Condition
     *
     * This is roughly equivalent to `self.column NOT IN (...values)` but requires only one placeholder
     *
     * @param string $column
     * @param array $values
     * @return $this
     */
    public function notAll(string $column, array $values): self
    {
        return $this->add($this->createNotAll($column, $values));
    }

    /**
     * Adds a `self.column <OPERATOR> :column::column_type` condition
     *
     * The value will be actually passed separately as a query parameter
     *
     * @param string $column
     * @param string $operator
     * @param mixed $value
     * @return $this
     */
    public function operatorCondition(string $column, string $operator, $value): self
    {
        return $this->add($this->createOperatorCondition($column, $operator, $value));
    }

    /**
     * Adds a `self.column = :column::column_type` condition
     *
     * The value will be actually passed separately as a query parameter
     *
     * @param string $column
     * @param mixed $value
     * @return $this
     */
    public function equal(string $column, $value): self
    {
        return $this->add($this->createEqual($column, $value));
    }

    /**
     * Adds a Condition based on the given SQL expression
     *
     * @param string $sql
     * @param array $parameters
     * @return $this
     */
    public function sqlCondition(string $sql, array $parameters = []): self
    {
        return $this->add($this->createSqlCondition($sql, $parameters));
    }

    /**
     * Adds a condition on a primary key
     *
     * @param mixed $value
     * @return $this
     */
    public function primaryKey($value): self
    {
        return $this->add($this->createPrimaryKey($value));
    }

    /**
     * Configures a list of columns returned by a SELECT statement
     *
     * $callback is a function that accepts an instance of ColumnsBuilder and should configure it:
     * <code>
     * $builder->outputColumns(fn(ColumnsBuilder $cb) => $cb->primaryKey()->replace('/_id$/', 'Identity'));
     * </code>
     *
     * @param callable(ColumnsBuilder): mixed|null $callback Deprecated since 0.4.0, use methods of the returned object
     * @return proxies\ColumnsBuilderProxy<static>
     */
    public function outputColumns(callable $callback = null): proxies\ColumnsBuilderProxy
    {
        $builder = new proxies\ColumnsBuilderProxy($this, $this->definition, false);
        $this->addProxy($builder);

        if (null !== $callback) {
            $callback($builder);
        }

        return $builder;
    }

    /**
     * Configures a list of columns in the RETURNING clause
     *
     * $callback is a function that accepts an instance of ColumnsBuilder and should configure it:
     * <code>
     * $builder->returningColumns(fn(ColumnsBuilder $cb) => $cb->only(['id', 'name']));
     * </code>
     *
     * @param callable(ColumnsBuilder): mixed|null $callback Deprecated since 0.4.0, use methods of the returned object
     * @return proxies\ColumnsBuilderProxy<static>
     */
    public function returningColumns(callable $callback = null): proxies\ColumnsBuilderProxy
    {
        $builder = new proxies\ColumnsBuilderProxy($this, $this->definition, true);
        $this->addProxy($builder);

        if (null !== $callback) {
            $callback($builder);
        }

        return $builder;
    }

    /**
     * Adds a scalar subquery to the output list of a SELECT statement
     *
     * While the companion `returningSubquery()` method is possible, it's unlikely to be used.
     *
     * $callback is a function that accepts an instance of ScalarSubqueryBuilder and should configure it:
     * <code>
     * $builder->outputSubquery(
     *     $select,
     *     fn(ScalarSubqueryBuilder $sb) => $sb->joinOnForeignKey()->alias('foo')
     * );
     * </code>
     *
     * @param SelectProxy $select
     * @param callable(ScalarSubqueryBuilder): mixed|null $callback Deprecated since 0.4.0, use methods
     *                                                              of the returned object
     * @return proxies\ScalarSubqueryBuilderProxy<static>
     */
    public function outputSubquery(SelectProxy $select, callable $callback = null): proxies\ScalarSubqueryBuilderProxy
    {
        $builder = new proxies\ScalarSubqueryBuilderProxy($this, $this->definition, $select);
        $this->addProxy($builder);

        if (null !== $callback) {
            $callback($builder);
        }

        return $builder;
    }

    /**
     * Adds expression(s) to the list of columns returned by a SELECT statement
     *
     * @param string|Condition $expression
     * @param string|null $alias
     * @return $this
     */
    public function outputExpression($expression, ?string $alias = null): self
    {
        return $this->add(new SelectListFragment($this->expressionToManipulator($expression, $alias)));
    }

    /**
     * Adds expression(s) to the list of columns in the RETURNING clause
     *
     * @param string|Condition $expression
     * @param string|null $alias
     * @return $this
     */
    public function returningExpression($expression, ?string $alias = null): self
    {
        return $this->add(new ReturningClauseFragment($this->expressionToManipulator($expression, $alias)));
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
     * Adds a join to the given table
     *
     * $callback is a function that accepts an instance of JoinBuilder and should configure it:
     * <code>
     * $builder->join($table, fn(JoinBuilder $jb) => $jb->left()->onForeignKey());
     * </code>
     *
     * @param string|TableName|QualifiedName|TableGateway|SelectProxy $joined
     * @param callable(JoinBuilder): mixed|null $callback Deprecated since 0.4.0, use methods of the returned object
     * @return proxies\JoinBuilderProxy<static>
     */
    public function join($joined, callable $callback = null): proxies\JoinBuilderProxy
    {
        $builder = new proxies\JoinBuilderProxy($this, $this->definition, $this->normalizeSelect($joined));
        $this->addProxy($builder);

        if (null !== $callback) {
            $callback($builder);
        }

        return $builder;
    }

    /**
     * Adds a `[NOT] EXISTS(...)` condition
     *
     * $callback is a function that accepts an instance of ExistsBuilder and should configure it:
     * <code>
     * $builder->join($table, fn(ExistsBuilder $eb) => $eb->not()->joinOnForeignKey());
     * </code>
     *
     * @param string|TableName|QualifiedName|TableGateway|SelectProxy $select
     * @param callable(ExistsBuilder):mixed|null $callback Deprecated since 0.4.0, use methods of the returned object
     * @return proxies\ExistsBuilderProxy<static>
     */
    public function exists($select, callable $callback = null): proxies\ExistsBuilderProxy
    {
        $builder = new proxies\ExistsBuilderProxy($this, $this->definition, $this->normalizeSelect($select));
        $this->addProxy($builder);

        if (null !== $callback) {
            $callback($builder);
        }

        return $builder;
    }

    /**
     * Tries to convert a parameter passed to join() or exists() to SelectProxy
     *
     * @param string|TableName|QualifiedName|TableGateway|SelectBuilder $select
     * @return SelectBuilder
     * @psalm-suppress RedundantConditionGivenDocblockType
     * @psalm-suppress DocblockTypeContradiction
     */
    private function normalizeSelect($select): SelectBuilder
    {
        if (\is_string($select)) {
            return new SqlStringSelectBuilder($this->tableLocator->getParser(), $select);
        } elseif ($select instanceof QualifiedName || $select instanceof TableName) {
            return $this->tableLocator->createGateway($select)
                ->select();
        } elseif ($select instanceof TableGateway) {
            return $select->select();
        } elseif ($select instanceof SelectBuilder) {
            return $select;
        }

        throw new InvalidArgumentException(\sprintf(
            "A table name, TableGateway or SelectBuilder instance expected, %s given",
            \is_object($select) ? 'object(' . \get_class($select) . ')' : \gettype($select)
        ));
    }

    /**
     * Adds a [part of] WITH clause represented by an SQL string
     *
     * The string may contain either a complete WITH clause `WITH foo AS (...)`, possibly with multiple CTEs,
     * or a single CTE `foo AS (...)`
     *
     * @param string $sql
     * @param array $parameters
     * @param int $priority
     * @return $this
     */
    public function withSqlString(string $sql, array $parameters = [], int $priority = Fragment::PRIORITY_DEFAULT): self
    {
        return $this->add(new SqlStringFragment(
            $this->tableLocator->getParser(),
            $sql,
            $parameters,
            $priority
        ));
    }

    /**
     * Adds a SELECT to the WITH clause
     *
     * $callback is a function that accepts an instance of WithClauseBuilder and should configure it
     * <code>
     * $builder->withSelect(
     *     $select,
     *     'foo',
     *     fn(WithClauseBuilder $wb) => $wb->recursive()
     *         ->columnAliases(['bar', 'baz'])
     * );
     * </code>
     *
     * @param SelectProxy $select
     * @param string $alias
     * @param callable(WithClauseBuilder):mixed|null $callback Deprecated since 0.4.0, use methods of the returned object
     * @return proxies\WithClauseBuilderProxy<static>
     */
    public function withSelect(
        SelectProxy $select,
        string $alias,
        callable $callback = null
    ): proxies\WithClauseBuilderProxy {
        $builder = new proxies\WithClauseBuilderProxy($this, $select, $alias);
        $this->addProxy($builder);

        if (null !== $callback) {
            $callback($builder);
        }

        return $builder;
    }

    /**
     * Sets the `ORDER BY` list of a `SELECT` query to the given expressions
     *
     * As setting the list basically involves embedding custom incoming SQL into query, this is the default restricted
     * version that allows only column names and ordinal numbers as sort expressions. It is not a good idea to
     * use unchecked user input anyway, white-lists of allowed sort expressions are preferable.
     *
     * @param iterable<OrderByElement|string>|string $orderBy
     * @return $this
     */
    public function orderBy($orderBy): self
    {
        return $this->add(new OrderByClauseFragment($this->tableLocator->getParser(), $orderBy));
    }

    /**
     * Sets the `ORDER BY` list of a `SELECT` query to the given expressions (unsafe version)
     *
     * This version should be used explicitly if sorting by arbitrary expressions is needed. User input should
     * NEVER be used with this method.
     *
     * @param iterable<OrderByElement|string>|string $orderBy
     * @return $this
     */
    public function orderByUnsafe($orderBy): self
    {
        return $this->add(new OrderByClauseFragment($this->tableLocator->getParser(), $orderBy, false));
    }

    /**
     * Adds the `LIMIT` clause to a `SELECT` query
     *
     * The actual value for `LIMIT` is not embedded into SQL, but passed as a query parameter
     *
     * @param int $limit
     * @return $this
     */
    public function limit(int $limit): self
    {
        return $this->add(new LimitClauseFragment($limit));
    }

    /**
     * Adds the `OFFSET` clause to a `SELECT` query
     *
     * The actual value for `OFFSET` is not embedded into SQL, but passed as a query parameter
     *
     * @param int $offset
     * @return $this
     */
    public function offset(int $offset): self
    {
        return $this->add(new OffsetClauseFragment($offset));
    }
}
