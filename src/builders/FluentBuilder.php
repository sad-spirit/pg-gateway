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
    Condition,
    Exception,
    Fragment,
    SelectBuilder,
    SelectProxy,
    SqlStringSelectBuilder,
    TableAccessor,
    TableGateway,
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
     */
    public function createBoolColumn(string $column): BoolCondition
    {
        return new BoolCondition($this->definition->getColumns()->get($column));
    }

    /**
     * A non-fluent version of {@see notBoolColumn()}
     *
     * The returned value can be combined with AND / OR before adding to the list
     */
    public function createNotBoolColumn(string $column): NotCondition
    {
        return new NotCondition($this->createBoolColumn($column));
    }

    /**
     * A non-fluent version of {@see isNull()}
     *
     * The returned value can be combined with AND / OR before adding to the list
     */
    public function createIsNull(string $column): IsNullCondition
    {
        return new IsNullCondition($this->definition->getColumns()->get($column));
    }

    /**
     * A non-fluent version of {@see isNotNull()}
     *
     * The returned value can be combined with AND / OR before adding to the list
     */
    public function createIsNotNull(string $column): NotCondition
    {
        return new NotCondition($this->createIsNull($column));
    }

    /**
     * A non-fluent version of {@see notAll()}
     *
     * The returned value can be combined with AND / OR before adding to the list
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
     */
    public function createOperatorCondition(string $column, string $operator, mixed $value): ParametrizedCondition
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
     */
    public function createEqual(string $column, mixed $value): ParametrizedCondition
    {
        return $this->createOperatorCondition($column, '=', $value);
    }

    /**
     * A non-fluent version of {@see sqlCondition()}
     *
     * The returned value can be combined with AND / OR before adding to the list
     *
     * @param array<string, mixed> $parameters
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
     */
    public function createExists(string|TableName|QualifiedName|TableGateway|SelectBuilder $select): ExistsBuilder
    {
        return new ExistsBuilder($this->definition, $this->normalizeSelect($select));
    }

    /**
     * Adds a `self.column = any(:column::column_type[])` SQL condition
     *
     * This is roughly equivalent to `column IN (...values)` but requires only one placeholder
     *
     * @return $this
     */
    public function any(string $column, array $values): self
    {
        return $this->add($this->createAny($column, $values));
    }

    /**
     * Adds a `self.column` Condition for a column of `bool` type
     *
     * @return $this
     */
    public function boolColumn(string $column): self
    {
        return $this->add($this->createBoolColumn($column));
    }

    /**
     * Adds a `NOT self.column` Condition for a column of `bool` type
     *
     * @return $this
     */
    public function notBoolColumn(string $column): self
    {
        return $this->add($this->createNotBoolColumn($column));
    }

    /**
     * Adds a `self.column IS NULL` Condition
     *
     * @return $this
     */
    public function isNull(string $column): self
    {
        return $this->add($this->createIsNull($column));
    }

    /**
     * Adds a `self.column IS NOT NULL` Condition
     *
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
     * @return $this
     */
    public function operatorCondition(string $column, string $operator, mixed $value): self
    {
        return $this->add($this->createOperatorCondition($column, $operator, $value));
    }

    /**
     * Adds a `self.column = :column::column_type` condition
     *
     * The value will be actually passed separately as a query parameter
     *
     * @return $this
     */
    public function equal(string $column, mixed $value): self
    {
        return $this->add($this->createEqual($column, $value));
    }

    /**
     * Adds a Condition based on the given SQL expression
     *
     * @param array<string, mixed> $parameters
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
    public function primaryKey(mixed $value): self
    {
        return $this->add($this->createPrimaryKey($value));
    }

    /**
     * Configures a list of columns returned by a SELECT statement
     *
     * @return proxies\ColumnsBuilderProxy<static>
     * @deprecated Since 0.9.0: use {@see returningColumns()} for both SELECT and data-modifying statements
     */
    public function outputColumns(): proxies\ColumnsBuilderProxy
    {
        return $this->returningColumns();
    }

    /**
     * Configures a list of columns returned by SELECT or the RETURNING clause of DELETE / INSERT / UPDATE
     *
     * @param string[] $only
     * @return proxies\ColumnsBuilderProxy<static>
     */
    public function returningColumns(array $only = []): proxies\ColumnsBuilderProxy
    {
        $builder = new proxies\ColumnsBuilderProxy($this, $this->definition);
        $this->addProxy($builder);

        if ([] !== $only) {
            $builder->only($only);
        }

        return $builder;
    }

    /**
     * Adds a scalar subquery to the output list of a SELECT statement
     *
     * @return proxies\ScalarSubqueryBuilderProxy<static>
     * @deprecated Since 0.9.0: use {@see returningSubquery()} for both SELECT and data-modifying statements
     */
    public function outputSubquery(SelectBuilder $select): proxies\ScalarSubqueryBuilderProxy
    {
        return $this->returningSubquery($select);
    }

    /**
     * Adds a scalar subquery to the output list of a SELECT statement or (maybe even) to the RETURNING clause
     * of DELETE / INSERT / UPDATE
     *
     * @return proxies\ScalarSubqueryBuilderProxy<static>
     */
    public function returningSubquery(SelectBuilder $select): proxies\ScalarSubqueryBuilderProxy
    {
        $builder = new proxies\ScalarSubqueryBuilderProxy($this, $this->definition, $select);
        $this->addProxy($builder);

        return $builder;
    }

    /**
     * Adds expression(s) to the list of columns returned by a SELECT statement
     *
     * @return $this
     * @deprecated Since 0.9.0: use {@see returningExpression()} for both SELECT and data-modifying statements
     */
    public function outputExpression(string|Condition $expression, ?string $alias = null): self
    {
        return $this->returningExpression($expression, $alias);
    }

    /**
     * Adds expression(s) to the list returned by a SELECT statement or by RETURNING clause of DELETE / INSERT / UPDATE
     *
     * @param array<string, mixed> $parameters
     * @return $this
     */
    public function returningExpression(
        string|Condition $expression,
        ?string $alias = null,
        array $parameters = []
    ): self {
        if ($expression instanceof Condition) {
            return $this->add(new ConditionAppender(
                [] === $parameters ? $expression : new ParametrizedCondition($expression, $parameters),
                $alias
            ));

        } elseif ([] === $parameters) {
            return $this->add(new SqlStringAppender($this->tableLocator->getParser(), $expression, $alias));

        } else {
            return $this->addWithParameters(
                new SqlStringAppender($this->tableLocator->getParser(), $expression, $alias),
                $parameters
            );
        }
    }

    /**
     * Adds a join to the given table
     *
     * The method will try to call `onForeignKey()` method of builder if $joined contains table metadata (i.e. is not
     * an SQL string). If an Exception is thrown in that call (due to missing / ambiguous FK), it will be silenced
     * and the join will remain unconditional.
     *
     * @return proxies\JoinBuilderProxy<static>
     */
    public function join(string|TableName|QualifiedName|TableGateway|SelectBuilder $joined): proxies\JoinBuilderProxy
    {
        $normalized = $this->normalizeSelect($joined);
        $builder    = new proxies\JoinBuilderProxy($this, $this->definition, $normalized);
        $this->addProxy($builder);

        if ($normalized instanceof TableAccessor) {
            try {
                $builder->onForeignKey();
            } catch (Exception) {
            }
        }

        return $builder;
    }

    /**
     * Adds a `[NOT] EXISTS(...)` condition
     *
     * @return proxies\ExistsBuilderProxy<static>
     */
    public function exists(
        string|TableName|QualifiedName|TableGateway|SelectBuilder $select
    ): proxies\ExistsBuilderProxy {
        $builder = new proxies\ExistsBuilderProxy($this, $this->definition, $this->normalizeSelect($select));
        $this->addProxy($builder);

        return $builder;
    }

    /**
     * Tries to convert a parameter passed to `join()` or `exists()` to SelectBuilder
     */
    private function normalizeSelect(string|TableName|QualifiedName|TableGateway|SelectBuilder $select): SelectBuilder
    {
        if (\is_string($select)) {
            return new SqlStringSelectBuilder($this->tableLocator->getParser(), $select);
        } elseif ($select instanceof QualifiedName || $select instanceof TableName) {
            return $this->tableLocator->createGateway($select)
                ->select();
        } elseif ($select instanceof TableGateway) {
            return $select->select();
        } else {
            return $select;
        }
    }

    /**
     * Adds a [part of] WITH clause represented by an SQL string
     *
     * The string may contain either a complete WITH clause `WITH foo AS (...)`, possibly with multiple CTEs,
     * or a single CTE `foo AS (...)`
     *
     * @param array<string, mixed> $parameters
     * @return $this
     */
    public function withSqlString(string $sql, array $parameters = [], int $priority = Fragment::PRIORITY_DEFAULT): self
    {
        return $this->add(new SqlStringFragment($this->tableLocator->getParser(), $sql, $parameters, $priority));
    }

    /**
     * Adds a SELECT to the WITH clause
     *
     * @return proxies\WithClauseBuilderProxy<static>
     */
    public function withSelect(SelectProxy $select, string $alias): proxies\WithClauseBuilderProxy
    {
        $builder = new proxies\WithClauseBuilderProxy($this, $select, $alias);
        $this->addProxy($builder);

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
    public function orderBy(string|iterable $orderBy): self
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
    public function orderByUnsafe(string|iterable $orderBy): self
    {
        return $this->add(new OrderByClauseFragment($this->tableLocator->getParser(), $orderBy, false));
    }

    /**
     * Adds the `LIMIT` clause to a `SELECT` query
     *
     * The actual value for `LIMIT` is not embedded into SQL, but passed as a query parameter
     *
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
     * @return $this
     */
    public function offset(int $offset): self
    {
        return $this->add(new OffsetClauseFragment($offset));
    }
}
