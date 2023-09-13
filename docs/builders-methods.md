# Builder methods of default gateways

As query methods of `TableGateway` usually accept implementations of `Fragment` and `FragmentBuilder`, methods to
simplify creation of such objects were also added to default gateway implementations.

## Builder methods of `GenericTableGateway`

```PHP
namespace sad_spirit\pg_gateway\gateways;

use sad_spirit\pg_gateway\builders\{
    ColumnsBuilder,
    ExistsBuilder,
    JoinBuilder,
    ScalarSubqueryBuilder
};
use sad_spirit\pg_gateway\conditions\{
    NotCondition,
    ParametrizedCondition,
    column\BoolCondition,
    column\IsNullCondition
};
use sad_spirit\pg_gateway\fragments\{
    ReturningClauseFragment,
    SelectListFragment
};

class GenericTableGateway implements TableGateway
{
    // creating Conditions
    public function any(string $column, array $values) : ParametrizedCondition;
    public function column(string $column) : BoolCondition;
    public function notColumn(string $column) : NotCondition;
    public function isNull(string $column) : IsNullCondition;
    public function isNotNull(string $column) : NotCondition;
    public function notAll(string $column, array $values) : ParametrizedCondition;
    public function operatorCondition(string $column, string $operator, mixed $value) : ParametrizedCondition;
    public function equal(string $column, $value) : ParametrizedCondition;
    public function sqlCondition(string $sql, array $parameters = []) : ParametrizedCondition;
    public function exists(string|QualifiedName|TableGateway|SelectProxy $select) : ExistsBuilder;

    // creating fragments that modify the output expressions list
    public function outputColumns() : ColumnsBuilder;
    public function returningColumns() : ColumnsBuilder;
    public function outputSubquery(SelectProxy $select) : ScalarSubqueryBuilder;
    public function outputExpression(string|Condition $expression, ?string $alias = null) : SelectListFragment;
    public function returningExpression(string|Condition $expression, ?string $alias = null) : ReturningClauseFragment;
    
    // creating a builder for joins
    public function join(string|QualifiedName|TableGateway|SelectProxy $joined) : JoinBuilder;

    // fragments for SELECT statements
    public function orderBy(iterable<OrderByElement|string>|string $orderBy) : OrderByClauseFragment;
    public function orderByUnsafe(iterable<OrderByElement|string>|string $orderBy) : OrderByClauseFragment;
    public function limit(int $limit) : LimitClauseFragment;
    public function offset(int $offset) : OffsetClauseFragment;
}
```

### Creating `Condition` instances

Base `Condition` class implements `FragmentBuilder` interface, so objects returned by these methods can be
directly passed to the query methods of `TableGateway`:
```PHP
$gateway->select($gateway->any('field', [1, 2, 3]));

$gateway->delete([
    $gateway->equal('foo', 1),
    $gateway->isNull('option')
]);
```
When added in that way, `Condition`s will be applied to the `WHERE` clause of the query.

The above methods generate the following SQL
 * `any()` - generates `self.column = any(:column::column_type[])`. 
   Returned `ParametrizedCondition` decorates `conditions\column\AnyCondition` here,  allowing to pass `$values`
   together with condition rather than separately in `$parameters` argument to a query method.
 * `column()` - generates `self.column` using a column of `bool` type.
 * `notColumn()` - generates `NOT self.column`, returned `NotCondition` decorates `BoolCondition`.
 * `isNull()` - generates `self.column IS NULL`.
 * `isNotNull()` - generates `self.column IS NOT NULL`, returned `NotCondition` decorates `IsNullCondition`.
 * `notAll()` - generates `self.column <> all(:column::column_type[])`, returned `ParametrizedCondition`
   decorates `conditions\column\NotAllCondition`.
 * `operatorCondition()` - generates `self.column <OPERATOR> :column::column_type`, returned `ParametrizedCondition`
   decorates `conditions\column\OperatorCondition`.
 * `equal()` - generates a `self.column = :column::column_type`, returned `ParametrizedCondition`
   decorates `OperatorCondition`.
 * `sqlCondition()` - embeds manually written SQL as a condition, returned `ParametrizedCondition`
   decorates `conditions\SqlStringCondition`.
 * `exists()` - returns a [builder for configuring a `[NOT] EXISTS(...)` condition](./builders-classes.md).
   If the argument is a string or an instance of `QualifiedName` it is treated as a table name,
   a gateway is located for that table and `select()`ed from.
   If the argument is already a `TableGateway` instance then an unconditional `select()` is done. 

Note that all the methods that accept column values do not embed them into SQL, passing them on instead 
via `ParametrizedCondition` decorator. This way the generated SQL does not depend on specific parameter values
and may be eventually reused with other values.

While `sqlCondition()` accepts an SQL string, this won't of course be inserted verbatim into
the generated SQL, e.g.
```PHP
$condition = $gateway->sqlCondition(
    'case when self.foo @@ :foo::foo_type then self.bar else false end',
    ['foo' => $fooValue]
)
```
will have the special `self` alias replaced as needed, also named `:foo` placeholder will be converted
to positional one and its type info extracted and used to properly convert the given `$fooValue`.

### Creating fragments that modify the output expressions list

 * `outputColumns()` - [creates a Builder for configuring a list of columns](./builders-classes.md) returned by
   a `SELECT` statement.
 * `returningColumns()` - creates a Builder for configuring a list of columns in the `RETURNING`
   clause.
 * `outputSubquery()` - creates a Builder for configuring
   a scalar subquery to be added to the output list of a `SELECT` statement.
 * `outputExpression()` - adds expression(s) to the list of columns returned by a `SELECT` statement.
 * `returningExpression()` - adds expression(s) to the list of columns in the `RETURNING` clause.


### Creating a builder for joins

* `join()` - [creates a Builder for configuring a join](./builders-classes.md) to the given table.
   The argument has the same semantics as for `exists()` method described above.

### Fragments for `SELECT` statements

 * `orderBy()` / `orderByUnsafe()` - these create fragments that set the `ORDER BY` list of a `SELECT` query
   to the given expressions, the difference being that the former allows only column names and ordinal numbers
   as expressions while the latter allows everything. The reasoning is that sort options are often coming from
   user input and due to SQL language structure should be embedded in the query without the means to use
   some parameter-like constructs. "Unsafe" in the method name is a huge hint not to pass user input. 
 * `limit()` creates a fragment adding the `LIMIT` clause. Note that the given `$limit` value will not actually
   be embedded in SQL but passed as a parameter value.
 * `offset()` creates a fragment adding the `OFFSET` clause. `$offset` parameter is also not embedded in SQL.

## Builder methods of `PrimaryKeyTableGateway`

`PrimaryKeyTableGateway` defines one additional builder method:
```PHP
namespace sad_spirit\pg_gateway\gateways;

use sad_spirit\pg_gateway\conditions\ParametrizedCondition;

class PrimaryKeyTableGateway extends GenericTableGateway implements PrimaryKeyAccess
{
    public function primaryKey(mixed $value) : ParametrizedCondition;
}
```
 
The returned `ParametrizedCondition` decorates `conditions\PrimaryKeyCondition`.

It can be used to combine the condition on table's primary key with some additional fragments, as shorthand
methods of `PrimaryKeyAccess` do not accept these:

```PHP
$select = $gateway->selectByPrimaryKey(1);
```
vs
```PHP
$select = $gateway->select([
    $gateway->primaryKey(1),
    $gateway->outputExpression('foo @@@ bar as foobar')
]);
```

