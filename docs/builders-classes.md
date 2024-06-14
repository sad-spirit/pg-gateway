# `FragmentBuilder` implementations

While `Condition` and `TargetListManipulator` do implement the `FragmentBuilder` interface,
their subclasses do not provide means to configure `Fragment`s returned by
the `getFragment()` method. Only the builders that have special configuration methods are listed below.

Instances of these classes are created in some [`FluentBuilder` methods](./builders-methods.md) and can be configured via callbacks passed
to these methods.

### `ColumnsBuilder`

This configures what columns of the table accessed via gateway will be returned in the output list of `SELECT`
or in the `RETURNING` clause of `DELETE` / `INSERT` / `UPDATE`.

```PHP
namespace sad_spirit\pg_gateway\builders;

use sad_spirit\pg_gateway\{
   Fragment,
   FragmentBuilder
};
use sad_spirit\pg_gateway\fragments\target_list\ColumnAliasStrategy;

class ColumnsBuilder implements FragmentBuilder
{
    public function __construct(TableDefinition $definition, bool $returningClause = false);

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
```

* `none()` removes all columns with `self` as relation name from the list
  (this will be a no-op if applied to `RETURNING`),
* `star()` replaces all such columns with `self.*` shorthand
  (this is will be a no-op if applied to `SELECT`).

No aliases are possible  with these methods.

Four other methods replace all columns having `self` as relation name with an explicit list of columns:
 * `all()` lists all the table columns,
 * `only()` lists only the given ones,
 * `except()` - all columns *except* the given ones,
 * `primaryKey()` - columns that belong to the table's primary key.

With the methods above, it is possible to assign aliases to columns:
 * `alias()` uses a custom implementation of `ColumnAliasStrategy`.
 * `replace()` will essentially run `preg_replace` on column names using the given arguments
   (see `fragments\target_list\alias_strategies\PregReplaceStrategy`).
 * `map()` will try to find aliases in the explicitly provided map `['column name' => 'alias']`
   (see `fragments\target_list\alias_strategies\MapStrategy`).
 * `apply()` will call the given `$closure` with a column name and use the result as an alias
   (see `fragments\target_list\alias_strategies\ClosureStrategy`). Giving a non-null `$key` that somehow identifies
   the given `$closure` will allow caching a query that uses this strategy.

If a strategy returns `null` or unmodified column name for a given column, then that column will be left without alias.

### `ExistsBuilder`

As there is currently no `ConditionBuilder` interface, this is essentially a builder for `WhereClauseFragment`
which happens to also implement a `getCondition()` method. Getting an unwrapped `Condition` may be useful if e.g.
you need to combine it via `AND` / `OR` with other conditions.

```PHP
namespace sad_spirit\pg_gateway\builders;

class ExistsBuilder extends AdditionalSelectBuilder
{
    // defined in FragmentBuilder
    public function getFragment() : Fragment;

    // inherited from AdditionalSelectBuilder
    public function __construct(TableDefinition $base, SelectProxy $additional);
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
```

Methods that configure joins are mostly similar in all the classes that extend `AdditionalSelectBuilder`.
`joinOn()` uses custom join `Condition`. As usual, `self` alias in that should reference the `$base` table and
`joined` alias should reference the `$additional` table being joined (i.e. the one in `EXISTS(...)`).
 
`joinOnForeignKey()` is used to join two *different* tables using a `FOREIGN KEY` constraint between them. 
If there are multiple `FOREIGN KEY` constraints between tables, `$keyColumns` can be given to select the one
containing these columns on child side. For example, given the following schema
```SQL
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
```
the following code
```PHP
use sad_spirit\pg_gateway\builders\ExistsBuilder;

$gwEmployees = $locator->createGateway('example.employees');

// selects all employees who authored documents 
$selectAuthor = $gwEmployees->select(
    $locator->createBuilder('example.employees')
        ->exists('example.documents', fn(ExistsBuilder $eb) => $eb->joinOnForeignKey(['author_id']))
);

echo $selectAuthor->createSelectStatement()->getSql() . "\n\n";

// selects all employees who approved documents 
$selectApprover = $gwEmployees->select(
    $locator->createBuilder('example.employees')
        ->exists('example.documents', fn(ExistsBuilder $eb) => $eb->joinOnForeignKey(['approver_id']))
);

echo $selectApprover->createSelectStatement()->getSql() . "\n\n";
```
will output something similar to
```SQL
select self.*
from example.employees as self
where exists(
        select 1
        from example.documents as gw_1
        where gw_1.author_id = self.id
    )

select self.*
from example.employees as self
where exists(
        select 1
        from example.documents as gw_2
        where gw_2.approver_id = self.id
    )
```


`joinOnRecursiveForeignKey()` performs a self-join using a recursive foreign key (i.e. this should be used if 
`$base` and `$additional` reference the same table). `$fromChild` specifies whether base table is on the child side
of join or the parent one. For example, given the following table
```SQL
create table example.tree (
    id   integer not null generated by default as identity,
    parent_id integer,
    name text not null,

    constraint tree_pkey primary key (id),
    constraint tree_parent_fkey foreign key (parent_id)
        references example.tree (id)
);
```
the following code
```PHP
use sad_spirit\pg_gateway\builders\ExistsBuilder;

$gwTree = $locator->createGateway('example.tree');

// selects all items having a parent (this is of course achieved easier with `parent_id IS NOT NULL`)
$selectChild = $gwTree->select(
    $locator->createBuilder('example.tree')
        ->exists(fn(ExistsBuilder $eb) => $eb->joinOnRecursiveForeignKey(true))
);

echo $selectChild->createSelectStatement()->getSql() . "\n\n";

// selects all items having children
$selectParent = $gwTree->select(
    $locator->createBuilder('example.tree')
        ->exists(fn(ExistsBuilder $eb) => $eb->joinOnRecursiveForeignKey(false))
);

echo $selectParent->createSelectStatement()->getSql();
```
will output something similar to
```SQL
select self.*
from example.tree as self
where exists(
        select 1
        from example.tree as gw_1
        where self.parent_id = gw_1.id
    )

select self.*
from example.tree as self
where exists(
        select 1
        from example.tree as gw_2
        where gw_2.parent_id = self.id
    )
```
`$keyColumns` serve the same purpose as in `joinOnForeignKey()`, in the unlikely scenario that there are
multiple recursive `FOREIGN KEY` constraints defined.

`alias()` specifies an explicit alias for the table inside `EXISTS(...)`, a generated one will be used if not given.

`not()` toggles generation of `NOT EXISTS(...)` condition.

### `JoinBuilder`

This configures joining a `SelectProxy` object to the current statement.

```PHP
namespace sad_spirit\pg_gateway\builders;

use sad_spirit\pg_gateway\fragments\JoinStrategy;

class JoinBuilder extends AdditionalSelectBuilder
{
    // defined in FragmentBuilder
    public function getFragment() : Fragment;

    // inherited from AdditionalSelectBuilder
    public function __construct(TableDefinition $base, SelectProxy $additional);
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
```

Actual merging of the `$additional` to the `$base` is performed by an implementation of `JoinStrategy`.
 * `strategy()` uses a custom implementation of `JoinStrategy`.
 * `inline()` adds the joined table a separate item of the base statement's `FROM` (or `USING`) clause
   (see `fragments\join_strategies\InlineStrategy`). This is the only strategy that works with `UPDATE` and `DELETE`,
   using the `employees` / `documents` schema above, the following code
```PHP
use sad_spirit\pg_gateway\builders\JoinBuilder;

$delete = $locator->createGateway('example.documents')
    ->createDeleteStatement(
        $locator->createBuilder('example.documents')
            ->join(
                $locator->createGateway('example.employees')
                    ->selectByPrimaryKey(1),
                fn(JoinBuilder $jb) => $jb->onForeignKey()
                    ->inline()
            )
            ->getFragment()
    );

echo $delete->getSql();
```
will output something like
```SQL
delete from example.documents as self
using example.employees as gw_1
where gw_1.id = $1::int4
```

`inner()`, `left()`, `right()`, and `full()` are backed by `fragments\join_strategies\ExplicitJoinStrategy`, they
join the `$additional` to the `$base` table using the explicit `JOIN` clause with the condition as its `ON` clause.
`$additional` may be wrapped in a subquery if it contains complex clauses.

`lateral()`, `lateralInner()`, and `lateralLeft()` are backed by `fragments\join_strategies\LateralSubselectStrategy`,
they wrap the `$additional` into the `LATERAL` subquery and either put it as a separate `FROM` item (`lateral()`)
or join to the `$base` using `INNER` or `LEFT` join. The main difference to the previous strategies is that
the condition will be added to the `WHERE` clause of subquery rather than to the `ON` clause of `JOIN`.

`inline()` is the default join strategy.

The join condition is configured the same way as in `ExistsBuilder` above, `unconditional()` method is used 
to explicitly state that no join condition is used.

`priority()` controls the order in which joins will be applied, this is especially useful for `LATERAL` joins.
`Fragment`s having the higher priority will be applied earlier.

`useForCount()` controls whether the join will be performed in `SELECT COUNT(*)` query executed by
`SelectProxy::executeCount()`. A join that does not modify the number of returned rows can be safely skipped.

### `ScalarSubqueryBuilder`

This behaves as a `FragmentBuilder` returning an instance of `SelectListFragment`,
but also has a `getManipulator()` method returning an unwrapped instance of `TargetListManipulator` which can
be used in `ReturningClauseFragment`.

```PHP
namespace sad_spirit\pg_gateway\builders;

class ScalarSubqueryBuilder extends AdditionalSelectBuilder
{
    // defined in FragmentBuilder
    public function getFragment() : Fragment;

    // inherited from AdditionalSelectBuilder
    public function __construct(TableDefinition $base, SelectProxy $additional);
    public function alias(string $alias) : $this;

    // Returns the unwrapped manipulator
    public function getManipulator() : TargetListManipulator;

    // methods that configure join condition with the base table
    public function joinOn(Condition $condition) : $this;
    public function joinOnForeignKey(string[] $keyColumns = []) : $this;
    public function joinOnRecursiveForeignKey(bool $fromChild = true, array $keyColumns = []) : $this;

    // alias methods
    public function tableAlias(string $alias) : $this;
    public function columnAlias(string $alias) : $this;
}
```

The somewhat new methods are
 * `tableAlias()` - this is actually a synonym for `alias()`, added to differentiate from `columnAlias()`.
 * `columnAlias()` - sets the alias for subquery expression in the `TargetList`, `(SELECT ...) as $alias`.

### `WithClauseBuilder`

This is actually used only for `with\SelectProxyFragment` subclass of `WithClauseFragment`, its instance
can be configured by a callback passed to `FragmentBuilder::withSelectProxy()`.

```PHP
namespace sad_spirit\pg_gateway\builders;

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
```

Note that the package will not generate an alias for a query in `WITH`,
so an alias should always be passed to constructor. 

 * `recursive()` enables the `RECURSIVE` option for the `WITH` clause;
 * `materialized()` / `notMaterialized()` enable `[NOT] MATERIALIZED` options for the CTE;
 * `columnAliases()` sets the column aliases for the CTE;
 * `priority()` sets the Fragment's priority: without `RECURSIVE` queries in `WITH` can only reference their
   previous siblings, so priority may be important. 