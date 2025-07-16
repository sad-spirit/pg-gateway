========
Overview
========

**pg_gateway** builds upon `pg_wrapper <https://github.com/sad-spirit/pg-wrapper>`__
and `pg_builder <https://github.com/sad-spirit/pg-builder>`__ packages and provides a Table Data Gateway
implementation for Postgres.

**pg_wrapper** is used to execute queries and to convert Postgres types to PHP types and back. It already can
automatically convert query result fields to proper PHP types using the result metadata. **pg_gateway** automates
converting parameters as well: it reads the table metadata and uses that to properly convert values for table columns.

**pg_builder** is used to build queries in gateways' methods. As that package contains a reimplementation
of SQL parser used by Postgres, it usually accepts parts of the query as strings. Those are parsed into
nodes of an Abstract Syntax Tree. **pg_gateway** in its turn

- implements helper methods for adding common query parts, often relying on table metadata;
- allows using the complete feature set of **pg_builder** when necessary;
- can combine queries created by several gateways via ``JOIN`` / ``EXISTS`` / ``WITH``,
  correctly applying join conditions or similar constructs;

**pg_gateway** provides a means to cache the generated queries, skipping the whole parsing / building process.

Design decisions
================

Some specific design decisions were made for ``pg_gateway``, these are outlined below and discussed more verbosely
on the separate pages.

Database is the source of truth
-------------------------------

The package does not try to generate database schema based on some classes. Instead, it uses the existing schema
to configure the table gateways:

- List of table columns is used for building ``Condition``\ s depending on columns and for
  configuring the output of the query;
- ``PRIMARY KEY`` constraints allow finding rows by primary key and ``upsert()`` operations;
- ``FOREIGN KEY`` constraints are used to perform joins.

There is also no need to specify data types outside of SQL: the underlying packages take care to convert
both the output columns and the input parameters. It is sufficient to write

.. code-block:: postgres

    field = any(:param::integer[])

in your ``Condition`` and the package will expect an array of integers for a value of ``param`` parameter
and will properly convert that array for RDBMS's consumption.

Queries are built as ASTs
-------------------------

The queries being built are represented as an Abstract Syntax Tree of Nodes, parts of that tree can be provided
as strings and the tree can be manipulated thanks to **pg_builder** package.

**pg_gateway** in turn allows direct access to the AST being built and provides its own manipulation options.
For example, it is possible to configure a ``SELECT`` targeting one table via its gateway's ``select()`` method
and then embed this ``SELECT`` into query being built by a gateway to another table. The fact that we aren't dealing
with strings here allows applying additional conditions and updating table aliases, even if (parts) of SQL
were provided as strings initially.

The obvious downside is that parsing SQL and building SQL from AST are expensive operations, so we provide means
to cache the complete query.


Preferring parametrized queries
-------------------------------

While Postgres only allows positional parameters like ``$1`` in queries, **pg_builder** package accepts named
ones like ``:param`` that are later converted to native positional ones.

As was mentioned above, there is no need to specify parameter types outside of SQL they appear in.
There are also means to pass parameter values alongside query parts that use them.

These feature make it easy to combine a query from several parts having parameter placeholders, instead of
substituting literals into query. Parametrized queries can be cached and reused later with different parameter values.

Reusable query fragments
------------------------

The main concept of the package is ``Fragment``: it serves as a sort of proxy to a part of query AST.
Every query being built starts from the base AST (e.g. ``SELECT self.* from table_name as self``) and then
Fragments are applied to it. Those may modify the list of returned columns or add conditions to the ``WHERE`` clause.

Fragments and related classes have a ``getKey()`` method that should return a string uniquely identifying the Fragment
based on its contents. It is assumed that applying Fragments having the same keys will result in the same changes
to query. These keys are combined to generate a cache key for the complete query and possibly skip
the parse / build operations.


Requirements
============

**pg_gateway** requires at least PHP 8.2 with native `pgsql <https://php.net/manual/en/book.pgsql.php>`__ extension
enabled.

Minimum supported PostgreSQL version is 12.

It is highly recommended to setup `PSR-6 <https://www.php-fig.org/psr/psr-6/>`__ cache implementation
both for metadata and for generated queries.

Installation
============

Require the package with `composer <https://getcomposer.org/>`__:

.. code-block:: bash

    composer require "sad_spirit/pg_gateway:^0.10"

Data mapping
============

Mapping of database rows to domain objects is outside the scope of **pg_gateway**: it accepts and returns data as
associative arrays. There are, however, some features that may help with such mapping:

- ``Result`` class from ``pg_wrapper`` package has ``getTableOID()`` method returning the OID (internal
  Postgres object identifier) of a table that was the source of the result field. ``pg_gateway`` adds
  ``metadata\CachedTableOIDMapper`` class that maps such OIDs to table names.
-  It is possible to do mass aliasing of result fields using regular expressions or callbacks.
