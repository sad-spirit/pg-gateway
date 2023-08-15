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
    QualifiedName,
    expressions\KeywordConstant,
    range\UpdateOrDeleteTarget
};
use sad_spirit\pg_gateway\{
    Fragment,
    TableDefinition,
    TableGateway,
    TableLocator,
    exceptions\UnexpectedValueException
};
use sad_spirit\pg_gateway\tests\assets\{
    FragmentImplementation,
    TableDefinitionImplementation
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

        self::executeSqlFromFile(self::$connection, 'columns-drop.sql', 'columns-create.sql');
    }

    public static function tearDownAfterClass(): void
    {
        self::executeSqlFromFile(self::$connection, 'columns-drop.sql');
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
        $definition = new TableDefinitionImplementation(self::$connection, new QualifiedName('cols_test', 'simple'));
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

        $definition   = new TableDefinitionImplementation(self::$connection, new QualifiedName('update_test'));
        $tableLocator = new TableLocator(self::$connection, null, null, $this->getMockForCacheHit($stmt));
        $fragment     = new FragmentImplementation(new KeywordConstant(KeywordConstant::FALSE), 'a key');

        $this::assertSame(
            $stmt,
            $this->createDeleteStatement($tableLocator, $definition, $fragment)
        );
    }

    public function testNoCacheForNullKeyedFragments(): void
    {
        $definition    = new TableDefinitionImplementation(self::$connection, new QualifiedName('foo'));
        $fragment      = new FragmentImplementation(new KeywordConstant(KeywordConstant::NULL), null);

        $tableLocator = new TableLocator(self::$connection, null, null, $this->getMockForNoCache());
        $this->createDeleteStatement($tableLocator, $definition, $fragment);
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
                $definition->getConnection()->getConnectionId(),
                TableGateway::STATEMENT_DELETE,
                TableLocator::hash($definition->getName()),
                $fragmentKey
            );
        }

        return $tableLocator->createNativeStatementUsingCache(
            function () use ($tableLocator, $definition, $fragment): Delete {
                $delete = $tableLocator->getStatementFactory()->delete(new UpdateOrDeleteTarget(
                    $definition->getName(),
                    new Identifier('foo')
                ));
                $fragment->applyTo($delete);
                return $delete;
            },
            $cacheKey
        );
    }
}
