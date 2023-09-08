# Table metadata

The package loads and uses the following table metadata: 
 * List of table columns, represented by `metadata\Columns` class. It is used for configuring the list of columns returned
   by the query, for setting of column values in `INSERT` and `UPDATE` queries, and for Conditions
   on specific columns;
 * `PRIMARY KEY` constraint, represented by `metadata\PrimaryKey` class. It allows accessing table rows by primary key 
   and performing `upsert()` and `replaceRelated()` operations;
 * `FOREIGN KEY` constraints, represented by `metadata\References` class. These are used to perform joins in all the
   relevant Fragments.

All of the above classes extend `CachedMetadataLoader`: that class tries to use metadata cache from `Connection` object
if that cache is available before loading metadata from database.

Of course, it is highly recommended to use metadata cache in production.

## `TableDefinition` interface

The interface defines access to metadata of a particular table:
```PHP

namespace sad_spirit\pg_gateway;

use sad_spirit\pg_builder\nodes\QualifiedName;
use sad_spirit\pg_wrapper\Connection;

interface TableDefinition
{
    public function getConnection() : Connection;
    public function getName() : QualifiedName;
    public function getColumns() : metadata\Columns;
    public function getPrimaryKey() : metadata\PrimaryKey;
    public function getReferences() : metadata\References;
}
```
The `Connection` object returned by `getConnection()` represents the database the table is in.

Due to specifics of `pg_builder`'s `Node` classes `getName()` should always return a clone of 
the original object, so it is not a good idea to check the return value with `===`.

`TableDefinition` is extended by `TableGateway` and `SelectProxy`, these have default implementations in the package.

## `Columns` class

This class serves as a container for `Column` value objects, allowing iteration over these and providing some
additional methods:
```PHP
namespace sad_spirit\pg_gateway\metadata;

class Columns extends CachedMetadataLoader implements \IteratorAggregate, \Countable
{
    public function getAll() : Column[];
    public function getNames() : string[];
    public function has(string $column) : bool;
    public function get(string $column) : Column;
}
```

`get()` will throw an `OutOfBoundsException` if a column with the given name was not found.

As the class implements `IteratorAggregate` and `Countable` interfaces, the following is possible:
```PHP
$columns = $definition->getColumns();

echo "The table has " . count($columns) . " column(s), specifically:\n";
foreach ($columns as $column) {
    echo $column->getName() . "\n";
}
```

The `Column` class has the following accessors:
```PHP
namespace sad_spirit\pg_gateway\metadata;

class Column
{
    public function getName() : string;
    public function isNullable() : bool;
    public function getTypeOID() : int|numeric-string;
}
```

## `PrimaryKey` class

This is also a container for `Column` objects, representing columns that form the table's primary key:
```PHP
namespace sad_spirit\pg_gateway\metadata;

class PrimaryKey extends CachedMetadataLoader implements \IteratorAggregate, \Countable
{
    public function getAll() : Column[];
    public function getNames() : string[];
    public function isGenerated() : bool;
}
```

`isGenerated()` returns whether table's primary key is automatically generated. This includes the
SQL standard `GENERATED` columns, Postgres specific `SERIAL`,
and those having `nextval('sequence_name')` for a default value.

## `References` class

This is a container for `ForeignKey` value objects, representing both `FOREIGN KEY` constraints added to the table
and those referencing it: 
```PHP
namespace sad_spirit\pg_gateway\metadata;

use sad_spirit\pg_builder\nodes\QualifiedName;

class References extends CachedMetadataLoader implements \IteratorAggregate, \Countable
{
    public function get(QualifiedName $relatedTable, string[] $keyColumns = []) : ForeignKey;
    public function from(QualifiedName $childTable, string[] $keyColumns = []) : ForeignKey[];
    public function to(QualifiedName $referencedTable, string[] $keyColumns = []) : ForeignKey[];
}
```

 * `get()` returns a single `ForeignKey` object matching 
   the given related table and constraint columns (if given). The columns are always those on the child side of 
   the relationship. Will throw an `InvalidArgumentException` unless exactly one matching key is found.
 * `from()` returns foreign keys defined on the given table referencing the current one and
   `to()` returns foreign keys on the current table referencing the given one.

The `ForeignKey` class has the following accessors:
```PHP
namespace sad_spirit\pg_gateway\metadata;

use sad_spirit\pg_builder\nodes\QualifiedName;

class ForeignKey implements \IteratorAggregate
{
    public function getChildTable() : QualifiedName;
    public function getReferencedTable() : QualifiedName;
    public function getChildColumns() : string[];
    public function getReferencedColumns() : string[];
    public function getConstraintName() : string;
    public function isRecursive() : bool;
}
```
"Child" here is the table to which the `FOREIGN KEY` constraint was added, "referenced" is the table
actually referenced from that constraint.

`getConstraintName()` returns the name of the `FOREIGN KEY` constraint. This is always available, may be
   autogenerated by Postgres if not given explicitly.

`isRecursive()` returns whether a foreign key is recursive, i.e. refers back to the same table.

Iteration over `ForeignKey` object goes through column mapping:
```PHP
foreach ($foreignKey as $childColumn => $referencedColumn) {
    echo "Column " . $childColumn . " references " . $referencedColumn . "\n";
}
```
