.. _table-locator:

======================
``TableLocator`` class
======================

This class serves as a facade to features of ``pg_gateway`` and the packages it depends on. It is also used
to create table gateways and builders.

It is recommended to pass an instance of this class as a dependency instead of individual gateway objects.

Class API
=========

.. code-block:: php

    namespace sad_spirit\pg_gateway;

    use sad_spirit\pg_wrapper\Connection;
    use sad_spirit\pg_builder\{
        NativeStatement,
        Parser,
        Statement,
        StatementFactory,
        converters\TypeNameNodeHandler,
        nodes\QualifiedName,
    };
    use Psr\Cache\CacheItemPoolInterface;

    class TableLocator
    {
        // Static helper methods
        public static function hash(mixed $value) : string;
        public static function generateAlias() : string;

        public function __construct(
            Connection $connection,
            iterable<TableGatewayFactory> $gatewayFactories = [],
            ?StatementFactory $statementFactory = null,
            ?CacheItemPoolInterface $statementCache = null
        );

        // factory for TableDefinition implementations
        public function getTableDefinitionFactory() : TableDefinitionFactory;
        public function setTableDefinitionFactory(TableDefinitionFactory $factory) : $this;

        // factory for gateways and builders
        public function addTableGatewayFactory(TableGatewayFactory $factory) : $this;

        // getters for constructor dependencies
        public function getConnection() : Connection;
        public function getStatementFactory() : StatementFactory;

        // facade methods
        public function atomic(callable $callback, bool $savepoint = false) : mixed;
        public function getParser() : Parser;
        public function createFromString(string $sql) : Statement;
        public function createFromAST(Statement $ast) : NativeStatement;
        public function getTypeConverterFactory() : TypeNameNodeHandler&ConfigurableTypeConverterFactory;
        public function createTypeNameNodeForOID($oid) : TypeName;

        // creating statements
        public function createNativeStatementUsingCache(\Closure $factoryMethod, ?string $cacheKey) : NativeStatement;

        // creating objects containing tables' metadata
        public function getTableDefinition(string|metadata\TableName|QualifiedName $name) : TableDefinition;

        // creating gateways and builders
        public function createGateway(string|metadata\TableName|QualifiedName $name) : TableGateway;
        public function createBuilder(string|metadata\TableName|QualifiedName $name) : builders\FragmentListBuilder;

        // Shorthand for createGateway($name)->select($fragments, $parameters)
        public function select(
            string|metadata\TableName|QualifiedName $name,
            null|iterable|\Closure|Fragment|FragmentBuilder $fragments = null,
            array $parameters = []
        ) : SelectProxy;
    }

Constructor arguments
=====================

The only required argument is the ``Connection`` object:

.. code-block:: php

    $locator = new TableLocator(new Connection('...connection string...'));

``$gatewayFactories``, if given, will be used in ``createGateway()`` and ``createBuilder()``. Otherwise,
these methods will return instances of default gateways and default builder, respectively.

If ``$statementFactory`` is omitted, a factory for the given ``Connection`` will be created
via ``StatementFactory::forConnection()`` method.

``$statementCache`` can be any `PSR-6 <https://www.php-fig.org/psr/psr-6>`__ cache implementation. If given,
it will be used for caching complete statements. Note that table metadata will be cached using
the metadata cache of ``Connection`` object, if one is available.

Facade methods
==============

``atomic()``
    Calls ``Connection::atomic()``, passing
    ``TableLocator`` instance as the first argument to the given callback. This
    `executes the callback atomically <https://pg-wrapper.readthedocs.io/en/v3.1.0/transactions.html>`__
    (within database transaction).
``getParser()``
    Returns an instance of ``Parser`` used by ``StatementFactory``.
``createFromString()``
    Calls
    `the same method of StatementFactory <https://pg-builder.readthedocs.io/en/v3.1.0/statement-factory.html>`__,
    parses SQL of a complete statement returning its AST.
``createFromAST()``
    Calls the same method of ``StatementFactory``, builds an SQL string
    from AST and returns object encapsulating this string and parameter placeholder data.
``getTypeConverterFactory()``
    Returns the type converter factory object used by ``Connection``.
``createTypeNameNodeForOID()``
    Calls the same method of ``TypeNameNodeHandler``, returns ``TypeName`` node
    corresponding to database type OID that can be used in statement AST.

.. _table-locator-factories:

Getting metadata and creating gateways (the ``Locator`` part)
=============================================================

.. note::

    It is recommended to *always* provide a qualified name (``schema_name.table_name``) to ``TableLocator`` methods:
    the package does not process ``search_path`` and will simply assume that an unqualified name belongs
    to the ``public`` schema.


``getTableDefinition()``
    Returns metadata for a specific database table.
    It uses an implementation of :ref:`TableDefinitionFactory interface <factory-definition>` under the hood.
``setTableDefinitionFactory()``
    Sets the implementation of ``TableDefinitionFactory`` used by ``getTableDefinition()``.
``getTableDefinitionFactory()``
    Returns the implementation of ``TableDefinitionFactory`` used by ``getTableDefinition()``.
    This will set up and return a default instance of ``OrdinaryTableDefinitionFactory`` if a specific instance
    was not configured. That default implementation, as its name implies, only returns metadata for ordinary tables,
    using it with views / foreign tables / etc. will cause an exception.

Table metadata that is returned by ``getTableDefinition()`` is used for creating gateways and builders to that table:

``createGateway()``
    Returns a ``TableGateway`` implementation for a given table name.
``createBuilder()``
    Returns a fluent builder for a given table name.

These methods will call relevant methods of available ``TableGatewayFactory`` implementations in the order
these were added (either in constructor or with ``addTableGatewayFactory()``).

* If a factory returns a non-``null`` value it will become the method's result.
* If no factories are available or if all of them returned ``null``, default gateway / builder implementations
  are created and returned.

.. _table-locator-statements:

Creating statements
===================

``createNativeStatementUsingCache()`` method is used by ``TableGateway`` and ``SelectProxy`` implementations
for creating statements.

The goal of this method is to prevent parse / build operations and return the actual pre-built SQL.
Thus its return value is an instance of ``NativeStatement`` encapsulating that SQL, hopefully coming from cache.

``$factoryMethod`` closure, on the other hand, should return an instance of ``Statement``, i.e. the AST.
Consider the actual implementation of ``GenericTableGateway::createInsertStatement()``:

.. code-block:: php

    public function createInsertStatement(FragmentList $fragments): NativeStatement
    {
        return $this->tableLocator->createNativeStatementUsingCache(
            function () use ($fragments): Insert {
                $insert = $this->tableLocator->getStatementFactory()->insert(new InsertTarget(
                    $this->definition->getName()->createNode(),
                    new Identifier(TableGateway::ALIAS_SELF)
                ));
                $fragments->applyTo($insert);
                return $insert;
            },
            $this->generateStatementKey(StatementType::Insert, $fragments)
        );
    }
