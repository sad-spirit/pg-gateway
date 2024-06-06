<?php

/*
 * This file is part of sad_spirit/pg_gateway package
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * @noinspection SqlWithoutWhere
 * @noinspection SqlResolve
 */

declare(strict_types=1);

namespace sad_spirit\pg_gateway\tests;

use sad_spirit\pg_builder\{
    Delete,
    Lexer,
    NativeStatement,
    Parser,
    StatementFactory,
    converters\BuilderSupportDecorator,
};
use sad_spirit\pg_builder\nodes\{
    Identifier,
    expressions\KeywordConstant,
    range\UpdateOrDeleteTarget
};
use sad_spirit\pg_gateway\{
    Fragment,
    OrdinaryTableDefinition,
    TableDefinition,
    TableGateway,
    TableGatewayFactory,
    TableLocator,
    exceptions\UnexpectedValueException,
    gateways\CompositePrimaryKeyTableGateway,
    gateways\GenericTableGateway,
    gateways\PrimaryKeyTableGateway,
    metadata\TableName
};
use sad_spirit\pg_gateway\tests\assets\{
    FragmentImplementation,
    SpecificTableGateway
};
use sad_spirit\pg_wrapper\converters\{
    DefaultTypeConverterFactory,
    StringConverter,
    StubTypeConverterFactory
};

/**
 * Test for StatementFactory backed by PSR-6 cache
 */
class TableLocatorTest extends DatabaseBackedTest
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::executeSqlFromFile(
            self::$connection,
            'columns-drop.sql',
            'columns-create.sql',
            'primary-key-drop.sql',
            'primary-key-create.sql',
            'composite-primary-key-create.sql'
        );
    }

    public static function tearDownAfterClass(): void
    {
        self::executeSqlFromFile(self::$connection, 'columns-drop.sql', 'primary-key-drop.sql');
        self::$connection = null;
    }

    public function testAcceptsTypeNameNodeHandler(): void
    {
        $connection = clone self::$connection;
        $connection->setTypeConverterFactory(
            $factory = new BuilderSupportDecorator(new DefaultTypeConverterFactory(), new Parser(new Lexer()))
        );
        $locator    = new TableLocator($connection);

        $this::assertSame($factory, $connection->getTypeConverterFactory());
        $this::assertSame($factory, $locator->getTypeConverterFactory());
    }

    public function testDecoratesDefaultTypeConverterFactory(): void
    {
        $connection = clone self::$connection;
        $connection->setTypeConverterFactory($factory = new DefaultTypeConverterFactory());
        $factory->registerConverter(StringConverter::class, 'unknown', 'unknown');

        $locator = new TableLocator($connection);
        $this::assertInstanceOf(BuilderSupportDecorator::class, $connection->getTypeConverterFactory());
        $this::assertInstanceOf(BuilderSupportDecorator::class, $locator->getTypeConverterFactory());
        $this::assertInstanceOf(
            StringConverter::class,
            $connection->getTypeConverterFactory()->getConverterForTypeSpecification('unknown.unknown')
        );
        $this::assertInstanceOf(
            StringConverter::class,
            $locator->getTypeConverterFactory()->getConverterForTypeSpecification('unknown.unknown')
        );
    }

    public function testCannotDecorateStubConverterFactory(): void
    {
        $connection = clone self::$connection;
        $connection->setTypeConverterFactory(new StubTypeConverterFactory());

        $this::expectException(UnexpectedValueException::class);
        $this::expectExceptionMessage('TypeNameNodeHandler');
        new TableLocator($connection);
    }

    public function testGeneratedSqlIsStoredInCache(): void
    {
        $definition = new OrdinaryTableDefinition(self::$connection, new TableName('cols_test', 'simple'));
        $fragment   = new FragmentImplementation(
            new KeywordConstant(KeywordConstant::TRUE),
            'a key'
        );
        $tableLocatorNoCache = new TableLocator(self::$connection);
        $stmt = $this->createDeleteStatement($tableLocatorNoCache, $definition, $fragment);

        $tableLocatorCacheMiss = new TableLocator(self::$connection, null, null, $this->getMockForCacheMiss($stmt));
        $this->createDeleteStatement($tableLocatorCacheMiss, $definition, $fragment);
    }

    public function testGeneratedSqlIsLoadedFromCache(): void
    {
        $factory = StatementFactory::forConnection(self::$connection);
        $stmt    = $factory->createFromAST($factory->createFromString(
            'delete from public.cols as foo where false'
        ));

        $definition   = new OrdinaryTableDefinition(self::$connection, new TableName('update_test'));
        $tableLocator = new TableLocator(self::$connection, null, null, $this->getMockForCacheHit($stmt));
        $fragment     = new FragmentImplementation(new KeywordConstant(KeywordConstant::FALSE), 'a key');

        $this::assertSame(
            $stmt,
            $this->createDeleteStatement($tableLocator, $definition, $fragment)
        );
    }

    public function testNoCacheForNullKeyedFragments(): void
    {
        $definition    = new OrdinaryTableDefinition(self::$connection, new TableName('foo'));
        $fragment      = new FragmentImplementation(new KeywordConstant(KeywordConstant::NULL), null);

        $tableLocator = new TableLocator(self::$connection, null, null, $this->getMockForNoCache());
        $this->createDeleteStatement($tableLocator, $definition, $fragment);
    }

    public function testCreateDefaultGatewayForNoPrimaryKeyTable(): void
    {
        $tableLocator = new TableLocator(self::$connection);
        $gateway      = $tableLocator->createGateway(new TableName('pkey_test', 'nokey'));

        $this::assertInstanceOf(GenericTableGateway::class, $gateway);
        $this::assertNotInstanceOf(PrimaryKeyTableGateway::class, $gateway);
    }

    public function testCreateDefaultGatewayForSingleColumnPrimaryKey(): void
    {
        $tableLocator = new TableLocator(self::$connection);
        $gateway      = $tableLocator->createGateway(new TableName('haskey'));

        $this::assertInstanceOf(PrimaryKeyTableGateway::class, $gateway);
        $this::assertNotInstanceOf(CompositePrimaryKeyTableGateway::class, $gateway);
    }

    public function testCreateDefaultGatewayForCompositePrimaryKey(): void
    {
        $tableLocator = new TableLocator(self::$connection);
        $gateway      = $tableLocator->createGateway(new TableName('pkey_test', 'composite'));

        $this::assertInstanceOf(CompositePrimaryKeyTableGateway::class, $gateway);
    }


    public function testSameDefinitionForSameName(): void
    {
        $tableLocator = new TableLocator(self::$connection);

        $gateway = $tableLocator->createGateway(new TableName('public', 'cols'));
        $another = $tableLocator->createGateway(' "public" . "cols"  ');
        $this::assertNotSame($gateway, $another);
        $this::assertSame($gateway->getDefinition(), $another->getDefinition());
    }

    public function testGetGatewayUsingFactory(): void
    {
        $tableLocator = new TableLocator(
            self::$connection,
            new class implements TableGatewayFactory {
                public function create(TableDefinition $definition, TableLocator $tableLocator): ?TableGateway
                {
                    if ('zerocolumns' === $definition->getName()->getRelation()) {
                        return new SpecificTableGateway($tableLocator);
                    }
                    return null;
                }
            }
        );

        $specific = $tableLocator->createGateway('cols_test.zerocolumns');
        $this::assertInstanceOf(SpecificTableGateway::class, $specific);

        $generic  = $tableLocator->createGateway('public.cols');
        $this::assertSame(GenericTableGateway::class, \get_class($generic));
    }


    private function createDeleteStatement(
        TableLocator $tableLocator,
        TableDefinition $definition,
        Fragment $fragment
    ): NativeStatement {
        if (null === ($fragmentKey = $fragment->getKey())) {
            $cacheKey = null;
        } else {
            $cacheKey = \sprintf(
                '%s.%s.%s.%s',
                $tableLocator->getConnection()->getConnectionId(),
                TableGateway::STATEMENT_DELETE,
                TableLocator::hash($definition->getName()),
                $fragmentKey
            );
        }

        return $tableLocator->createNativeStatementUsingCache(
            function () use ($tableLocator, $definition, $fragment): Delete {
                $delete = $tableLocator->getStatementFactory()->delete(new UpdateOrDeleteTarget(
                    $definition->getName()->createNode(),
                    new Identifier('foo')
                ));
                $fragment->applyTo($delete);
                return $delete;
            },
            $cacheKey
        );
    }
}
