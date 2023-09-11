# Gateways

## `TableGateway` interface

This interface extends `TableDefinition` (thus gateways provide [access to table metadata](./metadata.md)) and defines
four methods corresponding to SQL statements: 
```PHP
namespace sad_spirit\pg_gateway;

use sad_spirit\pg_builder\SelectCommon;
use sad_spirit\pg_wrapper\ResultSet;

interface TableGateway extends TableDefinition
{
    public function delete($fragments = null, array $parameters = []) : ResultSet;
    public function insert(array<string, mixed>|SelectCommon|SelectProxy $values, $fragments = null, array $parameters = []) : ResultSet;
    public function select($fragments = null, array $parameters = []) : SelectProxy;
    public function update(array $set, $fragments = null, array $parameters = []): ResultSet;
}
```

`$fragments` parameter for the above methods can be one of the following
 * `\Closure` - this is used for ad-hoc queries;
 * Implementation of `Fragment` or `FragmentBuilder`;
 * Most commonly, iterable over `Fragment` or `FragmentBuilder` implementations.

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

Note also that while `delete()` / `insert()` / `update()` methods immediately return `ResultSet` objects,
`select()` returns a `SelectProxy` instance.

### Ad-hoc queries

It is sometimes needed to modify the query AST in a completely custom way. Passing a closure as `$fragments` to one of 
the above methods allows exactly this:
```PHP
use sad_spirit\pg_builder\Delete;

$gateway->delete(function (Delete $delete) {
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
use sad_spirit\pg_wrapper\ResultSet;

interface SelectProxy extends KeyEquatable, Parametrized, TableDefinition, \IteratorAggregate
{
    public function executeCount() : int|numeric-string;
    public function getIterator() : ResultSet;
    public function createSelectAST() : SelectCommon;
}
```

`KeyEquatable` and `Parametrized` are [base interfaces for query fragments](./fragments-base.md), they are required
to use `SelectProxy` inside fragments.

An implementation of `SelectProxy` should contain all the data needed to execute 
`SELECT` (and `SELECT COUNT(*)`), with actual queries executed only when `getIterator()` or `executeCount()` is called, 
respectively.

The most common case still looks the same way as if `select()` did return `ResultSet`:
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
        $fragments = null,
        array $parameters = [],
        \Closure $baseSelectAST = null,
        \Closure $baseCountAST = null
    );

    public function createSelectStatement() : NativeStatement;
    public function createSelectCountStatement() : NativeStatement;
}
```

The constructor accepts closures creating base statement ASTs for `SELECT` and `SELECT count(*)` queries.
If e.g. a table uses "soft-deletes" then it may make sense to start from 
```SQL
SELECT self.* FROM foo AS self WHERE not self.deleted
```

Results of `createSelectStatement()` / `createSelectCountStatement()` can be used for `prepare()` / `execute()`. 

## `TableGateway` implementations

The package contains three implementations of `TableGateway` interface, an instance of one of these will be returned by
`GenericTableGateway::create()` or `$tableLocator->get()` if the locator was not configured 
with a custom gateway factory.

What exactly will be returned depends on whether `PRIMARY KEY` constraint was defined on the table and the number
of columns in that key.

### `GenericTableGateway`

This is the simplest gateway implementation, an instance of which is returned for tables that do not have a primary key
defined. In addition to the methods defined in the interface it has the static `create()` method mentioned above 
and the methods to create statements:
```PHP
namespace sad_spirit\pg_gateway\gateways;

use sad_spirit\pg_gateway\{
    FragmentList,
    TableLocator
}
use sad_spirit\pg_builder\{
    NativeStatement,
    nodes\QualifiedName
}

class GenericTableGateway implements TableGateway
{
    public static function create(QualifiedName $name, TableLocator $tableLocator) : self;

    public function createDeleteStatement(FragmentList $fragments) : NativeStatement;
    public function createInsertStatement(FragmentList $fragments) : NativeStatement;
    public function createUpdateStatement(FragmentList $fragments) : NativeStatement
}
```

The results of those can be used for e.g. `prepare()` / `execute()`. [`FragmentList`](./fragments-implementations.md) 
is an object that keeps all the fragments used in a query and possibly parameter values for those.
It is usually created via `FragmentList::normalize()` from whatever can be passed as `$fragments`
to `TableGateway` methods.

Note the lack of `createSelectStatement()`, methods of `TableSelect` can be used for that.

There are also [several builder methods defined](./builders-methods.md), 
these return `Fragment`s / `FragmentBuilder`s configured for that particular gateway.

### `PrimaryKeyTableGateway`

If a table has a `PRIMARY KEY` constraint defined and the key has only one column, then an instance of this class
will be returned. It implements an additional `PrimaryKeyAccess` interface with the following methods
```PHP
namespace sad_spirit\pg_gateway;

use sad_spirit\pg_wrapper\ResultSet;

interface PrimaryKeyAccess
{
    public function deleteByPrimaryKey(mixed $primaryKey) : ResultSet;
    public function selectByPrimaryKey(mixed $primaryKey) : SelectProxy;
    public function updateByPrimaryKey(mixed $primaryKey, array $set): ResultSet;

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

The class also defines [an additional builder method](./builders-methods.md) for a primary key condition.

### `CompositePrimaryKeyTableGateway`

When the table's `PRIMARY KEY` constraint contains two or more columns, this class will be used. We assume that
such a table is generally used for defining an M:N relationship and provide a method that allows to replace 
all records related to a key from one side of relationship:
 * `replaceRelated(array $primaryKeyPart, iterable $rows): array`

Assuming the schema defined in [README](../README.md) we can use this method to replace the list of roles
assigned to the user after e.g. editing user's profile:
```PHP
$tableLocator->atomic(function (TableLocator $locator) use ($userData, $roles) {
    $pkey = $locator->get('example.users')
        ->upsert($userData);

    return $locator->get('example.users_roles')
        ->replaceRelated($pkey, $roles);
});
```
