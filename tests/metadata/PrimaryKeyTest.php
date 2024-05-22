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

namespace sad_spirit\pg_gateway\tests\metadata;

use sad_spirit\pg_gateway\{
    exceptions\UnexpectedValueException,
    metadata\Column,
    metadata\PrimaryKey,
    metadata\TableName,
    tests\DatabaseBackedTest
};

class PrimaryKeyTest extends DatabaseBackedTest
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::executeSqlFromFile(
            self::$connection,
            'primary-key-drop.sql',
            'primary-key-create.sql',
            'composite-primary-key-create.sql'
        );
    }

    public static function tearDownAfterClass(): void
    {
        self::executeSqlFromFile(self::$connection, 'primary-key-drop.sql');
        self::$connection = null;
    }

    public function testFailsOnNonExistentRelation(): void
    {
        $this::expectException(UnexpectedValueException::class);
        $this::expectExceptionMessage('does not exist');
        new PrimaryKey(self::$connection, new TableName('pkey_test', 'missing'));
    }

    public function testFailsOnNonTable(): void
    {
        $this::expectException(UnexpectedValueException::class);
        $this::expectExceptionMessage('is not a table');
        new PrimaryKey(self::$connection, new TableName('pkey_test', 'explicit_seq'));
    }

    public function testDefaultsToPublicSchema(): void
    {
        $pkey = new PrimaryKey(self::$connection, new TableName('haskey'));

        $this::assertCount(1, $pkey);
        $this::assertEquals([new Column('id', false, 23)], $pkey->getAll());
        $this::assertFalse($pkey->isGenerated());
    }

    public function testMissingPrimaryKey(): void
    {
        $pkey = new PrimaryKey(self::$connection, new TableName('pkey_test', 'nokey'));

        $this::assertCount(0, $pkey);
        $this::assertEquals([], $pkey->getAll());
        $this::assertFalse($pkey->isGenerated());
    }

    public function testCompositePrimaryKey(): void
    {
        $pkey = new PrimaryKey(self::$connection, new TableName('pkey_test', 'composite'));

        $this::assertCount(3, $pkey);
        $this::assertEquals(['e_id', 's_id', 'i_id'], $pkey->getNames());
        $this::assertFalse($pkey->isGenerated());
    }

    public function testGeneratedPrimaryKeyUsingSQLStandardSyntax(): void
    {
        $pkey = new PrimaryKey(self::$connection, new TableName('pkey_test', 'standard'));

        $this::assertEquals(['i_id'], $pkey->getNames());
        $this::assertTrue($pkey->isGenerated());
    }

    public function testGeneratedPrimaryKeyUsingSerial(): void
    {
        $pkey = new PrimaryKey(self::$connection, new TableName('pkey_test', 'serial'));

        $this::assertEquals(['s_id'], $pkey->getNames());
        $this::assertTrue($pkey->isGenerated());
    }

    public function testGeneratedPrimaryKeyUsingDefaultNextval(): void
    {
        $pkey = new PrimaryKey(self::$connection, new TableName('pkey_test', 'explicit'));

        $this::assertEquals(['e_id'], $pkey->getNames());
        $this::assertTrue($pkey->isGenerated());
    }

    public function testMetadataIsStoredInCache(): void
    {
        self::$connection->setMetadataCache($this->getMockForCacheMiss(
            [[new Column('i_id', false, 23)], true]
        ));
        new PrimaryKey(self::$connection, new TableName('pkey_test', 'standard'));
    }

    public function testMetadataIsLoadedFromCache(): void
    {
        self::$connection->setMetadataCache($this->getMockForCacheHit(
            [[new Column('i_id', false, 23)], true]
        ));
        $pkey = new PrimaryKey(self::$connection, new TableName('pkey_test', 'standard'));
        $this::assertEquals(['i_id'], $pkey->getNames());
        $this::assertTrue($pkey->isGenerated());
    }
}
