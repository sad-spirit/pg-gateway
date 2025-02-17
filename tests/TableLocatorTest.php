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
    enums\ConstantName
};
use sad_spirit\pg_builder\nodes\{
    Identifier,
    QualifiedName,
    expressions\KeywordConstant,
    range\UpdateOrDeleteTarget
};
use sad_spirit\pg_gateway\{
    Fragment,
    OrdinaryTableDefinition,
    OrdinaryTableDefinitionFactory,
    StatementType,
    TableDefinition,
    TableGateway,
    TableGatewayFactory,
    TableLocator,
    builders\FragmentListBuilder,
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
class TableLocatorTest extends DatabaseBackedTestCase
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

    public function testDefaultTableDefinitionFactory(): void
    {
        $locator = new TableLocator(self::$connection);
        $this::assertInstanceOf(OrdinaryTableDefinitionFactory::class, $locator->getTableDefinitionFactory());
    }

    public function testGeneratedSqlIsStoredInCache(): void
    {
        $definition = new OrdinaryTableDefinition(self::$connection, new TableName('cols_test', 'simple'));
        $fragment   = new FragmentImplementation(
            new KeywordConstant(ConstantName::TRUE),
            'a key'
        );
        $tableLocatorNoCache = new TableLocator(self::$connection);
        $stmt = $this->createDeleteStatement($tableLocatorNoCache, $definition, $fragment);

        $tableLocatorCacheMiss = new TableLocator(self::$connection, [], null, $this->getMockForCacheMiss($stmt));
        $this->createDeleteStatement($tableLocatorCacheMiss, $definition, $fragment);
    }

    public function testGeneratedSqlIsLoadedFromCache(): void
    {
        $factory = StatementFactory::forConnection(self::$connection);
        $stmt    = $factory->createFromAST($factory->createFromString(
            'delete from public.cols as foo where false'
        ));

        $definition   = new OrdinaryTableDefinition(self::$connection, new TableName('update_test'));
        $tableLocator = new TableLocator(self::$connection, [], null, $this->getMockForCacheHit($stmt));
        $fragment     = new FragmentImplementation(new KeywordConstant(ConstantName::FALSE), 'a key');

        $this::assertSame(
            $stmt,
            $this->createDeleteStatement($tableLocator, $definition, $fragment)
        );
    }

    public function testNoCacheForNullKeyedFragments(): void
    {
        $definition    = new OrdinaryTableDefinition(self::$connection, new TableName('foo'));
        $fragment      = new FragmentImplementation(new KeywordConstant(ConstantName::NULL), null);

        $tableLocator = new TableLocator(self::$connection, [], null, $this->getMockForNoCache());
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

        $definitionOne   = $tableLocator->getTableDefinition('cols');
        $definitionTwo   = $tableLocator->getTableDefinition(' "public" . "cols"  ');
        $definitionThree = $tableLocator->getTableDefinition(new TableName('public', 'cols'));
        $definitionFour  = $tableLocator->getTableDefinition(new QualifiedName('cols'));

        $this::assertSame($definitionOne, $definitionTwo);
        $this::assertSame($definitionTwo, $definitionThree);
        $this::assertSame($definitionThree, $definitionFour);
    }

    public function testAddTableGatewayFactory(): void
    {
        $tableLocator = new TableLocator(self::$connection);

        $this::assertInstanceOf(GenericTableGateway::class, $tableLocator->createGateway('public.cols'));

        $specific    = new SpecificTableGateway($tableLocator);
        $mockFactory = $this->createMock(TableGatewayFactory::class);
        $mockFactory->expects($this::once())
            ->method('createGateway')
            ->willReturn($specific);

        $tableLocator->addTableGatewayFactory($mockFactory);
        $this::assertSame($specific, $tableLocator->createGateway('public.cols'));
    }

    public function testFirstApplicableFactoryCreatesGateway(): void
    {
        $gatewayOne = $this->createMock(TableGateway::class);
        $gatewayTwo = $this->createMock(TableGateway::class);

        $factoryOne = $this->createMock(TableGatewayFactory::class);
        $factoryOne->expects($this::once())
            ->method('createGateway')
            ->willReturn($gatewayOne);
        $factoryTwo = $this->createMock(TableGatewayFactory::class);
        $factoryTwo->expects($this::once())
            ->method('createGateway')
            ->willReturn($gatewayTwo);

        $locatorOne = new TableLocator(self::$connection, [$factoryOne, $factoryTwo]);
        $locatorTwo = new TableLocator(self::$connection, [$factoryTwo, $factoryOne]);

        $this::assertSame($gatewayOne, $locatorOne->createGateway('public.cols'));
        $this::assertSame($gatewayTwo, $locatorTwo->createGateway('public.cols'));
    }

    public function testFirstApplicableFactoryCreatesBuilder(): void
    {
        $builderOne = $this->createMock(FragmentListBuilder::class);
        $builderTwo = $this->createMock(FragmentListBuilder::class);

        $factoryOne = $this->createMock(TableGatewayFactory::class);
        $factoryOne->expects($this::once())
            ->method('createBuilder')
            ->willReturn($builderOne);
        $factoryTwo = $this->createMock(TableGatewayFactory::class);
        $factoryTwo->expects($this::once())
            ->method('createBuilder')
            ->willReturn($builderTwo);

        $locatorOne = new TableLocator(self::$connection, [$factoryOne, $factoryTwo]);
        $locatorTwo = new TableLocator(self::$connection, [$factoryTwo, $factoryOne]);

        $this::assertSame($builderOne, $locatorOne->createBuilder('public.cols'));
        $this::assertSame($builderTwo, $locatorTwo->createBuilder('public.cols'));
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
                StatementType::Delete->value,
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
