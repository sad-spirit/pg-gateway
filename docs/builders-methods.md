# Fluent builder objects

`TableLocator::createBuilder()` returns an object that is a subclass of `builders\FragmentListBuilder`:
```PHP
namespace sad_spirit\pg_gateway\builders;

use sad_spirit\pg_gateway\{
    Fragment,
    FragmentBuilder,
    FragmentList,
    TableDefinition,
    TableLocator
};

abstract class FragmentListBuilder implements FragmentBuilder
{
    public function __construct(TableDefinition $definition, TableLocator $tableLocator);

    final public function getFragment() : FragmentList;
    final public function add(Fragment|FragmentBuilder $fragment) : $this;
}
```
It is configured with an instance of `TableDefinition` and thus creates fragments suitable for a specific table.
These are usually added immediately to an instance of `FragmentList` which is eventually returned
from `getFragment()` method. The methods in child classes are supposed to return `$this`,
allowing to chain method calls.


## Default builder class, `FluentBuilder`

As is the case with gateways, if `TableLocator` is not configured with gateway factories or if neither of those
returns a specific object from its `createBuilder()` method, a default implementation is returned:

```PHP
namespace sad_spirit\pg_gateway\builders;

use sad_spirit\pg_gateway\{
    Condition,
    SelectProxy,
    TableGateway,
    metadata\TableName
};
use sad_spirit\pg_gateway\conditions\{
    NotCondition,
    ParametrizedCondition,
    column\BoolCondition,
    column\IsNullCondition
};
use sad_spirit\pg_builder\nodes\{
    OrderByElement,
    QualifiedName
};

class FluentBuilder extends FragmentListBuilder
{
    // Non-fluent methods for creating Conditions
    public function createAny(string $column, array $values) : ParametrizedCondition;
    public function createBoolColumn(string $column) : BoolCondition;
    public function createNotBoolColumn(string $column) : NotCondition;
    public function createIsNull(string $column) : IsNullCondition;
    public function createIsNotNull(string $column) : NotCondition;
    public function createNotAll(string $column, array $values) : ParametrizedCondition;
    public function createOperatorCondition(string $column, string $operator, mixed $value) : ParametrizedCondition;
    public function createEqual(string $column, $value) : ParametrizedCondition;
    public function createSqlCondition(string $sql, array $parameters = []) : ParametrizedCondition;
    public function createExists(string|TableName|QualifiedName|TableGateway|SelectProxy $select) : ExistsBuilder;
    public function createPrimaryKey(mixed $value) : ParametrizedCondition;

    // Immediately adding Conditions to the list
    public function any(string $column, array $values) : $this;
    public function boolColumn(string $column) : $this;
    public function notBoolColumn(string $column) : $this;
    public function isNull(string $column) : $this;
    public function isNotNull(string $column) : $this;
    public function notAll(string $column, array $values) : $this;
    public function operatorCondition(string $column, string $operator, mixed $value) : $this;
    public function equal(string $column, $value) : $this;
    public function sqlCondition(string $sql, array $parameters = []) : $this;
    public function exists(string|TableName|QualifiedName|TableGateway|SelectProxy $select, callable(ExistsBuilder) $callback = null) : $this;
    public function primaryKey(mixed $value) : $this;

    // Adding fragments that modify the output expressions list
    public function outputColumns(callable(ColumnsBuilder) $callback = null) : $this;
    public function returningColumns(callable(ColumnsBuilder) $callback = null) : $this;
    public function outputSubquery(SelectProxy $select, callable(ScalarSubqueryBuilder) $callback = null) : $this;
    public function outputExpression(string|Condition $expression, ?string $alias = null) : $this;
    public function returningExpression(string|Condition $expression, ?string $alias = null) : $this;
    
    // Adding a join
    public function join(string|TableName|QualifiedName|TableGateway|SelectProxy $joined, callable(JoinBuilder) $callback = null) : $this;

    // Adding fragments to SELECT statements
    public function orderBy(iterable<OrderByElement|string>|string $orderBy) : $this;
    public function orderByUnsafe(iterable<OrderByElement|string>|string $orderBy) : $this;
    public function limit(int $limit) : $this;
    public function offset(int $offset) : $this;
}
```

### Creating vs. adding `Condition` instances

As seen above, there are two groups of methods for `Condition`s: methods in the first group return
an instance of `Condition` and those in the second group just add that `Condition` to the list (using the
methods of the first group under the hood).

The base `Condition` class implements `FragmentBuilder` interface, with its `getFragment()` method returning
a `WhereClauseFragment`, so adding a `Condition` directly to the list applies it to the `WHERE` clause
of the query using `AND`.

Therefore, two main reasons to use `create*()` methods are
 * `Condition` should be used in the `HAVING` clause or as the `JOIN` condition;
 * Several `Conditions` should be combined via `AND` and `OR`.

```PHP
use sad_spirit\pg_gateway\Condition;

$gateway->select(
    $builder->add(Condition::or(
        $builder->createIsNull('processed'),
        $builder->createEqual('employee_id', $currentEmployee)
    ))
);
```

### Created `Condition`s

The `create*()` methods eventually generate the following SQL
 * `createAny()` - generates `self.column = any(:column::column_type[])`. 
   Returned `ParametrizedCondition` decorates `conditions\column\AnyCondition` here,  allowing to pass `$values`
   together with condition rather than separately in `$parameters` argument to a query method.
 * `createBoolColumn()` - generates `self.column` using a column of `bool` type.
 * `createNotBoolColumn()` - generates `NOT self.column`, returned `NotCondition` decorates `BoolCondition`.
 * `createIsNull()` - generates `self.column IS NULL`.
 * `createIsNotNull()` - generates `self.column IS NOT NULL`, returned `NotCondition` decorates `IsNullCondition`.
 * `createNotAll()` - generates `self.column <> all(:column::column_type[])`, returned `ParametrizedCondition`
   decorates `conditions\column\NotAllCondition`.
 * `createOperatorCondition()` - generates `self.column <OPERATOR> :column::column_type`, returned `ParametrizedCondition`
   decorates `conditions\column\OperatorCondition`.
 * `createEqual()` - generates a `self.column = :column::column_type`, returned `ParametrizedCondition`
   decorates `OperatorCondition`.
 * `createSqlCondition()` - embeds manually written SQL as a condition, returned `ParametrizedCondition`
   decorates `conditions\SqlStringCondition`.
 * `createExists()` - returns a [builder for configuring a `[NOT] EXISTS(...)` condition](./builders-classes.md).
   If the argument is a string or an instance of `TableName` / `QualifiedName` it is treated as a table name,
   a gateway is located for that table and `select()`ed from.
   If the argument is already a `TableGateway` instance then an unconditional `select()` is done.
 * `createPrimaryKey()` (actually defined in `PrimaryKeyBuilder` trait) - similar to `createEqual()` but handles
   composite primary keys as well. The returned `ParametrizedCondition` decorates `conditions\PrimaryKeyCondition`.

Note that all the methods that accept column values do not embed them into SQL, passing them on instead 
via `ParametrizedCondition` decorator. This way the generated SQL does not depend on specific parameter values
and may be reused with other values.

While `sqlCondition()` / `createSqlCondition()` accepts an SQL string, this won't of course be inserted verbatim into
the generated SQL, e.g.
```PHP
$condition = $builder->createSqlCondition(
    'case when self.foo @@ :foo::foo_type then self.bar else false end',
    ['foo' => $fooValue]
)
```
will have the special `self` alias replaced as needed, also named `:foo` placeholder will be converted
to positional one and its type info extracted and used to properly convert the given `$fooValue`.

### Methods accepting callbacks

Several of the `FluentBuilder`'s methods accept callbacks, these are used to configure builder objects that are created
in these methods. E.g. the `exists()` method exposes the `ExistsBuilder` instance:
```PHP
use sad_spirit\pg_gateway\builders\ExistsBuilder;

$builder->exists('example.stuff', fn(ExistsBuilder $eb) => $eb->not()->joinOn('self.klmn @@@ joined.klmn'));
```

### Modifying the output expressions list

 * `outputColumns()` - configures a list of columns  returned by
    a `SELECT` statement [using a ColumnsBuilder](./builders-classes.md).
 * `returningColumns()` - configures a list of columns in the `RETURNING` clause.
 * `outputSubquery()` - adds a scalar subquery to the output list of a `SELECT` statement,
    configured with `ScalarSubqueryBuilder`.
 * `outputExpression()` - adds expression(s) to the list of columns returned by a `SELECT` statement.
 * `returningExpression()` - adds expression(s) to the list of columns in the `RETURNING` clause.


### Adding joins

* `join()` - adds a join to the given table using [a Builder for configuring the join](./builders-classes.md).
   The first argument has the same semantics as for `exists()` / `createExists()` method described above,
   the second is a callback to configure the `JoinBuilder` instance:
```PHP
use sad_spirit\pg_gateway\builders\JoinBuilder;

$builder->join('example.users', fn(JoinBuilder $jb) => $jb->onForeignKey(['editor_id'])->left()->alias('editors'))
```

### Fragments for `SELECT` statements

 * `orderBy()` / `orderByUnsafe()` - these add fragments that set the `ORDER BY` list of a `SELECT` query
   to the given expressions, the difference being that the former allows only column names and ordinal numbers
   as expressions while the latter allows everything. The reasoning is that sort options are often coming from
   user input and due to SQL language structure should be embedded in the query without the means to use
   some parameter-like constructs. "Unsafe" in the method name is a huge hint not to pass user input. 
 * `limit()` adds a fragment applying the `LIMIT` clause. Note that the given `$limit` value will not actually
   be embedded in SQL but passed as a parameter value.
 * `offset()` add a fragment applying the `OFFSET` clause. `$offset` parameter is also not embedded in SQL.
