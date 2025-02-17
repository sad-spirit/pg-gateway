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

/** @noinspection SqlResolve */

declare(strict_types=1);

namespace sad_spirit\pg_gateway\tests\conditions;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_gateway\{
    metadata\TablePrimaryKey,
    TableGateway,
    exceptions\InvalidArgumentException,
    exceptions\UnexpectedValueException,
    metadata\Column,
    metadata\PrimaryKey,
    tests\NormalizeWhitespace};
use sad_spirit\pg_gateway\conditions\PrimaryKeyCondition;
use sad_spirit\pg_builder\{
    SqlBuilderWalker,
    StatementFactory,
    converters\TypeNameNodeHandler,
    nodes\QualifiedName,
    nodes\TypeName
};

class PrimaryKeyConditionTest extends TestCase
{
    use NormalizeWhitespace;

    private function getPrimaryKeyMock(array $columnNames): PrimaryKey
    {
        $reflection = new \ReflectionClass(TablePrimaryKey::class);
        $mock       = $reflection->newInstanceWithoutConstructor();
        $property   = $reflection->getProperty('columns');
        $property->setValue($mock, \array_map(
            fn (string $name): Column => new Column($name, true, 25),
            $columnNames
        ));

        return $mock;
    }

    private function getConverterFactoryMock(): TypeNameNodeHandler
    {
        $mock = $this->createMock(TypeNameNodeHandler::class);

        $mock->expects($this->any())
            ->method('createTypeNameNodeForOID')
            ->willReturnCallback(fn (): TypeName => new TypeName(new QualifiedName('int5')));

        return $mock;
    }

    public function testMissingPrimaryKeyInfo(): void
    {
        $this::expectException(UnexpectedValueException::class);
        $this::expectExceptionMessage('No columns');
        new PrimaryKeyCondition($this->getPrimaryKeyMock([]), $this->getConverterFactoryMock());
    }

    public function testKeyDependsOnColumns(): void
    {
        $fooOne = new PrimaryKeyCondition($this->getPrimaryKeyMock(['foo']), $this->getConverterFactoryMock());
        $fooTwo = new PrimaryKeyCondition($this->getPrimaryKeyMock(['foo']), $this->getConverterFactoryMock());
        $fooBar = new PrimaryKeyCondition($this->getPrimaryKeyMock(['foo', 'bar']), $this->getConverterFactoryMock());

        $this::assertNotNull($fooOne->getKey());
        $this::assertStringNotContainsString('foo', $fooOne->getKey());
        $this::assertSame($fooOne->getKey(), $fooTwo->getKey());
        $this::assertNotSame($fooOne->getKey(), $fooBar->getKey());
    }

    public function testNormalizeValueSingleColumn(): void
    {
        $foo = new PrimaryKeyCondition($this->getPrimaryKeyMock(['foo']), $this->getConverterFactoryMock());

        $this::assertEquals(['foo' => 5], $foo->normalizeValue(['foo' => 5]));
        $this::assertEquals(['foo' => 5], $foo->normalizeValue(5));
        $this::assertEquals(['foo' => new \stdClass()], $foo->normalizeValue(new \stdClass()));
    }

    public function testNormalizeValueMultipleColumns(): void
    {
        $foobar = new PrimaryKeyCondition($this->getPrimaryKeyMock(['foo', 'bar']), $this->getConverterFactoryMock());

        $this::assertEquals(
            ['foo' => 'value', 'bar' => 'another value'],
            $foobar->normalizeValue(['foo' => 'value', 'bar' => 'another value'])
        );

        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('Expecting an array');
        $foobar->normalizeValue(new \stdClass());
    }

    public function testMissingPrimaryKeyColumn(): void
    {
        $foo = new PrimaryKeyCondition($this->getPrimaryKeyMock(['foo']), $this->getConverterFactoryMock());

        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('not found');
        $foo->normalizeValue(['bar' => 'baz']);
    }

    public function testExtraFieldInArray(): void
    {
        $foo = new PrimaryKeyCondition($this->getPrimaryKeyMock(['foo']), $this->getConverterFactoryMock());

        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('do not correspond to primary key columns');
        $foo->normalizeValue(['foo' => 'value', 'bar' => 'baz']);
    }

    public function testAddToStatement(): void
    {
        $factory   = new StatementFactory();

        $delete    = $factory->delete('some_table');
        $condition = new PrimaryKeyCondition(
            $this->getPrimaryKeyMock(['foo', 'bar']),
            $this->getConverterFactoryMock()
        );
        $condition->getFragment()->applyTo($delete);

        $this::assertStringEqualsStringNormalizingWhitespace(
            'delete from some_table where ' . TableGateway::ALIAS_SELF . '.foo = :foo::int5 and '
            . TableGateway::ALIAS_SELF . '.bar = :bar::int5',
            $delete->dispatch(new SqlBuilderWalker())
        );
    }
}
