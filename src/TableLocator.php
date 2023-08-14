<?php

/*
 * This file is part of sad_spirit/pg_gateway package
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace sad_spirit\pg_gateway;

use sad_spirit\pg_gateway\exceptions\UnexpectedValueException;
use sad_spirit\pg_wrapper\{
    Connection,
    converters\DefaultTypeConverterFactory
};
use sad_spirit\pg_builder\{
    Parser,
    Statement,
    NativeStatement,
    StatementFactory,
    converters\TypeNameNodeHandler,
    converters\BuilderSupportDecorator,
    exceptions\SyntaxException,
    nodes\TypeName
};
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException as PsrException;

/**
 * TableLocator is a facade to pg_gateway features and a means to create table gateways
 */
class TableLocator
{
    private Connection $connection;
    private ?TableGatewayFactory $gatewayFactory;
    private StatementFactory $statementFactory;
    private TypeNameNodeHandler $typeConverterFactory;
    private ?CacheItemPoolInterface $statementCache;


    /**
     * Computes a reasonably unique hash of a value.
     *
     * Borrowed from Symfony DI component, intended for generating fragment keys.
     *
     * @param mixed $value A serializable value
     * @return string
     */
    public static function hash($value): string
    {
        $hash = \substr(\base64_encode(\hash('sha256', \serialize($value), true)), 0, 8);

        return \strtr($hash, '/+', '._');
    }

    public function __construct(
        Connection $connection,
        ?TableGatewayFactory $gatewayFactory = null,
        ?StatementFactory $statementFactory = null,
        ?CacheItemPoolInterface $statementCache = null
    ) {
        $this->connection       = $connection;
        $this->gatewayFactory   = $gatewayFactory;
        $this->statementFactory = $statementFactory ?? StatementFactory::forConnection($connection);
        $this->statementCache   = $statementCache;

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
     * Returns the DB connection object used by TableLocator
     *
     * @return Connection
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
    public function atomic(callable $callback, bool $savepoint = false)
    {
        return $this->connection->atomic(fn() => $callback($this, $this->connection), $savepoint);
    }

    /**
     * Returns the StatementFactory object used to convert queries from SQL strings to AST and back
     *
     * @return StatementFactory
     */
    public function getStatementFactory(): StatementFactory
    {
        return $this->statementFactory;
    }

    /**
     * Returns the Parser for converting SQL fragments to ASTs
     *
     * @return Parser
     */
    public function getParser(): Parser
    {
        return $this->statementFactory->getParser();
    }

    /**
     * Creates an AST representing a complete statement from SQL string
     *
     * @param string $sql
     * @return Statement
     * @throws SyntaxException
     */
    public function createFromString(string $sql): Statement
    {
        return $this->statementFactory->createFromString($sql);
    }

    /**
     * Creates an object containing SQL statement string and parameter mappings from AST
     *
     * @param Statement $ast
     * @return NativeStatement
     */
    public function createFromAST(Statement $ast): NativeStatement
    {
        return $this->statementFactory->createFromAST($ast);
    }

    /**
     * Get the factory object for converters to and from PostgreSQL representation
     *
     * @return TypeNameNodeHandler
     */
    public function getTypeConverterFactory(): TypeNameNodeHandler
    {
        return $this->typeConverterFactory;
    }

    /**
     * Returns TypeName node for query AST based on provided type OID
     *
     * @param int|numeric-string $oid
     * @return TypeName
     */
    public function createTypeNameNodeForOID($oid): TypeName
    {
        return $this->typeConverterFactory->createTypeNameNodeForOID($oid);
    }

    /**
     * Loads the previously generated NativeStatement from cache or generates it using given factory method
     *
     * @param \Closure(): Statement $factoryMethod This will be used to generate the AST in case of cache miss
     * @param string|null           $cacheKey      If not null, NativeStatement will be stored in cache under that key
     *
     * @return NativeStatement
     */
    public function createNativeStatementUsingCache(\Closure $factoryMethod, ?string $cacheKey): NativeStatement
    {
        $cacheItem = null;
        if (null !== $cacheKey && null !== $this->statementCache) {
            try {
                $cacheItem = $this->statementCache->getItem($cacheKey);
                if ($cacheItem->isHit()) {
                    return $cacheItem->get();
                }
            } catch (PsrException $e) {
            }
        }

        $native = $this->statementFactory->createFromAST($factoryMethod());

        if (null !== $this->statementCache && null !== $cacheItem) {
            $this->statementCache->save($cacheItem->set($native));
        }

        return $native;
    }
}
