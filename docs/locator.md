# TableLocator class

This class serves as a facade to features of `pg_gateway` and the packages it depends on. It is also used
to create table gateways and builders.

It is recommended to pass an instance of this class as a dependency instead of individual gateway objects.

API provided by `TableLocator` is as follows:

```PHP
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
    public function getTypeConverterFactory() : TypeNameNodeHandler;
    public function createTypeNameNodeForOID($oid) : TypeName;

    // creating statements
    public function createNativeStatementUsingCache(\Closure $factoryMethod, ?string $cacheKey) : NativeStatement;

    // creating objects containing tables' metadata
    public function getTableDefinition(string|metadata\TableName|QualifiedName $name) : TableDefinition;

    // creating gateways and builders
    public function createGateway(string|metadata\TableName|QualifiedName $name) : TableGateway;
    public function createBuilder(string|metadata\TableName|QualifiedName $name) : builders\FragmentListBuilder;
}
```

## Constructor arguments

As you can see, the only required argument is the `Connection` object:
```PHP
$locator = new TableLocator(new Connection('...connection string...'));
```

`$gatewayFactories`, if given, will be used for `createGateway()` and `createBuilder()` methods. Otherwise,
these will return instances of [default gateways](./gateways.md) and [default builder](./builders-methods.md),
respectively.

If `$statementFactory` is omitted, a factory for the given `Connection` will be created
via `StatementFactory::forConnection()` method.

`$statementCache` can be any [PSR-6](https://www.php-fig.org/psr/psr-6/) cache implementation. If given,
it will be used for caching complete statements. Note that table metadata will be cached using
the metadata cache of `Connection` object, if one is available.

## Facade methods

 * `atomic()` - calls `Connection::atomic()` passing
   `TableLocator` instance as the first argument to the given callback. This
   [executes the callback atomically](https://github.com/sad-spirit/pg-wrapper/wiki/transactions)
   (within database transaction).
 * `getParser()` - returns an instance of `Parser` used by `StatementFactory`
 * `createFromString()` - calls
   [the same method of `StatementFactory`](https://github.com/sad-spirit/pg-builder/wiki/StatementFactory),
   parses SQL of a complete statement returning its AST.
 * `createFromAST()` - calls the same method of `StatementFactory`, builds an SQL string
   from AST and returns object encapsulating this string and parameter placeholder data.
 * `getTypeConverterFactory()` - returns the type converter factory object used by `Connection`
 * `createTypeNameNodeForOID()` - calls the same method of `TypeNameNodeHandler`, returns `TypeName` node
   corresponding to database type OID that can be used in statement AST.

## Creating statements

`createNativeStatementUsingCache()` method is used by `TableGateway` and `SelectProxy` implementations
for creating statements.

Note the return type: the goal of this method is to prevent parse / build operations and return the actual pre-built SQL.
`$factoryMethod` closure, on the other hand, should return an instance of `Statement`, consider the actual 
implementation of `GenericTableGateway::createInsertStatement()`:
```PHP
public function createInsertStatement(FragmentList $fragments): NativeStatement
{
    return $this->tableLocator->createNativeStatementUsingCache(
        function () use ($fragments): Insert {
            $insert = $this->tableLocator->getStatementFactory()->insert(new InsertTarget(
                $this->getName(),
                new Identifier(TableGateway::ALIAS_SELF)
            ));
            $fragments->applyTo($insert);
            return $insert;
        },
        $this->generateStatementKey(self::STATEMENT_INSERT, $fragments)
    );
}
```

## Getting metadata and creating gateways (the `Locator` part)

It is recommended to *always* provide a qualified name (`schema_name.table_name`) to the `TableLocator` methods: 
the package does not try to process `search_path` and will just assume that an unqualified name belongs
to the `public` schema.

`getTableDefinition()` method is used for getting [metadata for a specific database table](./metadata.md).
It uses an implementation of `TableDefinitionFactory` interface under the hood:
```PHP
namespace sad_spirit\pg_gateway;

use sad_spirit\pg_gateway\metadata\TableName;

interface TableDefinitionFactory
{
    public function create(TableName $name): TableDefinition;
}
```

That implementation can be set with `setTableDefinitionFactory()` and is returned by `getTableDefinitionFactory()`.
The latter method will set up and return a default instance of `OrdinaryTableDefinitionFactory` if a specific instance
was not configured. That default implementation, as its name implies, only returns metadata for ordinary tables,
using it with views / foreign tables / etc. will cause an exception.

Implementations of `TableDefinition` are then used for creating gateways and builders using implementations
of `TableGatewayFactory` interface, provided to `TableLocator` constructor and `addTableGatewayFactory()`:
```PHP
namespace sad_spirit\pg_gateway;

use sad_spirit\pg_builder\nodes\QualifiedName;

interface TableGatewayFactory
{
    public function createGateway(TableDefinition $definition, TableLocator $tableLocator): ?TableGateway;
    public function createBuilder(TableDefinition $definition, TableLocator $tableLocator): ?builders\FragmentListBuilder;
}
```

`createGateway()` and `createBuilder()` methods of `TableLocator` will call relevant methods of available
`TableGatwewayFactory` implementations in the order these were added until one returns a non-null value.
If no factories are available or if all available returned `null`, default gateway / builder implementations
are created and returned.

### `NameMappingGatewayFactory`

The package contains an implementation of `TableGatewayFactory` that maps database schemas to PHP namespaces
and converts "snake_case" table names like `foo_bar` to "StudlyCaps" PHP class names like `FooBar`.

The following code
```PHP
use sad_spirit\pg_gateway\NameMappingGatewayFactory;

$factory = new NameMappingGatewayFactory(['foo' => '\\app\\modules\\foo\\database']);
$factory->createGateway('foo.bar_baz');
```
will try to load and instantiate `\app\modules\foo\database\BarBazGateway` class. It will return
`null` if one does not exist. The `setGatewayClassNameTemplate()` and `setBuilderClassNameTemplate()` allow setting
the templates for class names. Those default to `'%sGateway'` and `'%sBuilder'`, respectively, where `%s` will be
substituted by a table name converted to "StudlyCaps". Thus, after
```PHP
$factory->setGatewayClassNameTemplate('gateways\\%s');
$factory->createGateway('foo.bar_baz');
```
the factory will try the `\app\modules\foo\database\gateways\BarBaz` class instead.
