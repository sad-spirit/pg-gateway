.. _fragment-builders:

=============================
Configuring complex Fragments
=============================

Several classes in the package implement the ``FragmentBuilder`` interface. However, only some of these provide
methods to configure the built fragments and are described in this chapter.

Instances of these classes or of their proxy subclasses are created in
:ref:`methods of FluentBuilder <default-builder-api>` and can be configured via methods described here.

.. _fragment-builders-columns:

``builders\ColumnsBuilder``
===========================

This configures what columns of the table accessed via gateway will be returned in the output list of ``SELECT``
or in the ``RETURNING`` clause of ``DELETE`` / ``INSERT`` / ``UPDATE``.

.. code-block:: php

    namespace sad_spirit\pg_gateway\builders;

    use sad_spirit\pg_gateway\{
       Fragment,
       FragmentBuilder
    };
    use sad_spirit\pg_gateway\fragments\target_list\ColumnAliasStrategy;

    class ColumnsBuilder implements FragmentBuilder
    {
        public function __construct(TableDefinition $definition);

        // defined in FragmentBuilder
        public function getFragment() : Fragment;

        // methods that configure the list of returned columns
        public function none() : $this;
        public function star() : $this;
        public function all() : $this;
        public function only(string[] $onlyColumns) : $this;
        public function except(string[] $exceptColumns) : $this;
        public function primaryKey() : $this;

        // methods that configure aliases for returned columns
        public function alias(ColumnAliasStrategy $strategy) : $this;
        public function replace(string|string[] $pattern, string|string[] $replacement) : $this;
        public function map(array<string, string> $columnMap) : $this;
        public function apply(\Closure $closure, ?string $key = null) : $this;
    }

``none()``
    Removes all columns with ``self`` as relation name from the list
    (this will be a no-op if applied to ``RETURNING``).
``star()``
    Replaces all such columns with ``self.*`` shorthand (this is will be a no-op if applied to ``SELECT``).

No aliases are possible with these methods.

Four other methods replace all columns having ``self`` as relation name with an explicit list of columns:

``all()``
    Lists all the table columns.
``only()``
    Lists only the given ones.
``except()``
    All columns *except* the given ones.
``primaryKey()``
    Columns that belong to the table's primary key.

With the latter methods, it is possible to assign aliases to columns:

``alias()``
    Uses a custom implementation of ``ColumnAliasStrategy``.
``replace()``
    Will essentially run ``preg_replace`` on column names using the given arguments
    (see ``fragments\target_list\alias_strategies\PregReplaceStrategy``).
``map()``
    Will try to find aliases in the explicitly provided map ``['column name' => 'alias']``
    (see ``fragments\target_list\alias_strategies\MapStrategy``).
``apply()`` will call the given ``$closure`` with a column name and use the result as an alias
   (see ``fragments\target_list\alias_strategies\ClosureStrategy``). Giving a non-null ``$key`` that somehow identifies
   the given ``$closure`` will allow caching a query that uses this strategy.

If a strategy returns ``null`` or unmodified column name for a given column then that column will be left without alias.

.. _fragment-builders-exists:

``builders\ExistsBuilder``
==========================

As there is currently no ``ConditionBuilder`` interface, this is essentially a builder for ``WhereClauseFragment``
which happens to also implement a ``getCondition()`` method. Getting an unwrapped ``Condition`` may be useful if e.g.
you need to combine it via ``AND`` / ``OR`` with other conditions.

.. code-block:: php

    namespace sad_spirit\pg_gateway\builders;

    use sad_spirit\pg_gateway\{
        Condition,
        Fragment,
        SelectBuilder,
        TableDefinition
    };

    class ExistsBuilder extends AdditionalSelectBuilder
    {
        // defined in FragmentBuilder
        public function getFragment() : Fragment;

        // inherited from AdditionalSelectBuilder
        public function __construct(TableDefinition $base, SelectBuilder $additional);
        public function alias(string $alias) : $this;

        // returns the Condition
        public function getCondition() : Condition;

        // methods that configure join condition with the base table
        public function joinOn(Condition $condition) : $this;
        public function joinOnForeignKey(string[] $keyColumns = []) : $this;
        public function joinOnRecursiveForeignKey(bool $fromChild = true, array $keyColumns = []) : $this;

        // other configuration methods
        public function not() : $this;
    }


``alias()``
    Specifies an explicit alias for the table inside ``EXISTS(...)``, a generated one will be used if not given.
``not()``
    Toggles generation of ``NOT EXISTS(...)`` condition.

Join configuration
------------------

Methods that configure joins are mostly similar in all the classes that extend ``AdditionalSelectBuilder``.

``joinOn()`` uses custom join ``Condition``. As usual, ``self`` alias in that should reference the ``$base`` table and
``joined`` alias should reference the ``$additional`` table being joined (i.e. the one inside ``EXISTS(...)``).

``joinOnForeignKey()`` is used to join two *different* tables using a ``FOREIGN KEY`` constraint between them.
If there are multiple ``FOREIGN KEY`` constraints between tables, ``$keyColumns`` can be given to select the one
containing these columns on child side. For example, given the following schema

.. code-block:: postgres

    create table example.employees (
        id   integer not null generated by default as identity,
        name text not null,

        constraint employees_pkey primary key (id)
    );

    create table example.documents (
        id          integer not null generated by default as identity,
        author_id   integer not null,
        approver_id integer,
        contents    text not null,

        constraint documents_pkey primary key (id),
        constraint documents_author_fkey foreign key (author_id)
            references example.employees (id),
        constraint documents_approval_fkey foreign key (approver_id)
            references example.employees (id)
    );


the following code

.. code-block:: php

    use sad_spirit\pg_gateway\builders\FluentBuilder;

    $gwEmployees = $locator->createGateway('example.employees');

    // selects all employees who authored documents
    $selectAuthor = $gwEmployees->select(fn (FluentBuilder $builder) => $builder
        ->exists($locator->createGateway('example.documents'))
            ->joinOnForeignKey(['author_id']));

    echo $selectAuthor->createSelectStatement()->getSql() . ";\n\n";

    // selects all employees who approved documents
    $selectApprover = $gwEmployees->select(fn (FluentBuilder $builder) => $builder
        ->exists($locator->createGateway('example.documents'))
            ->joinOnForeignKey(['approver_id']));

    echo $selectApprover->createSelectStatement()->getSql() . ";\n\n";

will output something similar to

.. code-block:: postgres

    select self.*
    from example.employees as self
    where exists(
            select 1
            from example.documents as gw_1
            where gw_1.author_id = self.id
        );

    select self.*
    from example.employees as self
    where exists(
            select 1
            from example.documents as gw_2
            where gw_2.approver_id = self.id
        );


``joinOnRecursiveForeignKey()`` performs a self-join using a recursive foreign key (i.e. this should be used if
``$base`` and ``$additional`` reference the same table).
``$fromChild`` specifies whether base table is on the child side of join or the parent one.
For example, given the following table

.. code-block:: postgres

    create table example.tree (
        id   integer not null generated by default as identity,
        parent_id integer,
        name text not null,

        constraint tree_pkey primary key (id),
        constraint tree_parent_fkey foreign key (parent_id)
            references example.tree (id)
    );

the following code

.. code-block:: php

    use sad_spirit\pg_gateway\builders\FluentBuilder;

    $gwTree = $locator->createGateway('example.tree');

    // selects all items having a parent (this is of course achieved easier with `parent_id IS NOT NULL`)
    $selectChild = $gwTree->select(fn (FluentBuilder $builder) => $builder
        ->exists($gwTree)
            ->joinOnRecursiveForeignKey(true));

    echo $selectChild->createSelectStatement()->getSql() . ";\n\n";

    // selects all items having children
    $selectParent = $gwTree->select(fn (FluentBuilder $builder) => $builder
        ->exists($gwTree)
            ->joinOnRecursiveForeignKey(false));

    echo $selectParent->createSelectStatement()->getSql() . ";\n\n";

will output something similar to

.. code-block:: postgres

    select self.*
    from example.tree as self
    where exists(
            select 1
            from example.tree as gw_1
            where self.parent_id = gw_1.id
        );

    select self.*
    from example.tree as self
    where exists(
            select 1
            from example.tree as gw_2
            where gw_2.parent_id = self.id
        );

``$keyColumns`` serve the same purpose as in ``joinOnForeignKey()``, in the unlikely scenario that there are
multiple recursive ``FOREIGN KEY`` constraints defined.

.. _fragment-builders-join:

``builders\JoinBuilder``
========================

This configures joining an object that implements ``SelectBuilder`` to the current statement.

.. code-block:: php

    namespace sad_spirit\pg_gateway\builders;

    use sad_spirit\pg_gateway\{
        Condition,
        Fragment,
        SelectBuilder,
        TableDefinition,
        fragments\JoinStrategy
    };

    class JoinBuilder extends AdditionalSelectBuilder
    {
        // defined in FragmentBuilder
        public function getFragment() : Fragment;

        // inherited from AdditionalSelectBuilder
        public function __construct(TableDefinition $base, SelectBuilder $additional);
        public function alias(string $alias) : $this;

        // methods that configure the type of join being made
        public function strategy(JoinStrategy $strategy) : $this;
        public function inline() : $this;
        public function inner() : $this;
        public function left() : $this;
        public function right() : $this;
        public function full() : $this;
        public function lateral() : $this;
        public function lateralInner() : $this;
        public function lateralLeft() : $this;

        // methods that configure join condition with the base table
        public function on(Condition $condition) : $this;
        public function onForeignKey(string[] $keyColumns = []) : $this;
        public function onRecursiveForeignKey(bool $fromChild = true, string[] $keyColumns = []) : $this;
        public function unconditional() : $this;

        // other configuration methods
        public function priority(int $priority) : $this;
        public function useForCount(bool $use) : $this;
    }

Actual merging of the ``$additional`` to the ``$base`` is performed by an implementation of ``fragments\JoinStrategy``.

``strategy()``
    Uses a custom implementation of ``JoinStrategy``.
``inline()``
    Adds the joined table a separate item of the base statement's ``FROM`` (or ``USING``) clause
    (see ``fragments\join_strategies\InlineStrategy``). This is the only strategy that works with ``UPDATE`` and ``DELETE``,
    using the ``employees`` / ``documents`` schema above, the following code

    .. code-block:: php

        $gwDocuments = $locator->createGateway('example.documents');
        $delete      = $gwDocuments->createDeleteStatement(
            $locator->createBuilder('example.documents')
                ->join(
                    $locator->createGateway('example.employees')
                        ->selectByPrimaryKey(1)
                )
                    ->inline()
                ->getFragment()
        );

        echo $delete->getSql();

    will output something like

    .. code-block:: postgres

        delete from example.documents as self
        using example.employees as gw_1
        where gw_1.id = $1::int4

``inner()``, ``left()``, ``right()``, and ``full()``
    These are backed by ``fragments\join_strategies\ExplicitJoinStrategy``,
    they join the ``$additional`` to the ``$base`` table using the explicit ``JOIN`` clause with the condition
    as its ``ON`` clause. ``$additional`` may be wrapped in a subquery if it contains complex clauses.
``lateral()``, ``lateralInner()``, and ``lateralLeft()``
    These are backed by ``fragments\join_strategies\LateralSubselectStrategy``, they wrap the ``$additional`` into
    the ``LATERAL`` subquery and either put it as a separate ``FROM`` item (``lateral()``) or join to the ``$base``
    using ``INNER`` or ``LEFT`` join.

    The main difference to the previous strategies is that the condition will be added to
    the ``WHERE`` clause of subquery rather than to the ``ON`` clause of ``JOIN``.

.. note::

    ``inline()`` is the default join strategy.

The join condition is configured the same way as in ``ExistsBuilder`` above, ``unconditional()`` method is used
to explicitly state that no join condition is used, as ``FluentBuilder::join()`` will try to join
on a foreign key by default.

``priority()``
    Controls the order in which joins will be applied, this is especially useful for ``LATERAL`` joins.
    ``Fragment``\ s having the higher priority will be applied earlier.
``useForCount()``
    Controls whether the join will be performed in ``SELECT COUNT(*)`` query executed by
    ``SelectProxy::executeCount()``. A join that does not modify the number of returned rows can be safely skipped.

.. _fragment-builders-scalar:

``builders\ScalarSubqueryBuilder``
==================================

This behaves as a ``FragmentBuilder`` for an instance of ``TargetListFragment``, adding the query generated
by ``$additional`` to the output list of ``SELECT`` or (less probably) to ``RETURNING`` clause of another statement.

As `Postgres docs state <https://www.postgresql.org/docs/current/sql-expressions.html#SQL-SYNTAX-SCALAR-SUBQUERIES>`__

    A scalar subquery is an ordinary ``SELECT`` query in parentheses that returns exactly one row with one column.

However, it is possible to put ``SELECT`` in an ``ARRAY`` constructor to return more than one row and the type of
that single column can be composite (row) type.

.. code-block:: php

    namespace sad_spirit\pg_gateway\builders;

    class ScalarSubqueryBuilder extends AdditionalSelectBuilder
    {
        // defined in FragmentBuilder
        public function getFragment() : Fragment;

        // inherited from AdditionalSelectBuilder
        public function __construct(TableDefinition $base, SelectBuilder $additional);
        public function alias(string $alias) : $this;

        // methods that configure join condition with the base table
        public function joinOn(Condition $condition) : $this;
        public function joinOnForeignKey(string[] $keyColumns = []) : $this;
        public function joinOnRecursiveForeignKey(bool $fromChild = true, array $keyColumns = []) : $this;

        // alias methods
        public function tableAlias(string $alias) : $this;
        public function columnAlias(string $alias) : $this;

        // helpers for making the result not-quite-scalar
        public function asArray() : $this;
        public function returningRow() : $this;
    }

The somewhat new alias methods are

``tableAlias()``
    This is actually a synonym for ``alias()``, added to differentiate from ``columnAlias()``.
``columnAlias()``
    Sets the alias for subquery expression in the ``TargetList``, ``(SELECT ...) as $alias``.

The following methods essentially allow returning several rows and columns

``asArray()``
    The subquery will be wrapped in an ``ARRAY()`` constructor, allowing it to return more than one row.
``returningRow()``
    Column list of the subquery will be replaced by a ``ROW()`` constructor containing the same columns, e.g.
    ``SELECT foo, bar, baz`` will be changed to ``SELECT ROW(foo, bar, baz)``. The downside is that it will be
    necessary to specify the names and types for that row structure.

.. _fragment-builders-with:

``builders\WithClauseBuilder``
==============================

This is actually used only for ``fragments\with\SelectProxyFragment`` subclass of ``fragments\WithClauseFragment``,
its proxy subclass is returned by ``FluentBuilder::withSelect()``.

.. code-block:: php

    namespace sad_spirit\pg_gateway\builders;

    use sad_spirit\pg_gateway\{
        Fragment,
        FragmentBuilder,
        SelectProxy
    };

    class WithClauseBuilder implements FragmentBuilder
    {
        public function __construct(SelectProxy $select, string $alias);

        // defined in FragmentBuilder
        public function getFragment() : Fragment;

        public function columnAliases(array $aliases) : $this;
        public function materialized() : $this;
        public function notMaterialized() : $this;
        public function recursive() : $this;
        public function priority(int $priority) : $this;
    }

.. note::

    The package will not generate an alias for a query in ``WITH``, so an alias should always be passed to constructor.

``recursive()``
    Enables the ``RECURSIVE`` option for the ``WITH`` clause.
``materialized()`` / ``notMaterialized()``
    Enable ``[NOT] MATERIALIZED`` options for the CTE.
``columnAliases()``
    Sets the column aliases for the CTE.
``priority()``
    Sets the ``Fragment``'s priority: without ``RECURSIVE`` queries in ``WITH`` can only reference their
    previous siblings, so priority may be important.

Proxy subclasses
================

All the classes described above have subclasses in ``builders\proxies`` namespace. Those subclasses proxy the methods
of ``FluentBuilder`` instance that returns them allowing to seamlessly chain method calls. They also implement
the ``Proxy`` interface:

.. code-block:: php

    namespace sad_spirit\pg_gateway\builders;

    use sad_spirit\pg_gateway\Fragment;
    use sad_spirit\pg_gateway\FragmentBuilder;

    interface Proxy extends FragmentBuilder
    {
        public function getOwnFragment(): Fragment;
    }

``getOwnFragment()`` method returns the fragment created by the builder itself, while ``getFragment()`` method returns
the fragment built by the proxied instance of ``FluentBuilder``. This way methods defined in ``TableGateway``
need not care whether they receive an instance of ``FluentBuilder`` or an instance of ``Proxy``.
