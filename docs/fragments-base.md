# Query fragments: base interfaces, parameters, aliases

Every query being built by the package goes through the following steps
 * `TableGateway` / `SelectProxy` generates a cache key for the query and calls
   `TableLocator::createNativeStatementUsingCache()` passing a factory closure and the key.
 * If there is a cached `NativeStatement` instance with that key, that is returned.
 * Otherwise, a query is created:
   * A factory closure is called, it creates a base query AST (e.g. `SELECT self.* from table_name as self`)
     and then applies `Fragment`s to it.
   * The resultant `Statement` is converted to `NativeStatement` and possibly cached.

`Fragment`s are responsible for two of the above steps: generating the key (indirectly)
and modifying the AST (directly). They should serve as a sort of proxy for a part of Statement AST, 
creating the actual `Node` objects only when the `applyTo()` method is called.
E.g. if an instance of `Fragment` contains some manually written SQL as a string, that string should not
be processed by `Parser` unless `applyTo()` is called. The string should be used for generating a unique key
for the `Fragment`, though.

`Fragment`s should generally be independent and reusable. It is possible to set up a dependency between `Fragment`s
via priority, but without explicit priority it should be assumed that the fragments can be applied in any order and
to any `Statement`. It is a job of a `Fragment` to check whether it can be applied in the first place.

## `KeyEquatable` interface

This interface defines one method:
 * `getKey(): ?string` - returns a string that uniquely identifies the implementing object based on its properties.
   Returning `null` means that the fragment (and consequently the query using it) cannot be cached.

Implementations of this interface are considered "equal" when building a query if their keys are equal. This is used
for two main purposes:
 * We need a means to generate cache key for a query without generating SQL itself.
   Cache key for a complete statement will be generated based on values returned by `getKey()` methods of its Fragments.
 * `FragmentList` discards duplicate fragments (= having equal string keys): those may appear when several `Fragment`s 
   add the same `Fragment` (e.g. a CTE or a join to a related table) as a dependency.

An implementation should only return a non-`null` key if
 * It is immutable, receiving all its dependencies in the constructor;
 * It will always apply the same changes to the same given `Statement`.

The key should depend on SQL being generated but never on values of parameters used in the query,
even if those are passed with the `Fragment`.

If an implementation of `KeyEquatable` has a property that is also an implementation of `KeyEquatable`
then it should return `null` from `getKey()` if its child returns `null` to prevent caching. In the other case it 
should generate a key based on the child's string key.
E.g. in the case of `WhereClauseFragment` containing a `Condition`:
```PHP
 public function getKey(): ?string
 {
     $conditionKey = $this->condition->getKey();
     return null === $conditionKey ? null : 'where.' . $conditionKey;
 }
```
 

## `Fragment` interface

This interface extends `KeyEquatable` and defines two additional methods:
 * `applyTo(Statement $statement): void` - applies the fragment to the given Statement.
 * `getPriority(): int` - returns the fragment's priority. Fragments with higher priority will be applied earlier,
   this may be relevant for CTEs, joins, and parts of `ORDER BY` / `GROUP BY` clauses. If fragments have the
   same priority then they will be applied in alphabetical order of their keys.

Implementations of `Fragment` are classes that directly modify the `Statement` being built. They, however,
are not necessarily generating the actual changes, delegating this instead to some other classes.
E.g. `WhereClauseFragment` uses an expression generated by `Condition` and 
`SelectListFragment` passes the `$select->list` property to an instance of `TargetListManipulator`.

In any case, as stated above, `Fragment` implementations should delay building parts of AST until `applyTo()` is called.

## `SelectFragment` interface

This is an interface for fragments specific to `SELECT` statements. As `SelectProxy` can actually execute two different
queries using the same set of fragments:
 * `SELECT *` query executed in `getIterator()` and
 * `SELECT COUNT(*)` query executed in `executeCount()`

we need a means to filter parts of the query that are not relevant to the latter. 

The interface extends `Fragment` defining one additional method
 * `isUsedForCount(): bool` - whether this fragment should be added to a `SELECT COUNT(*)` query at all. 
   If the fragment does not change the number of returned rows or if it doesn't make sense for `SELECT COUNT(*)`
   query (e.g. `ORDER`, `LIMIT`, `OFFSET`), then it should be skipped.

and changes the signature of `applyTo()`
 * `applyTo(Statement $statement, bool $isCount = false): void` - the second parameter specifies whether a 
   `SELECT COUNT(*)` query is being processed. It is intended for the `JOIN`-type fragments: while the join itself 
   may be needed as it affects the number of returned rows, adding fields from the joined table to the target
   list should be omitted.

## `FragmentBuilder` interface

This interface defines one method
 * `getFragment(): Fragment` - returns the built fragment.

It has two main purposes
 * As `Fragment` instances should be immutable, they may need a lot of complex constructor arguments. It is much easier
   to use a builder with a fluent interface than to create these manually;
 * `Fragment` dependencies that are not instances of `Fragment` can implement `FragmentBuilder` to be accepted
   by gateway's query methods.

The first purpose is easily illustrated with `JoinBuilder`:
```PHP
$documentsGateway->join('employees') // this returns a JoinBuilder instance
    ->onForeignKey(['author_id'])
    ->left()
    ->alias('author')
    ->useForCount(false);
```

The second one is most obvious with `Condition` that implements `FragmentBuilder`
wrapping itself in `WhereClauseFragment`:
```PHP
public function getFragment(): Fragment
{
    return new fragments\WhereClauseFragment($this);
}
```
this allows passing `Condition` instances directly to gateway's query methods to add them to the `WHERE` clause
of the query being built.

## Passing parameter values with fragments

If a `Fragment` adds a parameter placeholder `:param` to the query, it may make sense to pass a value for that
parameter alongside the fragment. It is essentially required for `Fragment`s that embed a `SelectProxy` into the
larger statement, as `SelectProxy` implementations should contain all parameters needed to execute a query.

### `Parametrized` interface

This interface defines one method
 * `getParameterHolder(): ParameterHolder` - returns values for named parameters.

We do not return just an associative array, as we want to perform an additional check when combining parameter values
from several sources: there should not be several values for the same parameter name.
This check is performed by an implementation of `ParameterHolder` interface.

Most of the built-in `Fragment` implementations do actually implement `Parametrized`. 

### `ParameterHolder` interface

This interface defines two methods
 * `getParameters(): array<string, mixed>` - returns values for named parameters.
 * `getOwner(): KeyEquatable` - returns the `Fragment`/`Condition` that is the source of the parameters. 
   This is only used for generating an exception message if different values for the same parameter were found when
   combining several `ParameterHolder` fragments

`ParameterHolder` has three implementations
 * `EmptyParameterHolder` - this is a Null Object implementation, its `getParameters()` method always returns `[]`;
 * `SimpleParameterHolder` - a wrapper for an associative array, returned when parameter values come from
   a single source.
 * `RecursiveParameterHolder` - aggregates several child `ParameterHolder`s, returned by `Fragment`s that have several
   `Parametrized` children. This is the class that actually performs the check for duplicate values described above. 

## Using table aliases

As a rule of thumb, all tables that appear in the queries should be aliased. This allows using generated queries in
join-type `Fragment`s without possible ambiguities and allows using the same fragment with different gateways. 

There are two specially handled aliases
 * `self` (`TableGateway::ALIAS_SELF`) - alias for the table handled by the current gateway. All the fragments passed
    to its query methods should use this alias for access to the table columns.
 * `joined` (`TableGateway::ALIAS_JOINED`) - this is a special alias used in the join conditions, it references the
   table being joined (while `self` alias references the base table as usual).

As seen in the [README](../README.md), above aliases should be used even in custom SQL fragments:
```PHP
$gwLink->sqlCondition("current_date between coalesce(self.valid_from, 'yesterday') and coalesce(self.valid_to, 'tomorrow')");
```

Join-type fragments usually allow specifying an explicit alias for the table being joined. If not given,
an automatically generated one will be used.

### `ReplaceTableAliasWalker` class

This class is used internally by `JoinFragment` and similar classes to replace the above two aliases by some custom ones:
```PHP
$select->dispatch(new ReplaceTableAliasWalker(TableGateway::ALIAS_SELF, $alias));

$condition->dispatch(new ReplaceTableAliasWalker(TableGateway::ALIAS_JOINED, $alias));
```

As it is working with query AST, it will replace aliases even in fragments that were originally added as SQL strings.