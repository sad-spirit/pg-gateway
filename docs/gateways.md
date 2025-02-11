# Gateways

## `TableGateway` interface

This interface extends `TableAccessor` (thus gateways provide [access to table metadata](./metadata.md)) and defines
four methods corresponding to SQL statements: 
```PHP
namespace sad_spirit\pg_gateway;

use sad_spirit\pg_builder\SelectCommon;
use sad_spirit\pg_wrapper\Result;

interface TableGateway extends TableAccessor
{
    public function delete($fragments = null, array $parameters = []) : Result;
    public function insert(array<string, mixed>|SelectCommon|SelectBuilder $values, $fragments = null, array $parameters = []) : Result;
    public function select($fragments = null, array $parameters = []) : SelectProxy;
    public function update(array $set, $fragments = null, array $parameters = []): Result;
}
```

`$fragments` parameter for the above methods can be one of the following
 * `\Closure` - this accepts an instance of `FluentBuilder` to omit an explicit `createBuilder()` call;
 * Implementation of `Fragment` or `FragmentBuilder`;
 * Iterable over `Fragment` or `FragmentBuilder` implementations.

Most commonly, `$fragments` will either be a fluent builder object created by `TableLocator::createBuilder()`
```PHP
$locator->createGateway('example')
    ->select(
        $locator->createBuilder('example')
            ->returningColumns()
                ->only(['id', 'name'])
            ->notBoolColumn('deleted')
            ->orderBy('added')
            ->limit(10)
    );
```
or a closure that will receive such an object
```PHP
use sad_spirit\pg_gateway\builders\FluentBuilder;

$locator->createGateway('example')
    ->select(fn(FluentBuilder $builder) => $builder
         ->returningColumns()
            ->only(['id', 'name'])
         ->notBoolColumn('deleted')
         ->orderBy('added')
         ->limit(10));
```


`$values` (when an array) / `$set` parameter for `insert()` / `update()` is an associative array of the form
`'column name' => 'value'`. Here `'value'` may be either a literal or an instance of `Expression` which is used
to set the column value to an SQL expression:
```PHP
$documentsGateway->insert([
    'id'    => 1,
    'title' => 'default',
    'added' => new Expression('now()')
]);
```
Literals will not be embedded into the generated SQL, parameter placeholders will be inserted and their values
eventually passed to `Connection::executeParams()`.

Note also that while `delete()` / `insert()` / `update()` methods immediately return `Result` objects,
`select()` returns a `SelectProxy` instance.

### `AdHocStatement` interface

It is sometimes needed to modify the query AST in a completely custom way. Methods defined in `AdHocStatement`
interface allow exactly this:
```PHP
namespace sad_spirit\pg_gateway;

use sad_spirit\pg_builder\{
    Delete,
    Insert,
    SelectCommon,
    Update
};
use sad_spirit\pg_wrapper\Result;

interface AdHocStatement
{
    public function deleteWithAST(\Closure(Delete) $closure, array $parameters = []) : Result;
    public function insertWithAST($values, \Closure(Insert) $closure, array $parameters = []) : Result;
    public function selectWithAST(\Closure(SelectCommon) $closure, array $parameters = []) : SelectProxy;
    public function updateWithAST(array $set, \Closure(Update) $closure, array $parameters = []) : Result;
}
```

Closures passed to its methods accept the base AST of the query and may change it using the full
capabilities of [pg_builder](https://github.com/sad-spirit/pg-builder):
```PHP
use sad_spirit\pg_builder\Delete;

$gateway->deleteWithAST(function (Delete $delete) {
    // Modify the $delete query any way you like
    $delete->with->merge('with recursive foo as (...)');
    $delete->using[] = 'foo'
    $delete->where->and('self.bar @@@ foo.id');
});
```

The downside is that a query built in that way will not be cached.

### `SelectProxy` interface

Unlike other methods of `TableGateway`, `select()` *will not* immediately execute the generated `SELECT` statement,
but will return a proxy object implementing `SelectProxy` interface:
```PHP
namespace sad_spirit\pg_gateway;

use sad_spirit\pg_builder\SelectCommon;
use sad_spirit\pg_wrapper\Result;

interface SelectProxy extends SelectBuilder, Parametrized, TableAccessor, \IteratorAggregate
{
    public function executeCount() : int|numeric-string;
    public function getIterator() : Result;
    public function createSelectAST() : SelectCommon;
}
```

`KeyEquatable` and `Parametrized` are [base interfaces for query fragments](./fragments-base.md), they are required
to use `SelectProxy` inside fragments.

An implementation of `SelectProxy` should contain all the data needed to execute 
`SELECT` (and `SELECT COUNT(*)`), with actual queries executed only when `getIterator()` or `executeCount()` is called, 
respectively.

The most common case still looks the same way as if `select()` did return `Result`:
```PHP
foreach ($gateway->select($fragments) as $row) {
    // process the row
}
```

But having a proxy object allows less common cases as well:
 * It is frequently needed to additionally execute the query that returns the total number of rows that satisfy 
   the given conditions (e.g. for pagination), this is done with `executeCount()`;
 * The configured object can be used inside a more complex query, this is covered by `createSelectAST()` method.

The package provides a default implementation in `TableSelect` class, it is implemented immutable as is the case with
all other Fragments
```PHP
namespace sad_spirit\pg_gateway;

use sad_spirit\pg_builder\NativeStatement;

final class TableSelect implements SelectProxy
{
    public function __construct(
        TableLocator $tableLocator,
        TableGateway $gateway,
        FragmentList $fragments,
        \Closure $baseSelectAST = null,
        \Closure $baseCountAST = null
    );

    public function createSelectStatement() : NativeStatement;
    public function createSelectCountStatement() : NativeStatement;

    public function fetchFirst() : ?array;
}
```

The constructor accepts closures creating base statement ASTs for `SELECT` and `SELECT count(*)` queries.
If e.g. a table uses "soft-deletes" then it may make sense to start from 
```SQL
SELECT self.* FROM foo AS self WHERE not self.deleted
```

Results of `createSelectStatement()` / `createSelectCountStatement()` can be used for `prepare()` / `execute()`. 

`$select->fetchFirst()` method is a shorthand for `$select->getIterator()->current()`.  

## `TableGateway` implementations

The package contains three implementations of `TableGateway` interface. An instance of one of these will be returned by
`TableLocator::createGateway()` if the locator was not configured with custom gateway factories or if none of these
returned a more specific gateway object.

What exactly will be returned depends on
 * whether `PRIMARY KEY` constraint was defined on the table and 
 * the number of columns in that key.

### `GenericTableGateway`

This is the simplest gateway implementation, an instance of which is returned for tables that do not have a primary key
defined. In addition to the methods defined in `TableGateway` it contains methods to create statements and
to create the builder for that particular table
```PHP
namespace sad_spirit\pg_gateway\gateways;

use sad_spirit\pg_gateway\FragmentList;
use sad_spirit\pg_gateway\builders\FragmentListBuilder;
use sad_spirit\pg_builder\NativeStatement;

class GenericTableGateway implements TableGateway
{
    public function createDeleteStatement(FragmentList $fragments) : NativeStatement;
    public function createInsertStatement(FragmentList $fragments) : NativeStatement;
    public function createUpdateStatement(FragmentList $fragments) : NativeStatement

    public function createBuilder() : FragmentListBuilder;
}
```

The results of those can be used for e.g. `prepare()` / `execute()`. [`FragmentList`](./fragments-implementations.md) 
is an object that keeps all the fragments used in a query and possibly parameter values for those.
It is returned by `getFragment()` method of a fluent builder and can also be created via `FragmentList::normalize()`
from whatever can be passed as `$fragments` to `TableGateway` methods.

Note the lack of `createSelectStatement()`, methods of `TableSelect` can be used for that.

`createBuilder()` calls [`TableLocator::createBuilder()`](./locator.md) under the hood so everything
about that method applies.

### `PrimaryKeyTableGateway`

If a table has a `PRIMARY KEY` constraint defined and the key has only one column, then an instance of this class
will be returned. It extends `GenericTableGateway` and implements an additional `PrimaryKeyAccess` interface
with the following methods
```PHP
namespace sad_spirit\pg_gateway;

use sad_spirit\pg_wrapper\Result;

interface PrimaryKeyAccess
{
    public function deleteByPrimaryKey(mixed $primaryKey) : Result;
    public function selectByPrimaryKey(mixed $primaryKey) : SelectProxy;
    public function updateByPrimaryKey(mixed $primaryKey, array $set): Result;

    public function upsert(array $values): array;
}
```

The `upsert()` method builds and executes an `INSERT ... ON CONFLICT DO UPDATE ...` statement
returning the primary key of the inserted / updated row:
```PHP
$documentsGateway->upsert([
    'id'    => 1,
    'title' => 'New title'
]);
```
will most probably return `['id' => 1]`.

### `CompositePrimaryKeyTableGateway`

When the table's `PRIMARY KEY` constraint contains two or more columns, this subclass of `PrimaryKeyTableGateway`
will be used. We assume that such a table is generally used for defining an M:N relationship and provide a method
that allows to replace all records related to a key from one side of relationship:
 * `replaceRelated(array $primaryKeyPart, iterable $rows): array`

Assuming the schema defined in [README](../README.md) we can use this method to replace the list of roles
assigned to the user after e.g. editing user's profile:
```PHP
$tableLocator->atomic(function (TableLocator $locator) use ($userData, $roles) {
    $pkey = $locator->createGateway('example.users')
        ->upsert($userData);

    return $locator->createGateway('example.users_roles')
        ->replaceRelated($pkey, $roles);
});
```
