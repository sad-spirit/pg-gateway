# `Fragment` implementations

Everything that is passed to a query method of `GenericTableGateway` will eventually be represented by
an implementation of `Fragment` and kept in a `FragmentList`.

## `FragmentList`

An instance of this class aggregates fragments used to build a query and parameter values used to execute it:
```PHP
namespace sad_spirit\pg_gateway;

class FragmentList implements SelectFragment, Parametrized, \IteratorAggregate, \Countable
{
    public static function normalize($fragments) : self;

    public function __construct(Fragment|FragmentBuilder ...$fragments);
    
    public function add(Fragment|FragmentBuilder $fragment) : $this;
    public function mergeParameters(array $parameters, ?KeyEquatable $owner = null) : $this;
    public function getParameters() : array;
    public function getSortedFragments() : Fragment[];
    public function filter(\Closure $callback) : self;
}
```

The static `normalize()` method accepts `$fragments` parameter that usually was passed to a query method
of `TableGateway` and returns an instance of `FragmentList`. `$fragments` can be either an implementation of
`Fragment` or `FragmentBuilder`, or, most commonly, iterable over `Fragment` or `FragmentBuilder` implementations.
Anything else will result in `InvalidArgumentException`.

`add()` - adds a fragment to the list. If an instance of `FragmentList`
is given, it will be "flattened" with its items added rather than the list itself. If `FragmentBuilder`
is given, the return value of its `getFragment()` method is added to the list, not the builder.

`mergeParameters()` - adds values for several named parameters.
`$owner` is used only for a possible exception message in `RecursiveParameterHolder`.
 
`getParameters()` - shorthand for
```PHP
$list->getParameterHolder()->getParameters();
```
   Note that all parameter values are returned: those that were merged into the list itself and those that belong
   to `Parametrized` fragments in the list.

`getSortedFragments()` - returns fragments sorted by priority (higher first) and key (alphabetically).
This is used by `applyTo()` to apply contained fragments in a defined order.
  
`filter()` - filters the `FragmentList` using the given callback (uses `array_filter()` internally).
`TableSelect::executeCount()` uses this to leave only relevant fragments in the list.
   
You only really need an explicit instance of `FragmentList` when you want to use `create*()` methods 
of `GenericTableGateway`. Anywhere else the `$fragments` parameter will be normalized to `FragmentList` automatically.

### `FragmentListBuilder`

An instance of `FragmentList` is populated in subclasses of `FragmentListBuilder` and eventually returned by their
`getFragment()` method. `FragmentListBuilder` is the base class for [fluent builders](./builders-methods.md) 
created by `TableLocator::createBuilder()`.

## `CustomFragment`, `CustomSelectFragment`

These abstract classes should be extended by `Fragment`s that want a custom `applyTo()` implementation.
Their constructors accept a custom `$key` that will be returned by `getKey()` methods, so statements using these
are cacheable, unlike `ClosureFragment` below.

## `ParametrizedFragment`

This is a decorator for instances of `Fragment` that also accepts an array of parameters used by that `Fragment`.

It is recommended to use this with custom `Fragment`s rather than implement `Parametrized`.

```PHP
use sad_spirit\pg_gateway\fragments\CustomSelectFragment;
use sad_spirit\pg_gateway\fragments\ParametrizedFragment;
use sad_spirit\pg_builder\Statement;
use sad_spirit\pg_builder\Select;

$fragment = new ParametrizedFragment(
    new class ('limit-ties', false) extends CustomSelectFragment {
        public function applyTo(Statement $statement, bool $isCount = false): void
        {
           /** @var Select $statement */
           $statement->order->replace('title');
           $statement->limit = ':ties::integer';
           $statement->limitWithTies = true;
        }
    },
    ['ties' => 10]
);
```

`FragmentListBuilder::addWithParameters()` uses this internally. 


## `ClosureFragment`

Wrapper for a closure passed to a query method defined in `AdHocStatement` interface. Queries using this fragment
won't be cached.

## `InsertSelectFragment`

Wrapper for `SelectBuilder` object passed as `$values` to `GenericTableGateway::insert()`.

## `SetClauseFragment`

Fragment populating either the `SET` clause of an `UPDATE` statement or columns and `VALUES` clause of an `INSERT`.

This is created from `$values` given as an array to `GenericTableGateway::insert()` and from `$set` parameter
to `GenericTableGateway::update()`.

You may need to use that explicitly if you want to create a preparable `INSERT` / `UPDATE` statement, e.g.
```PHP
$update = $gateway->createUpdateStatement(new FragmentList(
    new SetClauseFragment(
        $gateway->getDefinition()->getColumns(),
        $tableLocator,
        ['name' => null] 
    ),
    // For the sake of example only, using $builder->createPrimaryKey() is easier
    new PrimaryKeyCondition($gateway->getDefinition()->getPrimaryKey(), $tableLocator->getTypeConverterFactory())
));

$update->prepare($gateway->getConnection());
$update->executePrepared([
    'id'   => 1,
    'name' => 'New name'
]);
$update->executePrepared([
    'id'   => 2,
    'name' => 'Even newer name'
]);

```


## `WhereClauseFragment` and `HavingClauseFragment`

These fragments add an expression generated by a `Condition` instance to the `WHERE` or `HAVING` clause of
a `Statement` being built, respectively.

`Condition` instances can be used directly in the query methods of `TableGateway` as they implement
the `FragmentBuilder` interface. This will add their expressions to the `WHERE` clause due to their `getFragment()`
methods returning `WhereClauseFragment`:
```PHP
$gateway->select(
    $builder->createIsNotNull('field') // Adds a Condition to FragmentList
    // ...
)
```

If a `Condition` should be applied to the `HAVING` clause, you should explicitly use `HavingClauseFragment`:
```PHP
$gateway->select(
    $builder->add(new HavingClauseFragment(
        $builder->createSqlCondition('count(self.field) > 1')
    ))
    // ...
)
```

## `TargetListFragment` and its subclasses

`TargetListFragment` is an abstract base class for fragments that modify either the output list of `SELECT` statement
or the `RETURNING` clause of `DELETE` / `INSERT` / `UPDATE`, whichever is passed to their `applyTo()` method.

It is rarely needed to use its subclasses directly as there are builders and builder methods available:
```PHP
$gateway->update(
    $builder->returningColumns()
        ->primaryKey()
    // ...
);

$gateway->select(
    $builder->returningExpression("coalesce(self.a, self.b) as ab")
    // ...
);
```

## `JoinFragment`

Joins an implementation of `SelectProxy` to the current statement using the given `JoinStrategy` implementation.
Can be additionally configured by a join `Condition`.

It is recommended to use `JoinBuilder` and related `FluentBuilder::join()` method rather than instantiating
this class directly:
```PHP
use sad_spirit\pg_gateway\metadata\TableName;

$documentsGateway->select(
    $documentsBuilder->join(new TableName('documents_tags'))
        ->onForeignKey()        // configures join condition
        ->lateralLeft()         // configures join strategy (LateralSubselectStrategy)
        ->useForCount(false)    // join will not be used by executeCount()
    // ...
);
```

## `LimitClauseFragment` and `OffsetClauseFragment`

These add the `LIMIT` and `OFFSET` clauses to `SELECT` statements. The clauses are added with parameter placeholders
`:limit` and `:offset`, values for these parameters are passed to the query with the fragments
as those implement `Parametrized`.

Builder methods are available for these:
```PHP
$gateway->select(
    $builder->limit(5)
        ->offset(10)
);
```

## `OrderByClauseFragment`

This fragment modifies the `ORDER BY` list of a `SELECT` query using the given expressions. Its constructor accepts
two flags modifying the behaviour:

```PHP
namespace sad_spirit\pg_gateway\fragments;

use sad_spirit\pg_gateway\SelectFragment;
use sad_spirit\pg_builder\Parser;
use sad_spirit\pg_builder\nodes\OrderByElement;

class OrderByClauseFragment implements SelectFragment
{
    public function __construct(
        Parser $parser,
        iterable<OrderByElement|string>|string $orderBy,
        bool $restricted = true,
        bool $merge = false,
        int $priority = self::PRIORITY_DEFAULT
    );
}
```

`$restricted` toggles whether only column names and ordinal numbers are allowed in `ORDER BY` list. As sort options
often come from user input and have to be embedded in SQL, there is that additional protection from SQL injection
by default.

`$merge` toggles whether the new expressions should be added to the existing `ORDER BY` items rather than replace those.
In that case the order in which fragments are added can be controlled with `$priority`.

There are builder methods that create fragments replacing the existing items
```PHP
$gateway->select(
    $builder->orderBy('foo, bar') // $restricted = true
);

$gateway->select(
    $builder->orderByUnsafe('coalesce(foo, bar)') // $restricted = false
);
```

If there is a need to merge items, the class can be instantiated directly:
```PHP
$gateway->select([
    new OrderByClauseFragment($parser, 'foo, bar', true, true, Fragment::PRIORITY_HIGH)
]);
```

## `WithClauseFragment`

Subclasses of this abstract class add Common Table Expressions to the query's `WITH` clause:
 * `fragments\with\SqlStringFragment` accepts an SQL string that can be either a complete `WITH` clause (possibly
   containing several CTEs) or a single CTE: `foo AS (...)`.
 * `fragments\with\SelectProxyFragment` accepts an implementation of `SelectProxy` returned by `TableGateway::select()`
   essentially allowing to prepare a CTE with one gateway and use it with the other.

Instances of these are added by `FluentBuilder::withSqlString()` and `FluentBuilder::withSelect()`, respectively.
`SelectProxyFragment` is configured by `WithClauseBuilder`.
