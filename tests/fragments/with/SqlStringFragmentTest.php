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

namespace sad_spirit\pg_gateway\tests\fragments\with;

use sad_spirit\pg_gateway\{
    OrdinaryTableDefinition,
    TableLocator,
    gateways\GenericTableGateway,
    metadata\TableName,
    tests\DatabaseBackedTestCase,
    tests\NormalizeWhitespace
};
use sad_spirit\pg_gateway\fragments\with\SqlStringFragment;

class SqlStringFragmentTest extends DatabaseBackedTestCase
{
    use NormalizeWhitespace;

    protected static ?GenericTableGateway $gateway;
    protected static ?TableLocator $locator;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$gateway = new GenericTableGateway(
            new OrdinaryTableDefinition(
                self::$connection,
                new TableName('pg_catalog', 'pg_class')
            ),
            self::$locator = new TableLocator(self::$connection)
        );
    }

    public static function tearDownAfterClass(): void
    {
        self::$gateway    = null;
        self::$connection = null;
    }

    public function testKeyDependsOnSql(): void
    {
        $fragmentOne = new SqlStringFragment(self::$locator->getParser(), 'with foo as (select 1)');
        $fragmentTwo = new SqlStringFragment(self::$locator->getParser(), 'foo as (select 1)');

        $this::assertNotNull($fragmentOne->getKey());
        $this::assertNotNull($fragmentTwo->getKey());
        $this::assertNotEquals($fragmentOne->getKey(), $fragmentTwo->getKey());
    }

    public function testParseCompleteWithClause(): void
    {
        $fragment = new SqlStringFragment(
            self::$locator->getParser(),
            'with recursive foo as (select 1), bar as (select 2)'
        );

        $ast = self::$gateway->select()
            ->createSelectAST();
        $fragment->applyTo($ast);

        $this::assertStringEqualsStringNormalizingWhitespace(
            'with recursive foo as ( select 1 ), bar as ( select 2 ) select self.* from pg_catalog.pg_class as self',
            self::$locator->createFromAST($ast)->getSql()
        );
    }

    public function testParseSingleCommonTableExpression(): void
    {
        $fragment = new SqlStringFragment(
            self::$locator->getParser(),
            'baz as (select 3)'
        );

        $ast = self::$gateway->select()
            ->createSelectAST();
        $fragment->applyTo($ast);

        $this::assertStringEqualsStringNormalizingWhitespace(
            'with baz as ( select 3 ) select self.* from pg_catalog.pg_class as self',
            self::$locator->createFromAST($ast)->getSql()
        );
    }
}
