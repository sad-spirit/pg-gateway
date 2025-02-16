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

namespace sad_spirit\pg_gateway\tests\conditions;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_builder\SqlBuilderWalker;
use sad_spirit\pg_gateway\{
    conditions\ForeignKeyCondition,
    metadata\ForeignKey,
    metadata\TableName
};

class ForeignKeyConditionTest extends TestCase
{
    public function testSingleColumnForeignKey(): void
    {
        $foreignKey = new ForeignKey(
            new TableName('bar'),
            ['foo_id'],
            new TableName('foo'),
            ['id'],
            'test_key'
        );

        $childToReference = new ForeignKeyCondition($foreignKey, true);
        $referenceToChild = new ForeignKeyCondition($foreignKey, false);

        $this::assertEquals(
            'self.foo_id = joined.id',
            $childToReference->generateExpression()->dispatch(new SqlBuilderWalker())
        );
        $this::assertEquals(
            'joined.foo_id = self.id',
            $referenceToChild->generateExpression()->dispatch(new SqlBuilderWalker())
        );

        $this::assertNotNull($childToReference->getKey());
        $this::assertNotNull($referenceToChild->getKey());
        $this::assertNotEquals($childToReference->getKey(), $referenceToChild->getKey());
    }

    public function testMultipleColumnsForeignKey(): void
    {
        $foreignKey = new ForeignKey(
            new TableName('foobar_related'),
            ['foobar_one', 'foobar_two'],
            new TableName('foobar'),
            ['one', 'two'],
            'test_multi_key'
        );

        $childToReference = new ForeignKeyCondition($foreignKey, true);
        $referenceToChild = new ForeignKeyCondition($foreignKey, false);

        $this::assertEquals(
            'self.foobar_one = joined.one and self.foobar_two = joined.two',
            $childToReference->generateExpression()->dispatch(new SqlBuilderWalker())
        );
        $this::assertEquals(
            'joined.foobar_one = self.one and joined.foobar_two = self.two',
            $referenceToChild->generateExpression()->dispatch(new SqlBuilderWalker())
        );
    }
}
