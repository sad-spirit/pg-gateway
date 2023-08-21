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

namespace sad_spirit\pg_gateway\tests\fragments;

use sad_spirit\pg_gateway\tests\assets\ConditionImplementation;
use sad_spirit\pg_gateway\tests\DatabaseBackedTest;
use sad_spirit\pg_gateway\{
    Fragment,
    TableLocator,
    conditions\ParametrizedCondition,
    conditions\SqlStringCondition,
    fragments\JoinFragment,
    fragments\JoinStrategy,
    gateways\GenericTableGateway
};
use sad_spirit\pg_gateway\fragments\join_strategies\InlineStrategy;
use sad_spirit\pg_builder\{
    Select,
    SelectCommon,
    Statement,
    StatementFactory
};
use sad_spirit\pg_builder\nodes\{
    QualifiedName,
    ScalarExpression,
    expressions\KeywordConstant,
    expressions\NumericConstant
};

class JoinFragmentTest extends DatabaseBackedTest
{
    protected static ?GenericTableGateway $gateway;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$gateway = new GenericTableGateway(
            new QualifiedName('pg_catalog', 'pg_class'),
            new TableLocator(self::$connection)
        );
    }

    public static function tearDownAfterClass(): void
    {
        self::$gateway    = null;
        self::$connection = null;
    }

    public function testKeyDependsOnConstructorArguments(): void
    {
        $select    = self::$gateway->select();
        $condition = new SqlStringCondition(
            StatementFactory::forConnection(self::$connection)->getParser(),
            'relkind = :kind'
        );
        $strategy  = new InlineStrategy();
        $fragment  = new JoinFragment($select, $condition, $strategy);

        $this::assertStringContainsString($select->getKey(), $fragment->getKey());
        $this::assertStringContainsString($condition->getKey(), $fragment->getKey());
        $this::assertStringContainsString($strategy->getKey(), $fragment->getKey());
    }

    public function testKeyIsNotNullForMissingCondition(): void
    {
        $select   = self::$gateway->select();
        $strategy = new InlineStrategy();
        $fragment = new JoinFragment($select, null, $strategy);

        $this::assertNotNull($fragment->getKey());
    }

    public function testKeyIsNullForNullSelectKey(): void
    {
        $select    = self::$gateway->select(function (Select $select) {
            $select->limit = new NumericConstant('10');
        });
        $condition = new SqlStringCondition(
            StatementFactory::forConnection(self::$connection)->getParser(),
            'relkind = :kind'
        );
        $fragment  = new JoinFragment($select, $condition);

        $this::assertNull($fragment->getKey());
    }

    public function testKeyIsNullForNullConditionKey(): void
    {
        $select    = self::$gateway->select();
        $condition = new ConditionImplementation(new KeywordConstant(KeywordConstant::TRUE), null);
        $fragment  = new JoinFragment($select, $condition);

        $this::assertNull($fragment->getKey());
    }

    public function testKeyIsNullForNullStrategyKey(): void
    {
        $select    = self::$gateway->select();
        $condition = new SqlStringCondition(
            StatementFactory::forConnection(self::$connection)->getParser(),
            'relkind = :kind'
        );
        $strategy  = new class implements JoinStrategy {
            public function join(
                Statement $statement,
                SelectCommon $joined,
                ?ScalarExpression $condition,
                string $alias,
                bool $isCount
            ): void {
                // no-op
            }

            public function getKey(): ?string
            {
                return null;
            }
        };
        $fragment  = new JoinFragment($select, $condition, $strategy);

        $this::assertNull($fragment->getKey());
    }

    public function testKeyDependsOnExplicitAlias(): void
    {
        $select    = self::$gateway->select();
        $condition = new SqlStringCondition(
            StatementFactory::forConnection(self::$connection)->getParser(),
            'relkind = :kind'
        );
        $strategy  = new InlineStrategy();

        $noAlias      = new JoinFragment($select, $condition, $strategy);
        $aliasOne     = new JoinFragment($select, $condition, $strategy, true, Fragment::PRIORITY_DEFAULT, 'one');
        $aliasOneMore = new JoinFragment($select, $condition, $strategy, true, Fragment::PRIORITY_DEFAULT, 'one');
        $aliasTwo     = new JoinFragment($select, $condition, $strategy, true, Fragment::PRIORITY_DEFAULT, 'two');

        $this::assertNotEquals($noAlias->getKey(), $aliasOne->getKey());
        $this::assertEquals($aliasOne->getKey(), $aliasOneMore->getKey());
        $this::assertNotEquals($aliasOne->getKey(), $aliasTwo->getKey());
    }

    public function testGetParameters(): void
    {
        $select    = self::$gateway->select(null, ['foo' => 'bar']);
        $condition = new ConditionImplementation(new KeywordConstant(KeywordConstant::TRUE));
        $fragment  = new JoinFragment(
            $select,
            new ParametrizedCondition($condition, ['name' => 'value'])
        );

        $this::assertEquals(
            ['foo' => 'bar', 'name' => 'value'],
            $fragment->getParameterHolder()->getParameters()
        );
    }
}
