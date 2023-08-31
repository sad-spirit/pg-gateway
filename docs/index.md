# sad_spirit/pg_gateway

The Table Data Gateway serves as a gateway to a table in the database, it provides methods that mirror the most common
table operations (`delete()`, `insert()`, `select()`, `update()`) and encapsulates SQL code that is needed to actually
perform these operations.

As `pg_gateway` is built upon [pg_wrapper](https://github.com/sad-spirit/pg-wrapper) 
and [pg_builder](https://github.com/sad-spirit/pg-builder) packages it does not provide database abstraction,
only targeting Postgres. This allows leveraging its strengths like rich type system and expressive SQL syntax while
maybe sacrificing some flexibility.

Some specific design decisions were made for `pg_gateway`, these are outlined below and discussed more verbosely
on the separate pages.

## Database is the source of truth

The package does not try to generate database schema based on some classes. Instead, it uses the existing schema 
to configure the table gateways:
 * List of table columns is used for building Conditions depending on columns and for configuring the output of the query;
 * `PRIMARY KEY` constraints allow finding rows by primary key and `upsert()` operations;
 * `FOREIGN KEY` constraints are used to perform joins.

There is also no need to specify data types outside of SQL: the underlying packages take care to convert
both the output columns and the input parameters. It is sufficient to write
```
field = any(:param::integer[]) 
```
in your Condition and the package will expect an array of integers for a value of `param` parameter
and properly convert that array for RDBMS's consumption. Output columns are transparently converted to proper PHP types
as well thanks to `pg_wrapper`.

## Queries are built as ASTs

`pg_builder` package contains a partial reimplementation of PostgreSQL's own query parser. It allows converting
manually written SQL into Abstract Syntax Tree, analyzing and manipulating this tree, 
and finally converting it back to an SQL string.

`pg_gateway` in turn allows direct access to the AST being built and provides its own manipulation options. 
For example, it is possible to configure a `SELECT` targeting one table via its gateway's `select()` method
and then embed this `SELECT` into query being built by a gateway to another table. The fact that we aren't dealing 
with strings here allows applying additional conditions and updating table aliases, even if (parts) of SQL
were provided as strings initially.

The obvious downside is that parsing SQL and building SQL from AST are expensive operations, so we provide means
to cache the complete query.

## Preferring parametrized queries

While Postgres only allows positional parameters like `$1` in queries, `pg_builder` package accepts named
ones like `:param` that are later converted to native positional ones.

As was mentioned above, there is no need to specify parameter types outside of SQL they appear in. 
There are also means to pass parameter values alongside query parts that use them.

These feature make it easy to combine a query from several parts having parameter placeholders, instead of
substituting literals into query. Parametrized queries can be cached and reused later with other parameter values.

## Reusable query parts

The main concept of the package is `Fragment`: it serves as a sort of proxy to a part of query AST.
Every query being built starts from the base AST (e.g. `SELECT self.* from table_name as self`) and then
Fragments are applied to it. Those may modify the list of returned columns or add conditions to the `WHERE` clause.

Fragments and related classes have a `getKey()` method that should return a string uniquely identifying the Fragment
based on its contents. It is assumed that applying Fragments having the same keys will result in the same changes
to query. These keys are combined to generate a cache key for the complete query and possibly skip
the parse / build operations.
