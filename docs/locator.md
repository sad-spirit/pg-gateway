# TableLocator class

This class serves as a facade to features of `pg_gateway` and the packages it depends on. It is also used
to create table gateways.

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
        ?TableGatewayFactory $gatewayFactory = null,
        ?StatementFactory $statementFactory = null,
        ?CacheItemPoolInterface $statementCache = null
    );

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

    // creating gateways
    public function get(string|QualifiedName $name) : TableGateway
}
```

## Constructor arguments

As you can see, the only required argument is the `Connection` object:
```PHP
$locator = new TableLocator(new Connection('...connection string...'));
```

If `$gatewayFactory` is given, it will be used when calling `get()` method, otherwise `get()` will
return an instance of a [default gateway](./gateways.md).

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

## Creating gateways (the `Locator` part)

`TableGatewayFactory` interface, an implementation of which can be passed to `TableLocator`
constructor, defines one method:
```PHP
namespace sad_spirit\pg_gateway;

use sad_spirit\pg_builder\nodes\QualifiedName;

interface TableGatewayFactory
{
    public function create(QualifiedName $name, TableLocator $tableLocator) : ?TableGateway;
}
```

When `get()` method of `TableLocator` is called, it calls `create()` method
of `TableGatewayFactory` implementation and falls back to 
`GenericTableGateway::create()` if there is either no factory or its `create()` method returned `null`.

If a gateway was already created for the given table name, the existing instance will be returned.

It is recommended to *always* provide a qualified name (`schema_name.table_name`) for a table: the package does not try 
to process `search_path` and will just assume that an unqualified name belongs to the `public` schema.