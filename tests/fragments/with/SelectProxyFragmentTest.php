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

namespace sad_spirit\pg_gateway\tests\fragments\with;

use sad_spirit\pg_gateway\{
    OrdinaryTableDefinition,
    TableLocator,
    conditions\ParametrizedCondition,
    gateways\GenericTableGateway,
    metadata\TableName
};
use sad_spirit\pg_gateway\fragments\with\SelectProxyFragment;
use sad_spirit\pg_gateway\tests\{
    assets\ConditionImplementation,
    DatabaseBackedTest,
    NormalizeWhitespace
};
use sad_spirit\pg_builder\Select;
use sad_spirit\pg_builder\StatementFactory;
use sad_spirit\pg_builder\nodes\{
    Identifier,
    expressions\KeywordConstant,
    expressions\NumericConstant,
    lists\IdentifierList
};

class SelectProxyFragmentTest extends DatabaseBackedTest
{
    use NormalizeWhitespace;

    protected static ?GenericTableGateway $gateway;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$gateway = new GenericTableGateway(
            new OrdinaryTableDefinition(
                self::$connection,
                new TableName('pg_catalog', 'pg_class')
            ),
            new TableLocator(self::$connection)
        );
    }

    public static function tearDownAfterClass(): void
    {
        self::$gateway    = null;
        self::$connection = null;
    }

    public function testKeyDependsOnSelectKey(): void
    {
        $select   = self::$gateway->select();
        $fragment = new SelectProxyFragment($select, new Identifier('foo'));

        $this::assertNotEquals($select->getKey(), $fragment->getKey());
        $this::assertStringContainsString($select->getKey(), $fragment->getKey());
    }

    public function testKeyIsNullForNullSelectKey(): void
    {
        $selectNullKey = self::$gateway->select(function (Select $select) {
            $select->limit = new NumericConstant('10');
        });
        $fragment      = new SelectProxyFragment($selectNullKey, new Identifier('foo'));

        $this::assertNull($fragment->getKey());
    }

    public function testKeyDependsOnAlias(): void
    {
        $select      = self::$gateway->select();
        $fragmentFoo = new SelectProxyFragment($select, new Identifier('foo'));
        $fragmentBar = new SelectProxyFragment($select, new Identifier('bar'));

        $this::assertNotNull($fragmentFoo->getKey());
        $this::assertNotNull($fragmentBar->getKey());
        $this::assertNotEquals($fragmentFoo->getKey(), $fragmentBar->getKey());
    }

    public function testKeyDependsOnColumnAliases(): void
    {
        $select      = self::$gateway->select();
        $fragmentOne = new SelectProxyFragment(
            $select,
            new Identifier('foo'),
            new IdentifierList(['one'])
        );
        $fragmentTwo = new SelectProxyFragment(
            $select,
            new Identifier('foo'),
            new IdentifierList(['two'])
        );

        $this::assertNotNull($fragmentOne->getKey());
        $this::assertNotNull($fragmentTwo->getKey());
        $this::assertNotEquals($fragmentOne->getKey(), $fragmentTwo->getKey());
    }

    public function testKeyDependsOnMaterialized(): void
    {
        $select                  = self::$gateway->select();
        $fragmentMaterialized    = new SelectProxyFragment(
            $select,
            new Identifier('foo'),
            new IdentifierList(['one', 'two']),
            false
        );
        $fragmentNotMaterialized = new SelectProxyFragment(
            $select,
            new Identifier('foo'),
            new IdentifierList(['one', 'two']),
            true
        );

        $this::assertNotNull($fragmentMaterialized->getKey());
        $this::assertNotNull($fragmentNotMaterialized->getKey());
        $this::assertNotEquals($fragmentMaterialized->getKey(), $fragmentNotMaterialized->getKey());
    }

    public function testKeyDependsOnRecursive(): void
    {
        $select            = self::$gateway->select();
        $fragmentRecursive = new SelectProxyFragment(
            $select,
            new Identifier('foo'),
            new IdentifierList(['one', 'two']),
            null,
            true
        );
        $fragmentNotRecursive = new SelectProxyFragment(
            $select,
            new Identifier('foo'),
            new IdentifierList(['one', 'two']),
            null,
            false
        );

        $this::assertNotNull($fragmentRecursive->getKey());
        $this::assertNotNull($fragmentNotRecursive->getKey());
        $this::assertNotEquals($fragmentRecursive->getKey(), $fragmentNotRecursive->getKey());
    }

    public function testGetParameters(): void
    {
        $select   = self::$gateway->select(new ParametrizedCondition(
            new ConditionImplementation(new KeywordConstant(KeywordConstant::TRUE)),
            ['name' => 'value']
        ));
        $fragment = new SelectProxyFragment($select, new Identifier('alias'));

        $this::assertEquals(
            ['name' => 'value'],
            $fragment->getParameterHolder()->getParameters()
        );
    }

    public function testApplyToStatement(): void
    {
        $select   = self::$gateway->select();
        $fragment = new SelectProxyFragment(
            $select,
            new Identifier('foo'),
            new IdentifierList(['one', 'two']),
            false,
            true
        );

        $ast = $select->createSelectAST();
        $fragment->applyTo($ast);

        $this::assertStringEqualsStringNormalizingWhitespace(
            'with recursive foo (one, two) as not materialized ( select self.* from pg_catalog.pg_class as self )'
            . ' select self.* from pg_catalog.pg_class as self',
            StatementFactory::forConnection(self::$connection)
                ->createFromAST($ast)
                ->getSql()
        );
    }
}
