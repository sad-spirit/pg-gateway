# TableLocator class

This class serves as a facade to features of `pg_gateway` and the packages it depends on. It is also used
to create table gateways.

It is recommended to pass an instance of this class as a dependency instead of individual gateway objects.

## Constructor arguments

`TableLocator`'s constructor has the following signature
```PHP
use sad_spirit\pg_wrapper\Connection;
use sad_spirit\pg_gateway\TableGatewayFactory;
use sad_spirit\pg_builder\StatementFactory;
use Psr\Cache\CacheItemPoolInterface;

public function __construct(
    Connection $connection,
    ?TableGatewayFactory $gatewayFactory = null,
    ?StatementFactory $statementFactory = null,
    ?CacheItemPoolInterface $statementCache = null
) {
    // ...
}
```

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

`$connection` and `$statementFactory` objects are later accessible via getters:
 * `getConnection(): Connection`
 * `getStatementFactory(): StatementFactory`


## Facade methods

 * `atomic(callable $callback, bool $savepoint = false): mixed` - calls `Connection::atomic()` passing
   `TableLocator` instance as the first argument to the given callback. This executes the callback atomically
   (i.e. within database transaction).
 * `getParser(): Parser` - returns an instance of `Parser` used by `StatementFactory`
 * `createFromString(string $sql): Statement` - calls the same method of `StatementFactory`, parses
   SQL of a complete statement returning its AST.
 * `createFromAST(Statement $ast): NativeStatement` - calls the same method of `StatementFactory`, builds an SQL string
   from AST and returns object encapsulating this string and parameter data.
 * `getTypeConverterFactory(): TypeNameNodeHandler` - returns the type converter factory object used by `Connection`
 * `createTypeNameNodeForOID($oid): TypeName` - calls the same method of `TypeNameNodeHandler`, returns `TypeName` node
   corresponding to database type OID that can be used in statement AST.

## Creating statements

`createNativeStatementUsingCache(\Closure $factoryMethod, ?string $cacheKey): NativeStatement` method is used
by `TableGateway` and `SelectProxy` implementations for creating statements.

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

Gateways are created using `get(string|QualifiedName $name): TableGateway` method. This will call `create()` method
of `TableGatewayFactory` implementation that was passed to the constructor and will fall back to 
`GenericTableGateway::create()` if there is either no factory or its `create()` method returned `null`.

If a gateway was already created for the given table name, the existing instance will be returned.

It is recommended to always provide a qualified name (`schema_name.table_name`) for a table: the package does not try 
to process `search_path` and will just assume that an unqualified name belongs to the `public` schema.