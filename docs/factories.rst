.. _factory-classes:

==============================
Factory interfaces and classes
==============================

Factories are used by ``TableLocator`` to create objects representing table metadata and to create gateways
and builders to these tables.

.. _factory-definition:

``TableDefinitionFactory`` interface
====================================

.. code-block:: php

    namespace sad_spirit\pg_gateway;

    use sad_spirit\pg_gateway\metadata\TableName;

    interface TableDefinitionFactory
    {
        public function create(TableName $name): TableDefinition;
    }

``TableDefinition`` used as a return type hint is an interface for objects representing table
(or, potentially, some other relation) metadata.
Implementation of ``TableDefinitionFactory`` should return a proper implementation of ``TableDefinition`` for the
given relation name or throw an exception in case of unsupported relation type / invalid name / etc.

``OrdinaryTableDefinitionFactory``
----------------------------------

This is the default implementation of ``TableDefinitionFactory``, its ``create()`` method will

* Return an instance of ``OrdinaryTableDefinition`` if ``$name`` represents an ordinary table (i.e. ``relkind`` field of
  ``pg_catalog.pg_class`` row for that relation contains ``'r'``);
* Throw an exception otherwise.

.. note::

    The class uses an implementation of ``TableOIDMapper`` to check the relation kind. The default implementation
    is usually configured to ignore system schemas, so you'll get an exception trying to get metadata for tables
    from e.g. ``pg_catalog``.

``TableGatewayFactory`` interface
=================================

.. code-block:: php

    namespace sad_spirit\pg_gateway;

    use sad_spirit\pg_builder\nodes\QualifiedName;

    interface TableGatewayFactory
    {
        public function createGateway(TableDefinition $definition, TableLocator $tableLocator) : ?TableGateway;
        public function createBuilder(TableDefinition $definition, TableLocator $tableLocator) : ?builders\FragmentListBuilder;
    }

``createGateway()`` / ``createBuilder()`` should return ``null`` if they cannot create gateway / builder for the
given ``$definition``. They shouldn't throw exceptions: ``TableLocator`` can contain multiple implementations
of ``TableGatewayFactory`` and will sequentially call the relevant methods of them until
a non-``null`` value is returned.

``NameMappingGatewayFactory``
-----------------------------

This is an implementation of ``TableGatewayFactory`` that maps database schemas to PHP namespaces
and converts "snake_case" table names like ``users_roles`` to "StudlyCaps" PHP class names like ``UsersRoles``.

The following code

.. code-block:: php

    use sad_spirit\pg_gateway\NameMappingGatewayFactory;

    $factory = new NameMappingGatewayFactory(['rbac' => '\\app\\modules\\rbac\\database']);
    $factory->createGateway('rbac.users');

will try to load and instantiate ``\app\modules\rbac\database\UsersGateway`` class. It will return
``null`` if one does not exist.

``setGatewayClassNameTemplate()`` and ``setBuilderClassNameTemplate()`` methods
allow setting the templates for class names. Those default to ``'%sGateway'`` and ``'%sBuilder'``, respectively,
where ``%s`` will be substituted by a table name converted to "StudlyCaps". Thus, after

.. code-block:: php

    $factory->setGatewayClassNameTemplate('gateways\\%s');
    $factory->createGateway('rbac.users');

the factory will try the ``\app\modules\rbac\database\gateways\BarBaz`` class instead.
