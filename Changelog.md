# Changelog

## [0.4.0] - 2024-08-31

### Changed
 * Closures passed as the `$fragments` parameter into methods defined in `TableGateway` interface will receive
   a subclass of `FragmentListBuilder` (created by `TableLocator::createBuilder()` for that table name)
   rather than a subclass of `Statement`. Before:
   ```PHP
   $gateway->delete(fn(Delete $delete) => $delete->where->and('foo = bar'));
   ```
   now:
   ```PHP
   $gateway->delete(fn(FluentBuilder $fb) => $fb->sqlCondition('foo = bar'));
   ```
   Previous behaviour is supported by the new `*WithAST()` methods.
 * Methods of `FluentBuilder` that accepted callbacks to configure the created builder instances, e.g. `join()`,
   now return builder objects that proxy `FluentBuilder` methods. Before:
   ```PHP
   $builder->join($otherTable, fn(JoinBuilder $jb) => $jb->left()->onForeignKey())
      ->outputColumns(fn(ColumnsBuilder $cb) => $cb->except(['foo']));
   ```
   now:
   ```PHP
   $builder
      ->join($otherTable)
          ->left()
          ->onForeignKey()
      ->outputColumns()
          ->except(['foo']);
   ```
   Callbacks are still accepted but deprecated.
 * Strings passed to `createExists()`, `exists()`, and `join()` methods of `FluentBuilder` are now treated as
   SELECT statements rather than table names:
   ```PHP
   $builder
      ->join('select foo from bar as baz');
   ```
   Table names can still be passed as instances of `TableName` or `QualifiedName`.
 * Constructor of `TableSelect` accepts an instance of `FragmentList` rather than separate
   `$fragments` and `$parameters`.
 * `FluentBuilder::join()` will join on foreign key by default if possible. If an unconditional
   join is needed, this should be explicitly requested:
   ```PHP
   $builder
      ->join($otherTable)
          ->unconditional();
   ```

### Added
 * `AdHocStatement` interface with `deleteWithAST()`, `insertWithAST()`, `selectWithAST()`, and `updateWithAST()`
   methods. Those accept closures that receive the relevant subclass of `Statement` as parameter.
   `GenericTableGateway` implements this interface.
 * `FragmentListBuilder::__clone()`: this now clones the current state of the builder allowing to safely use 
   a semi-configured one as a prototype.
 * `GenericTableGateway::createBuilder()` method that calls `TableLocator::createBuilder()` internally
   using the table's name from that gateway.
 * `TableSelect::fetchFirst()` method that is shorthand for `$select->getIterator()->current()`
 * `TableLocator::select($name, $fragments, $parameters)` method that is shorthand for
   `$locator->createGateway($name)->select($fragments, $parameters)`


## [0.3.0] - 2024-08-06

### Changed
 * Changed typehint of `$gatewayFactories` parameter for `TableLocator::__construct()` from `array` to `iterable`,
   allowing to use e.g. `tagged_iterator` from Symfony's DI container in its place.
 * Implementations of methods defined in `metadata\Columns`, `metadata\PrimaryKey`, and `metadata\References`
   interfaces were moved from `metadata\Table*` implementations into traits. This separates the code
   that loads metadata from DB / cache and the code that accesses that metadata, allowing reuse of the latter:
   * `ArrayOfColumns` trait now contains a `$columns` property and implementations of `Columns` methods working with it.
   * `ArrayOfPrimaryKeyColumns` trait now contains `$columns` and `$generated` properties and implements methods 
     of `PrimaryKey` working with these.
   * `ArrayOfForeignKeys` trait has `$foreignKeys`, `$referencing`, and `$referencedBy` properties as well as 
     implementations of `References` methods using these.
 * Additionally, `metadata\TableColumns` defined a new protected `assertCorrectRelkind()` method 
   that can be easily overridden in child class if working with relations that are not ordinary tables.

## [0.2.1] - 2024-07-24

Fixed package name in `composer.json`: the intended `sad_spirit/pg_gateway` instead of `sad_spirit/pg-gateway`
(dash replaced by underscore).

## [0.2.0] - 2024-06-14

### Added
 * `metadata\TableName` class replacing `QualifiedName` from `pg_builder` package.
 * `metadata\TableOIDMapper` interface and its default `metadata\CachedTableOIDMapper` implementation. This is used
   for checking the relation type when creating an implementation of `TableDefinition` and may be used to ease
   mapping of result columns when using `Result::getTableOID()`.
 * `TableDefinitionFactory` interface and its default `OrdinaryTableDefinitionFactory` implementation. The latter
   will return an instance of `OrdinaryTableDefinition` only for relations that exist in `pg_catalog.pg_class` and
   contain 'r' (ordinary table) in the `relkind` column.
 * `TableLocator::setTableDefinitionFactory()` and `TableLocator::getTableDefinitionFactory()`. If the factory is not
   explicitly set, the latter will create and return an instance of `OrdinaryTableDefinitionFactory`.
 * `TableLocator::getTableDefinition()` returning an implementation of `TableDefinition` for the given table.
   It uses the configured instance of `TableDefinitionFactory`.
 * `TableGatewayFactory::createBuilder()` method returning a subclass of a new abstract `builders\FragmentListBuilder`
   class. The method is called by the new `TableLocator::createBuilder()` method which will return 
   an instance of default `builders\FluentBuilder` implementation if the factories did not create a specific one.
 * `NameMappingGatewayFactory` implementation of `TableGatewayFactory` that maps DB schemas to PHP namespaces and
   "snake-case" `table_name` to "StudlyCaps" `TableName`.
 * Base abstract `CustomFragment` and `CustomSelectFragment` classes and `ParametrizedFragment` decorator,
   those can be used to add custom cacheable fragments.
 * Fragments and builders adding Common Table Expressions to query's `WITH` clause. It is possible to specify those
   either as an SQL string or as a wrapper for `SelectProxy` (i.e. a result of `TableGateway::select()`).

### Changed
 * `metadata\TableName` is used throughout the package in place of `pg_builder`'s `QualifiedName`.
   Instances of the new class do not need to be cloned and always contain two name parts (schema and relation) which 
   makes working with them a bit easier.
 * `metadata\Columns`, `metadata\PrimaryKey`, and `metadata\References` are now interfaces with the former logic
   residing in `metadata\TableColumns`, `metadata\TablePrimaryKey`, `metadata\TableReferences` respectively.
   The main reason is that custom implementations are needed for views and other relations other than ordinary tables.
 * `OrdinaryTableDefinition` is a default implementation of `TableDefinition`, which returns the instances
   of the above metadata classes.
 * `TableGateway` and `SelectProxy` interfaces no longer extend `TableDefinition`, they extend a new `TableAccessor`
   interface with a `getDefinition()` method.
 * Builder methods of `GenericTableGateway` were moved to `FluentBuilder`. Instances of that class are returned by
   `TableLocator::createBuilder()` by default, its methods now return `$this` allowing to chain calls.
   The actual fragments being created are added to the `FragmentList` eventually returned by `getFragment()`
 * Constructor of `TableLocator` accepts an array of `TableGatewayFactory` implementations rather than a single one.
   There is also a new `addTableGatewayFactory()` method. The factories will be called in the order added.
 * `TableGatewayFactory::create()` renamed to `createGateway()`.
 * `TableLocator::get()` is now `TableLocator::createGateway()`. It will no longer return the same instance of
   `TableGateway` for the same table name. It also uses an instance of `TableDefinitionFactory` under the hood,
   so by default only gateways to existing ordinary tables will be created.
 * `setPriority()` method of `VariablePriority` trait is now protected rather than public. Previously `Fragment`s
   using that trait were essentially mutable.
 * Depend on `pg_wrapper` and `pg_builder` 2.4

### Removed
 * `GenericTableGateway::create()` factory method. It is no longer necessary to access private properties in this and
   the remaining logic was moved to `TableLocator::createGateway()`.

## [0.1.0] - 2023-09-13

Initial release on GitHub.

[0.1.0]: https://github.com/sad-spirit/pg-gateway/releases/tag/v0.1.0
[0.2.0]: https://github.com/sad-spirit/pg-gateway/compare/v0.1.0...v0.2.0
[0.2.1]: https://github.com/sad-spirit/pg-gateway/compare/v0.2.0...v0.2.1
[0.3.0]: https://github.com/sad-spirit/pg-gateway/compare/v0.2.1...v0.3.0
[0.4.0]: https://github.com/sad-spirit/pg-gateway/compare/v0.3.0...v0.4.0
