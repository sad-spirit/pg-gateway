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

namespace sad_spirit\pg_gateway\tests\gateways;

use sad_spirit\pg_gateway\{
    TableLocator,
    exceptions\InvalidArgumentException,
    gateways\CompositePrimaryKeyTableGateway,
    tests\DatabaseBackedTest
};
use sad_spirit\pg_builder\{
    Select,
    nodes\QualifiedName
};

/**
 * Tests methods from CompositePrimaryKeyTableGateway
 */
class CompositePrimaryKeyTableGatewayTest extends DatabaseBackedTest
{
    protected static ?CompositePrimaryKeyTableGateway $gateway;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$gateway = new CompositePrimaryKeyTableGateway(
            new QualifiedName('pkey_test', 'composite'),
            new TableLocator(self::$connection)
        );
        self::executeSqlFromFile(self::$connection, 'primary-key-drop.sql', 'composite-primary-key-create.sql');

        foreach (
            [
                [1, 2, 3],
                [1, 3, 4],
                [2, 3, 4],
                [2, 3, 5]
            ] as [$e, $s, $i]
        ) {
            self::$gateway->insert(['e_id' => $e, 's_id' => $s, 'i_id' => $i]);
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::executeSqlFromFile(self::$connection, 'primary-key-drop.sql');
        self::$gateway = null;
        self::$connection = null;
    }

    public function testAtLeastOneColumnRequiredForReplaceRelated(): void
    {
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('at least one column');

        self::$gateway->replaceRelated(
            [],
            [['e_id' => 1, 's_id' => 2, 'i_id' => 3]]
        );
    }

    public function testPrimaryKeyColumnRequiredForReplaceRelated(): void
    {
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('not a part of primary key');

        self::$gateway->replaceRelated(
            ['foo' => 1],
            [['e_id' => 1, 's_id' => 2, 'i_id' => 3]]
        );
    }

    public function testPrimaryKeyPartShouldNotContainAllColumns(): void
    {
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('should contain only a subset');

        self::$gateway->replaceRelated(
            ['e_id' => 1, 's_id' => 2, 'i_id' => 3],
            [[]]
        );
    }

    public function testReplaceRelatedOneExtraColumn(): void
    {
        self::$gateway->replaceRelated(
            ['e_id' => 2, 's_id' => 3],
            [
                ['i_id' => 5],
                ['i_id' => 6]
            ]
        );

        $select = self::$gateway->select(static function (Select $select) {
            $select->where->and('e_id = 2');
        });

        $this::assertEqualsCanonicalizing(
            [5, 6],
            $select->getIterator()->fetchColumn('i_id')
        );
        $this::assertGreaterThanOrEqual(4, self::$gateway->select()->executeCount());
    }

    public function testReplaceRelatedTwoExtraColumns(): void
    {
        self::$gateway->replaceRelated(
            ['e_id' => 1],
            [
                ['s_id' => 2, 'i_id' => 3],
                ['s_id' => 3, 'i_id' => 5],
                ['s_id' => 4, 'i_id' => 6]
            ]
        );

        $select = self::$gateway->select(static function (Select $select) {
            $select->list->replace("s_id::text || ':' || i_id::text as si");
            $select->where->and('e_id = 1');
        });

        $this::assertEqualsCanonicalizing(
            ['2:3', '3:5', '4:6'],
            $select->getIterator()->fetchColumn('si')
        );
        $this::assertGreaterThanOrEqual(5, self::$gateway->select()->executeCount());
    }
}
