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
 * @noinspection SqlResolve
 * @noinspection SqlCheckUsingColumns
 * @noinspection SqlWithoutWhere
 */

declare(strict_types=1);

namespace sad_spirit\pg_gateway\tests\builders;

use sad_spirit\pg_gateway\{
    FragmentList,
    TableLocator,
    builders\ColumnsBuilder,
    exceptions\OutOfBoundsException,
    exceptions\InvalidArgumentException,
    exceptions\UnexpectedValueException,
    gateways\GenericTableGateway,
    gateways\PrimaryKeyTableGateway,
    metadata\TableName,
    tests\DatabaseBackedTest,
    tests\NormalizeWhitespace
};

class ColumnsBuilderTest extends DatabaseBackedTest
{
    use NormalizeWhitespace;

    protected static ?TableLocator $tableLocator;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$tableLocator = new TableLocator(self::$connection);
        self::executeSqlFromFile(self::$connection, 'foreign-key-drop.sql', 'foreign-key-create.sql');
    }

    public static function tearDownAfterClass(): void
    {
        self::executeSqlFromFile(self::$connection, 'foreign-key-drop.sql');
        self::$tableLocator = null;
        self::$connection = null;
    }

    public function testStarIsANoOpForSelect(): void
    {
        $gateway = self::$tableLocator->get(new TableName('fkey_test', 'documents'));
        $fragmentDefault = (new ColumnsBuilder($gateway, false))
            ->getFragment();
        $fragmentStar = (new ColumnsBuilder($gateway, false))
            ->star()
            ->getFragment();

        $this::assertEquals($fragmentStar, $fragmentDefault);

        $select = $gateway->select($fragmentStar);

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select self.* from fkey_test.documents as self',
            self::$tableLocator->getStatementFactory()->createFromAST($select->createSelectAST())->getSql()
        );
    }

    public function testNoneIsANoOpForReturningClause(): void
    {
        /** @var GenericTableGateway $gateway */
        $gateway = self::$tableLocator->get(new TableName('fkey_test', 'documents'));
        $fragmentDefault = (new ColumnsBuilder($gateway, true))
            ->getFragment();
        $fragmentNone = (new ColumnsBuilder($gateway, true))
            ->none()
            ->getFragment();

        $this::assertEquals($fragmentNone, $fragmentDefault);

        $delete = $gateway->createDeleteStatement(new FragmentList($fragmentNone));

        $this::assertStringEqualsStringNormalizingWhitespace(
            'delete from fkey_test.documents as self',
            $delete->getSql()
        );
    }

    public function testNoColumns(): void
    {
        $gateway = self::$tableLocator->get('employees');
        $select  = $gateway->select(
            (new ColumnsBuilder($gateway, false))
                ->none()
        );

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select from public.employees as self',
            self::$tableLocator->getStatementFactory()->createFromAST($select->createSelectAST())->getSql()
        );
    }

    public function testAllColumns(): void
    {
        $gateway = self::$tableLocator->get('employees');
        $select  = $gateway->select(
            (new ColumnsBuilder($gateway, false))
                ->all()
                ->replace('/^/', 'employee_')
        );

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select self.id as employee_id, self."name" as employee_name from public.employees as self',
            self::$tableLocator->getStatementFactory()->createFromAST($select->createSelectAST())->getSql()
        );
    }

    public function testOnlyDisallowsChoosingNoColumns(): void
    {
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('should not be empty');

        (new ColumnsBuilder(self::$tableLocator->get('employees'), false))
            ->only([]);
    }

    public function testOnlyChecksColumnNames(): void
    {
        $this::expectException(OutOfBoundsException::class);
        $this::expectExceptionMessage('unknown value');

        (new ColumnsBuilder(self::$tableLocator->get('employees'), false))
            ->only(['id', 'fcuk']);
    }

    public function testExceptShouldOmitSomeColumns(): void
    {
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('should not be empty');

        (new ColumnsBuilder(self::$tableLocator->get('employees'), false))
            ->except([]);
    }

    public function testExceptShouldNotOmitAllColumns(): void
    {
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('only a subset');

        (new ColumnsBuilder(self::$tableLocator->get('employees'), false))
            ->except(['id', 'name']);
    }

    public function testExceptChecksColumnNames(): void
    {
        $this::expectException(OutOfBoundsException::class);
        $this::expectExceptionMessage('unknown value');

        (new ColumnsBuilder(self::$tableLocator->get('employees'), false))
            ->except(['id', 'fcuk']);
    }

    public function testExceptColumns(): void
    {
        $gateway = self::$tableLocator->get(new TableName('fkey_test', 'documents'));
        $select  = $gateway->select(
            (new ColumnsBuilder($gateway, false))
                ->except(['contents'])
        );

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select self.id, self.employee_id, self.boss_id, self.parent_id from fkey_test.documents as self',
            self::$tableLocator->getStatementFactory()->createFromAST($select->createSelectAST())->getSql()
        );
    }

    public function testPrimaryKeyShouldContainColumns(): void
    {
        $this::expectException(UnexpectedValueException::class);
        $this::expectExceptionMessage('No columns');

        (new ColumnsBuilder(
            self::$tableLocator->get(new TableName('fkey_test', 'documents_tags')),
            false
        ))
            ->primaryKey();
    }

    public function testPrimaryKeyColumns(): void
    {
        $gateway = new PrimaryKeyTableGateway(new TableName('employees'), self::$tableLocator);
        $select  = $gateway->select(
            (new ColumnsBuilder($gateway, false))
                ->primaryKey()
        );

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select self.id from public.employees as self',
            self::$tableLocator->getStatementFactory()->createFromAST($select->createSelectAST())->getSql()
        );
    }
}
