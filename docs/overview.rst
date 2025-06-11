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
