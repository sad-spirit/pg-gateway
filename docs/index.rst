=====================
sad_spirit/pg_gateway
=====================

The `Table Data Gateway <https://martinfowler.com/eaaCatalog/tableDataGateway.html>`__ serves as a gateway to a table
in the database, it provides methods that mirror the most common table operations (``delete()``, ``insert()``,
``select()``, ``update()``) and encapsulates SQL code that is needed to actually perform these operations.

As ``pg_gateway`` is built upon `pg_wrapper <https://github.com/sad-spirit/pg-wrapper>`__
and `pg_builder <https://github.com/sad-spirit/pg-builder>`__ packages it does not provide database abstraction,
only targeting Postgres. This allows leveraging its strengths like rich type system and expressive SQL syntax while
maybe sacrificing some flexibility.

.. toctree::
    :maxdepth: 3
    :caption: Contents:

    overview
