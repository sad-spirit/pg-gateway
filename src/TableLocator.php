<?php

/*
 * This file is part of sad_spirit/pg_gateway:
 * Table Data Gateway for Postgres - auto-converts types, allows raw SQL, supports joins between gateways
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace sad_spirit\pg_gateway;

use sad_spirit\pg_gateway\{
    builders\FluentBuilder,
    builders\FragmentListBuilder,
    exceptions\UnexpectedValueException,
    gateways\CompositePrimaryKeyTableGateway,
    gateways\GenericTableGateway,
    gateways\PrimaryKeyTableGateway,
    metadata\CachedTableOIDMapper,
    metadata\TableName
};
use sad_spirit\pg_wrapper\{
    Connection,
    converters\DefaultTypeConverterFactory
};
use sad_spirit\pg_builder\{
    NativeStatement,
    Parser,
    Statement,
    StatementFactory,
    converters\TypeNameNodeHandler,
    converters\BuilderSupportDecorator,
    exceptions\SyntaxException,
    nodes\QualifiedName,
    nodes\TypeName
};
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException as PsrException;

/**
 * TableLocator is a facade to pg_gateway features and a means to create table gateways
 *
 * @psalm-import-type FragmentsInput from TableGateway
 */
class TableLocator
{
    private static int $aliasIndex = 0;
    /** @var array<TableGatewayFactory>  */
    private array $gatewayFactories = [];
    private readonly StatementFactory $statementFactory;
    private TypeNameNodeHandler $typeConverterFactory;

    private ?TableDefinitionFactory $definitionFactory = null;

    /** @var array<string,TableName> */
    private array $names = [];
    /** @var array<string,TableDefinition> */
    private array $definitions = [];

    /**
     * Computes a reasonably unique hash of a value.
     *
     * Borrowed from Symfony DI component, intended for generating fragment keys.
     *
     * @param mixed $value A serializable value
     */
    public static function hash($value): string
    {
        $hash = \substr(\base64_encode(\hash('sha256', \serialize($value), true)), 0, 8);

        return \strtr($hash, '/+', '._');
    }

    /**
     * Generates a unique alias for a table
     */
    public static function generateAlias(): string
    {
        return 'gw_' . ++self::$aliasIndex;
    }


    /**
     * Constructor, sets up factories
     *
     * @param iterable<TableGatewayFactory> $gatewayFactories
     */
    public function __construct(
        private readonly Connection $connection,
        iterable $gatewayFactories = [],
        ?StatementFactory $statementFactory = null,
        private readonly ?CacheItemPoolInterface $statementCache = null
    ) {
        $this->statementFactory = $statementFactory ?? StatementFactory::forConnection($this->connection);
        foreach ($gatewayFactories as $factory) {
            $this->addTableGatewayFactory($factory);
        }

        $converterFactory = $this->connection->getTypeConverterFactory();
        if ($converterFactory instanceof TypeNameNodeHandler) {
            $this->typeConverterFactory = $converterFactory;
        } elseif ($converterFactory instanceof DefaultTypeConverterFactory) {
            // Add a decorator ourselves, if possible...
            $this->typeConverterFactory = new BuilderSupportDecorator(
                $converterFactory,
                $this->statementFactory->getParser()
            );
            $this->connection->setTypeConverterFactory($this->typeConverterFactory);
        } else {
            // ...error if not
            throw new UnexpectedValueException(
                "Connection object should be configured either with an implementation"
                . " of TypeNameNodeHandler or an instance of DefaultTypeConverterFactory, this is required"
                . " for handling of type information extracted from SQL and for generating type names."
            );
        }
    }

    /**
     * Returns a Factory for TableDefinition implementations
     *
     * If a factory was not set with {@see setTableDefinitionFactory()}, then an instance of
     * {@see OrdinaryTableDefinitionFactory} will be created and returned
     */
    public function getTableDefinitionFactory(): TableDefinitionFactory
    {
        return $this->definitionFactory ??= new OrdinaryTableDefinitionFactory(
            $this->connection,
            new CachedTableOIDMapper($this->connection)
        );
    }

    /**
     * Sets a Factory for TableDefinition implementations
     *
     * @return $this
     */
    public function setTableDefinitionFactory(TableDefinitionFactory $factory): self
    {
        $this->definitionFactory = $factory;
        $this->definitions       = [];

        return $this;
    }

    /**
     * Adds a factory for TableGateway (and FragmentListBuilder) implementations
     *
     * @return $this
     */
    public function addTableGatewayFactory(TableGatewayFactory $factory): self
    {
        $this->gatewayFactories[] = $factory;

        return $this;
    }

    /**
     * Returns the DB connection object used by TableLocator
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Runs a given function atomically
     *
     * This behaves exactly like {@see Connection::atomic()}, except the callback will receive two arguments:
     *  - This TableLocator instance
     *  - The Connection instance used for transaction
     *
     * @param callable $callback  The function to execute atomically
     * @param bool     $savepoint Whether to create a savepoint if the transaction is already in progress
     * @return mixed The value returned by $callback
     * @throws \Throwable
     */
    public function atomic(callable $callback, bool $savepoint = false): mixed
    {
        return $this->connection->atomic(fn (): mixed => $callback($this, $this->connection), $savepoint);
    }

    /**
     * Returns the StatementFactory object used to convert queries from SQL strings to AST and back
     */
    public function getStatementFactory(): StatementFactory
    {
        return $this->statementFactory;
    }

    /**
     * Returns the Parser for converting SQL fragments to ASTs
     */
    public function getParser(): Parser
    {
        return $this->statementFactory->getParser();
    }

    /**
     * Creates an AST representing a complete statement from SQL string
     *
     * @throws SyntaxException
     */
    public function createFromString(string $sql): Statement
    {
        return $this->statementFactory->createFromString($sql);
    }

    /**
     * Creates an object containing SQL statement string and parameter mappings from AST
     */
    public function createFromAST(Statement $ast): NativeStatement
    {
        return $this->statementFactory->createFromAST($ast);
    }

    /**
     * Get the factory object for converters to and from PostgreSQL representation
     */
    public function getTypeConverterFactory(): TypeNameNodeHandler
    {
        return $this->typeConverterFactory;
    }

    /**
     * Returns TypeName node for query AST based on provided type OID
     *
     * @param int|numeric-string $oid
     */
    public function createTypeNameNodeForOID(int|string $oid): TypeName
    {
        return $this->typeConverterFactory->createTypeNameNodeForOID($oid);
    }

    /**
     * Returns the result of calling select() on the table gateway created for the given table name
     *
     * @param FragmentsInput $fragments
     * @param array<string, mixed> $parameters
     */
    public function select(
        string|TableName|QualifiedName $name,
        null|callable|iterable|Fragment|FragmentBuilder $fragments = null,
        array $parameters = []
    ): SelectProxy {
        return $this->createGateway($name)->select($fragments, $parameters);
    }

    /**
     * Loads the previously generated NativeStatement from cache or generates it using given factory method
     *
     * @param callable(): Statement $factoryMethod This will be used to generate the AST in case of cache miss
     * @param string|null           $cacheKey      If not null, NativeStatement will be stored in cache under that key
     */
    public function createNativeStatementUsingCache(callable $factoryMethod, ?string $cacheKey): NativeStatement
    {
        $cacheItem = null;
        if (null !== $cacheKey && null !== $this->statementCache) {
            try {
                $cacheItem = $this->statementCache->getItem($cacheKey);
                if ($cacheItem->isHit()) {
                    /** @psalm-suppress MixedReturnStatement */
                    return $cacheItem->get();
                }
            } catch (PsrException) {
            }
        }

        $native = $this->statementFactory->createFromAST($factoryMethod());

        if (null !== $this->statementCache && null !== $cacheItem) {
            $this->statementCache->save($cacheItem->set($native));
        }

        return $native;
    }

    /**
     * Returns a TableGateway implementation for a given table name
     *
     * Will use an implementation of TableGatewayFactory if available, falling back to returning
     * GenericTableGateway or its subclass based on table's primary key
     */
    public function createGateway(string|TableName|QualifiedName $name): TableGateway
    {
        $definition = $this->getTableDefinition($name);

        foreach ($this->gatewayFactories as $factory) {
            if (null !== ($gateway = $factory->createGateway($definition, $this))) {
                return $gateway;
            }
        }
        return match (\count($definition->getPrimaryKey())) {
            0 => new GenericTableGateway($definition, $this),
            1 => new PrimaryKeyTableGateway($definition, $this),
            default => new CompositePrimaryKeyTableGateway($definition, $this),
        };
    }

    /**
     * Returns a fluent builder for a given table name
     */
    public function createBuilder(string|TableName|QualifiedName $name): FragmentListBuilder
    {
        $definition = $this->getTableDefinition($name);

        foreach ($this->gatewayFactories as $factory) {
            if (null !== ($builder = $factory->createBuilder($definition, $this))) {
                return $builder;
            }
        }
        return new FluentBuilder($definition, $this);
    }

    /**
     * Converts the given name to a TableName instance
     */
    private function normalizeName(string|TableName|QualifiedName $name): TableName
    {
        if (\is_string($name)) {
            return $this->names[$name] ??= TableName::createFromNode($this->getParser()->parseQualifiedName($name));
        } elseif ($name instanceof QualifiedName) {
            return TableName::createFromNode($name);
        } else {
            return $name;
        }
    }

    /**
     * Returns a TableDefinition for a table with the given name
     */
    public function getTableDefinition(string|TableName|QualifiedName $name): TableDefinition
    {
        $normalized = $this->normalizeName($name);

        return $this->definitions[(string)$normalized] ??= $this->getTableDefinitionFactory()
            ->create($normalized);
    }
}
