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

namespace sad_spirit\pg_gateway\tests;

use sad_spirit\pg_gateway\{
    OrdinaryTableDefinition,
    OrdinaryTableDefinitionFactory,
    exceptions\InvalidArgumentException,
    metadata\CachedTableOIDMapper,
    metadata\TableName
};

class OrdinaryTableDefinitionFactoryTest extends DatabaseBackedTestCase
{
    private OrdinaryTableDefinitionFactory $factory;

    public function setUp(): void
    {
        $this->factory = new OrdinaryTableDefinitionFactory(
            self::$connection,
            new CachedTableOIDMapper(self::$connection, false)
        );
    }

    public function testCreatesDefinitionForAnOrdinaryTable(): void
    {
        $definition = $this->factory->create(new TableName('pg_catalog', 'pg_class'));
        $this::assertInstanceOf(OrdinaryTableDefinition::class, $definition);
    }

    public function testCannotCreateDefinitionForANonExistentTable(): void
    {
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('does not exist');

        $this->factory->create(new TableName('foo', 'bar'));
    }

    public function testCannotCreateDefinitionForAView(): void
    {
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage("of type 'view'");

        $this->factory->create(new TableName('information_schema', 'tables'));
    }
}
