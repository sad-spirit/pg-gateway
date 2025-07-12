.. _oid-mapper:

===========================
Mapping OIDs to table names
===========================

The ``TableOIDMapper`` interface and its default implementation provide an easier way to work with data
from ``pg_catalog.pg_class`` and related system tables.

If a field in the query result comes directly from a table, Postgres will return an OID representing that table
in result metadata. This can be accessed by
`Result::getTableOID() method <https://pg-wrapper.readthedocs.io/en/v3.1.0/result.html>`__ from ``pg_wrapper``.
Implementation of ``TableOIDMapper`` can then be used to map that OID to a more useful name.

``TableOIDMapper`` interface
============================

.. code-block:: php

    namespace sad_spirit\pg_gateway\metadata;

    interface TableOIDMapper
    {
        public function findOIDForTableName(TableName $name) : int|string;
        public function findTableNameForOID(int|string $oid) : TableName;
        public function findRelationKindForTableName(TableName $name) : RelationKind;
    }

"OID" in the method names stand for system "object identifier" type of Postgres. In this particular case OIDs represent
the primary key of ``pg_catalog.pg_class`` system table which contains the names and additional metadata for all
database relations.

Methods ``findTableNameForOID()`` / ``findOIDForTableName()`` provide mapping between OIDs and qualified table names.

``findRelationKindForTableName()`` return a case of ``RelationKind`` enum corresponding to a single character value
stored in ``relkind`` field of ``pg_catalog.pg_class``. ``OrdinaryTableDefinitionFactory`` class uses this to check
the relation kind of the given table name and reject anything that is not an ordinary table.

``CachedTableOIDMapper`` class
==============================

This is the default implementation of ``TableOIDMapper``. It uses the metadata cache of the given ``Connection``
instance to store data from ``pg_catalog.pg_class`` after the initial load.

By default this class does not load table info for Postgres system schemas (``information_schema`` and those starting
from ``pg_``, e.g. ``pg_catalog``). This can be changed by passing ``false`` as ``$ignoreSystemSchemas`` constructor
argument

.. code-block:: php

    $systemMapper = new CachedTableOIDMapper($connection, false);

    // Now you can use $locator to create gateways to system tables
    $locator->setTableDefinitionFactory(new OrdinaryTableDefinitionFactory(
        $connection,
        $systemMapper
    ));
