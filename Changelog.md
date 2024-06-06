# Changelog

## [Unreleased]

### Added
 * `metadata\TableName` class replacing `QualifiedName` from `pg_builder` package.
 * `metadata\TableOIDMapper` interface and its default `metadata\CachedTableOIDMapper` implementation. This is used
   for checking the relation type when creating an implementation of `TableDefinition` and may be used to ease
   mapping of result columns when using `Result::getTableOID()`
 * `TableDefinitionFactory` interface and its default `OrdinaryTableDefinitionFactory` implementation. The latter
   will return an instance of `OrdinaryTableDefinition` only for relations that exist in `pg_catalog.pg_class` and
   contain 'r' (ordinary table) in the `relkind` column.
 * `TableLocator::setTableDefinitionFactory()` and `TableLocator::getTableDefinitionFactory()`. If the factory is not
   explicitly set, the latter will create and return an instance of `OrdinaryTableDefinitionFactory`.

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
 * `TableLocator::get()` is now `TableLocator::createGateway()`. It will no longer return the same instance of
   `TableGateway` for the same table name. It also uses an instance of `TableDefinitionFactory` under the hood,
   so by default only gateways to existing ordinary tables will be created.
 * Depend on `pg_wrapper` and `pg_builder` 2.4

### Removed
 * `GenericTableGateway::create()` factory method. It is no longer necessary to access private properties in this and
   the remaining logic was moved to `TableLocator::createGateway()`.

## [0.1.0] - 2023-09-13

Initial release on GitHub.

[0.1.0]: https://github.com/sad-spirit/pg-gateway/releases/tag/v0.1.0
[Unreleased]: https://github.com/sad-spirit/pg-builder/compare/v0.1.0...HEAD