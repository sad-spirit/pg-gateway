# Changelog

## [Unreleased]

### Changed
 * Added `metadata\TableName` class that is used throughout the package in place of `pg_builder`'s `QualifiedName`.
   Instances of the new class do not need to be cloned and always contain two name parts (schema and relation) which 
   makes working with them a bit easier.
 * `metadata\Columns`, `metadata\PrimaryKey`, and `metadata\References` are now interfaces with the former logic
   residing in `metadata\TableColumns`, `metadata\TablePrimaryKey`, `metadata\TableReferences` respectively.
   The main reason is that custom implementations are needed for views and other relations other than ordinary tables.
 * `TableGateway` and `SelectProxy` interfaces no longer extend `TableDefinition`, they extend a new `TableAccessor`
   interface with a `getDefinition()` method. A default implementation of `TableDefinition` is added which returns
   the instances of the above metadata classes.
 * Depend on `pg_wrapper` and `pg_builder` 2.4

## [0.1.0] - 2023-09-13

Initial release on GitHub.

[0.1.0]: https://github.com/sad-spirit/pg-gateway/releases/tag/v0.1.0
[Unreleased]: https://github.com/sad-spirit/pg-builder/compare/v0.1.0...HEAD