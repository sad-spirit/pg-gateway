# Table metadata

The package loads and uses the following table metadata: 
 * List of table columns, represented by an implementation of `metadata\Columns` interface. 
   It is used for configuring the list of columns returned by the query, for setting of column values
   in `INSERT` and `UPDATE` queries, and for Conditions on specific columns;
 * `PRIMARY KEY` constraint, represented by an implementation of `metadata\PrimaryKey` interface.
   It allows accessing table rows by primary key and performing `upsert()` and `replaceRelated()` operations;
 * `FOREIGN KEY` constraints, represented by an implementaion of `metadata\References` interface. 
   These are used to perform joins in all the relevant Fragments.

The default implementations of the above interfaces are named `metadata\TableColumns`, `metadata\TablePrimaryKey`, and
`metadata\TableReferences`, respectively. These will work with ordinary tables, but not other relations like views
or foreign tables. All of these extend base `CachedMetadataLoader` class, which tries to use metadata cache from 
`Connection` object if that cache is available before loading metadata from database.

Of course, it is highly recommended to use metadata cache in production.

## `TableName` class

This class represents an always-qualified name of a table (or possibly another relation). Unlike `QualifiedName` from
`pg_builder` package it always has two parts: schema (defaulting to `public`) and relation name. It also does not need
to be cloned (`QualifiedName` contains a link to its parent node, so using the same instance in multiple queries
is impossible). The API is the following:
```PHP
namespace sad_spirit\pg_gateway\metadata;

use sad_spirit\pg_builder\nodes\QualifiedName;

final class TableName
{
    public function __construct(string ...$nameParts);

    // Converting to QualifiedName and back
    public static function createFromNode(QualifiedName $qualifiedName) : self;
    public function createNode() : QualifiedName;

    // These return name parts
    public function getRelation() : string;
    public function getSchema(): string;

    // Checks whether the two names are equal
    public function equals(self $other) : bool;

    // Returns the string representation, this uses QualifiedName internally
    public function __toString();
}
```

## `TableDefinition` interface

This interface aggregates metadata of a particular table:
```PHP

namespace sad_spirit\pg_gateway;

interface TableDefinition
{
    public function getName() : metadata\TableName;
    public function getColumns() : metadata\Columns;
    public function getPrimaryKey() : metadata\PrimaryKey;
    public function getReferences() : metadata\References;
}
```

The package contains a default implementation of this interface, `OrdinaryTableDefinition` class.
It represents metadata of an ordinary table with its methods returning the default `Table*` implementations of metadata
interfaces mentioned above.

## `TableAccessor` interface

This interface should be implemented by classes that perform queries to a specific table:
```PHP
namespace sad_spirit\pg_gateway;

use sad_spirit\pg_wrapper\Connection;

interface TableAccessor
{
    public function getConnection(): Connection;
    public function getDefinition(): TableDefinition;
}
```

it is extended by `TableGateway` and `SelectProxy`, these have default implementations in the package.

## `Columns` interface

Implementations of `Columns` serve as containers for `Column` value objects, allowing iteration over these 
and providing some additional methods:
```PHP
namespace sad_spirit\pg_gateway\metadata;

interface Columns extends \IteratorAggregate, \Countable
{
    public function getAll() : Column[];
    public function getNames() : string[];
    public function has(string $column) : bool;
    public function get(string $column) : Column;
}
```

`get()` will throw an `OutOfBoundsException` if a column with the given name was not found.

As the interface extends `IteratorAggregate` and `Countable`, the following is possible:
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

final class Column
{
    public function getName() : string;
    public function isNullable() : bool;
    public function getTypeOID() : int|numeric-string;
}
```

## `PrimaryKey` interface

This is also a container for `Column` objects, representing columns that form the table's primary key:
```PHP
namespace sad_spirit\pg_gateway\metadata;

interface PrimaryKey extends \IteratorAggregate, \Countable
{
    public function getAll() : Column[];
    public function getNames() : string[];
    public function isGenerated() : bool;
}
```

`isGenerated()` returns whether table's primary key is automatically generated. This includes the
SQL standard `GENERATED` columns, Postgres specific `SERIAL`,
and those having `nextval('sequence_name')` for a default value.

## `References` interface

Implementations serve as containers for `ForeignKey` value objects,
representing both `FOREIGN KEY` constraints added to the table and those referencing it: 
```PHP
namespace sad_spirit\pg_gateway\metadata;

interface References extends \IteratorAggregate, \Countable
{
    public function get(TableName $relatedTable, string[] $keyColumns = []) : ForeignKey;
    public function from(TableName $referencedTable, string[] $keyColumns = []) : ForeignKey[];
    public function to(TableName $childTable, string[] $keyColumns = []) : ForeignKey[];
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

final class ForeignKey implements \IteratorAggregate
{
    public function getChildTable() : TableName;
    public function getReferencedTable() : TableName;
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
