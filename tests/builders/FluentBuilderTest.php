<?php

/*
 * This file is part of sad_spirit/pg_gateway package
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/** @noinspection SqlResolve */

declare(strict_types=1);

namespace sad_spirit\pg_gateway\tests\builders;

use sad_spirit\pg_gateway\{
    Fragment,
    FragmentList,
    fragments\JoinFragment,
    OrdinaryTableDefinition,
    TableLocator,
    builders\FluentBuilder,
    builders\JoinBuilder,
    conditions\ForeignKeyCondition,
    conditions\ParametrizedCondition,
    exceptions\LogicException,
    exceptions\OutOfBoundsException,
    exceptions\UnexpectedValueException,
    fragments\CustomFragment,
    fragments\LimitClauseFragment,
    fragments\OffsetClauseFragment,
    metadata\TableName,
    tests\DatabaseBackedTestCase,
    tests\NormalizeWhitespace
};
use PHPUnit\Framework\Attributes\DataProvider;
use sad_spirit\pg_builder\{
    Select,
    Statement,
    nodes\QualifiedName
};

class FluentBuilderTest extends DatabaseBackedTestCase
{
    use NormalizeWhitespace;

    protected static ?TableLocator $tableLocator;
    private FluentBuilder $builder;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::executeSqlFromFile(self::$connection, 'update-drop.sql', 'update-create.sql');
        self::executeSqlFromFile(self::$connection, 'foreign-key-drop.sql', 'foreign-key-create.sql');
        self::$tableLocator = new TableLocator(self::$connection);
    }

    public static function tearDownAfterClass(): void
    {
        self::executeSqlFromFile(self::$connection, 'update-drop.sql', 'foreign-key-drop.sql');
        self::$tableLocator = null;
        self::$connection   = null;
    }

    protected function setUp(): void
    {
        $this->builder = new FluentBuilder(
            new OrdinaryTableDefinition(self::$connection, new TableName('update_test')),
            self::$tableLocator
        );
    }

    public function testCreateAny(): void
    {
        $condition = $this->builder->createAny('id', [1, 2]);

        $this::assertStringEqualsStringNormalizingWhitespace(
            'self.id = any(:id::int4[])',
            $condition->generateExpression()->dispatch(
                self::$tableLocator->getStatementFactory()->getBuilder()
            )
        );
        $this::assertEquals(
            ['id' => [1, 2]],
            $condition->getParameterHolder()->getParameters()
        );

        $this::expectException(OutOfBoundsException::class);
        $this::expectExceptionMessage('does not exist');
        $this->builder->createAny('missing', ['foo', 'bar']);
    }

    public function testCreateBoolColumn(): void
    {
        $condition = $this->builder->createBoolColumn('flag');

        $this::assertStringEqualsStringNormalizingWhitespace(
            'self.flag',
            $condition->generateExpression()->dispatch(
                self::$tableLocator->getStatementFactory()->getBuilder()
            )
        );
        $this::assertNotInstanceOf(ParametrizedCondition::class, $condition);

        $this::expectException(OutOfBoundsException::class);
        $this::expectExceptionMessage('does not exist');
        $this->builder->createBoolColumn('missing');
    }

    public function testCreateNotBoolColumn(): void
    {
        $condition = $this->builder->createNotBoolColumn('flag');

        $this::assertStringEqualsStringNormalizingWhitespace(
            'not self.flag',
            $condition->generateExpression()->dispatch(
                self::$tableLocator->getStatementFactory()->getBuilder()
            )
        );
        $this::assertNotInstanceOf(ParametrizedCondition::class, $condition);
    }

    public function testBoolColumnConditionRequiresBoolType(): void
    {
        $this::expectException(LogicException::class);
        $this::expectExceptionMessage("is not of type 'bool'");
        $this->builder->createBoolColumn('id');
    }

    public function testCreateIsNull(): void
    {
        $condition = $this->builder->createIsNull('title');

        $this::assertStringEqualsStringNormalizingWhitespace(
            'self.title is null',
            $condition->generateExpression()->dispatch(
                self::$tableLocator->getStatementFactory()->getBuilder()
            )
        );
        $this::assertNotInstanceOf(ParametrizedCondition::class, $condition);

        $this::expectException(OutOfBoundsException::class);
        $this::expectExceptionMessage('does not exist');
        $this->builder->createIsNull('missing');
    }

    public function testCreateIsNotNull(): void
    {
        $condition = $this->builder->createIsNotNull('title');

        $this::assertStringEqualsStringNormalizingWhitespace(
            'self.title is not null',
            $condition->generateExpression()->dispatch(
                self::$tableLocator->getStatementFactory()->getBuilder()
            )
        );
        $this::assertNotInstanceOf(ParametrizedCondition::class, $condition);
    }

    public function testCreateNotAll(): void
    {
        $condition = $this->builder->createNotAll('id', [3, 4]);

        $this::assertStringEqualsStringNormalizingWhitespace(
            'self.id <> all(:id::int4[])',
            $condition->generateExpression()->dispatch(
                self::$tableLocator->getStatementFactory()->getBuilder()
            )
        );
        $this::assertEquals(
            ['id' => [3, 4]],
            $condition->getParameterHolder()->getParameters()
        );

        $this::expectException(OutOfBoundsException::class);
        $this::expectExceptionMessage('does not exist');
        $this->builder->createNotAll('missing', ['baz', 'quux']);
    }

    public function testCreateOperatorCondition(): void
    {
        $condition = $this->builder->createOperatorCondition('title', '~*', 'gateway');

        $this::assertStringEqualsStringNormalizingWhitespace(
            'self.title ~* :title::"text"',
            $condition->generateExpression()->dispatch(
                self::$tableLocator->getStatementFactory()->getBuilder()
            )
        );
        $this::assertEquals(['title' => 'gateway'], $condition->getParameterHolder()->getParameters());

        $this::expectException(OutOfBoundsException::class);
        $this::expectExceptionMessage('does not exist');
        $this->builder->createOperatorCondition('missing', '!~*', 'gateway');
    }

    public function testCreateEqual(): void
    {
        $condition = $this->builder->createEqual('id', 5);

        $this::assertStringEqualsStringNormalizingWhitespace(
            'self.id = :id::int4',
            $condition->generateExpression()->dispatch(
                self::$tableLocator->getStatementFactory()->getBuilder()
            )
        );
        $this::assertEquals(['id' => 5], $condition->getParameterHolder()->getParameters());
    }

    public function testCreateSqlCondition(): void
    {
        $condition = $this->builder->createSqlCondition(
            "added between :cutoff and current_date",
            ['cutoff' => '2023-08-07']
        );

        $this::assertStringEqualsStringNormalizingWhitespace(
            "added between :cutoff and current_date",
            $condition->generateExpression()->dispatch(
                self::$tableLocator->getStatementFactory()->getBuilder()
            )
        );
        $this::assertEquals(['cutoff' => '2023-08-07'], $condition->getParameterHolder()->getParameters());
    }

    public function testCreatePrimaryKey(): void
    {
        $condition = $this->builder->createPrimaryKey(1);

        $this::assertStringEqualsStringNormalizingWhitespace(
            'self.id = :id::int4',
            $condition->generateExpression()->dispatch(
                self::$tableLocator->getStatementFactory()->getBuilder()
            )
        );
        $this::assertEquals(['id' => 1], $condition->getParameterHolder()->getParameters());
    }

    public function testAny(): void
    {
        $this::assertEquals(
            new FragmentList($this->builder->createAny('id', [1, 2])),
            $this->builder->any('id', [1, 2])->getFragment()
        );
    }

    public function testBoolColumn(): void
    {
        $this::assertEquals(
            new FragmentList($this->builder->createBoolColumn('flag')),
            $this->builder->boolColumn('flag')->getFragment()
        );
    }

    public function testNotBoolColumn(): void
    {
        $this::assertEquals(
            new FragmentList($this->builder->createNotBoolColumn('flag')),
            $this->builder->notBoolColumn('flag')->getFragment()
        );
    }

    public function testIsNull(): void
    {
        $this::assertEquals(
            new FragmentList($this->builder->createIsNull('title')),
            $this->builder->isNull('title')->getFragment()
        );
    }

    public function testIsNotNull(): void
    {
        $this::assertEquals(
            new FragmentList($this->builder->createIsNotNull('title')),
            $this->builder->isNotNull('title')->getFragment()
        );
    }

    public function testNotAll(): void
    {
        $this::assertEquals(
            new FragmentList($this->builder->createNotAll('id', [3, 4])),
            $this->builder->notAll('id', [3, 4])->getFragment()
        );
    }

    public function testOperatorCondition(): void
    {
        $this::assertEquals(
            new FragmentList($this->builder->createOperatorCondition('title', '~*', 'gateway')),
            $this->builder->operatorCondition('title', '~*', 'gateway')->getFragment()
        );
    }

    public function testEqual(): void
    {
        $this::assertEquals(
            new FragmentList($this->builder->createEqual('id', 5)),
            $this->builder->equal('id', 5)->getFragment()
        );
    }

    public function testSqlCondition(): void
    {
        $this::assertEquals(
            new FragmentList($this->builder->createSqlCondition(
                "added between :cutoff and current_date",
                ['cutoff' => '2023-08-07']
            )),
            $this->builder->sqlCondition(
                "added between :cutoff and current_date",
                ['cutoff' => '2023-08-07']
            )
                ->getFragment()
        );
    }

    public function testPrimaryKey(): void
    {
        $this::assertEquals(
            new FragmentList($this->builder->createPrimaryKey(1)),
            $this->builder->primaryKey(1)->getFragment()
        );
    }

    #[DataProvider('tableNameProvider')]
    public function testJoinUsingTableName(TableName|QualifiedName $name): void
    {
        $gateway = self::$tableLocator->createGateway('update_test');

        $select = $gateway->select(
            $this->builder->join($name)
                ->alias('custom')
        );

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select self.*, custom.* from public.update_test as self, public.update_test as custom',
            $select->createSelectStatement()->getSql()
        );
    }

    public function testJoinUsingSql(): void
    {
        $select = self::$tableLocator->createGateway('update_test')
            ->select(fn (FluentBuilder $builder): JoinBuilder => $builder
                ->join('select baz.* from foo.bar as baz order by baz.quux')
                    ->inline());

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select self.*, baz.* from public.update_test as self, foo.bar as baz order by baz.quux',
            $select->createSelectStatement()->getSql()
        );
    }

    public function testJoinsOnForeignKeyByDefault(): void
    {
        $documents = self::$tableLocator->createGateway('fkey_test.documents');
        $joined    = $documents->select();

        $possible  = self::$tableLocator->createBuilder('fkey_test.documents_tags')
            ->join($joined);
        $this::assertEquals(
            new JoinFragment($joined, new ForeignKeyCondition(
                $documents->getDefinition()
                    ->getReferences()
                    ->get(new TableName('fkey_test', 'documents_tags'))
            )),
            $possible->getOwnFragment()
        );

        $impossible = self::$tableLocator->createBuilder('public.employees')
            ->join($joined);
        $this::assertEquals(new JoinFragment($joined), $impossible->getOwnFragment());

        $unconditional = self::$tableLocator->createBuilder('fkey_test.documents_tags')
            ->join($joined)
            ->unconditional();
        $this::assertEquals(new JoinFragment($joined), $unconditional->getOwnFragment());
    }

    public static function tableNameProvider(): array
    {
        return [
            [new TableName('update_test')],
            [new QualifiedName('update_test')]
        ];
    }

    public function testJoinUsingGateway(): void
    {
        $gateway = self::$tableLocator->createGateway('update_test');

        $select = $gateway->select(
            $this->builder->join($gateway)
                ->alias('custom')
        );

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select self.*, custom.* from public.update_test as self, public.update_test as custom',
            $select->createSelectStatement()->getSql()
        );
    }

    public function testJoinUsingSelectProxy(): void
    {
        $gateway = self::$tableLocator->createGateway('update_test');

        $select = $gateway->select(
            $this->builder->join($gateway->select($this->builder->createBoolColumn('flag')))
                ->alias('custom')
        );

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select self.*, custom.* from public.update_test as self, public.update_test as custom where custom.flag',
            $select->createSelectStatement()->getSql()
        );
    }

    public function testOutputSubquery(): void
    {
        $gateway       = self::$tableLocator->createGateway('update_test');
        $unconditional = self::$tableLocator->createGateway('unconditional');
        $ucBuilder     = new FluentBuilder($unconditional->getDefinition(), self::$tableLocator);

        $select = $gateway->select(
            $this->builder->outputSubquery(
                $unconditional->select(
                    $ucBuilder->returningColumns()
                        ->only(['id'])
                )
            )
                ->alias('custom')
                ->columnAlias('klmn')
                ->joinOn($this->builder->createSqlCondition('self.title = joined.title'))
        );

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select self.*, ( select custom.id from public."unconditional" as custom where self.title = custom.title )'
            . ' as klmn from public.update_test as self',
            $select->createSelectStatement()->getSql()
        );
    }

    public function testOutputExpressionUsingString(): void
    {
        $gateway = self::$tableLocator->createGateway('update_test');
        $select  = $gateway->select(
            $this->builder->outputExpression('upper(self.title) as upper_title')
        );

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select self.*, upper(self.title) as upper_title from public.update_test as self',
            $select->createSelectStatement()->getSql()
        );
    }

    public function testOutputExpressionUsingCondition(): void
    {
        $gateway = self::$tableLocator->createGateway('update_test');
        $select  = $gateway->select(
            $this->builder->outputExpression($this->builder->createIsNull('title'), 'null_title')
        );

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select self.*, self.title is null as null_title from public.update_test as self',
            $select->createSelectStatement()->getSql()
        );
    }

    public function testWithClauseUsingSqlStrings(): void
    {
        $gateway = self::$tableLocator->createGateway('update_test');
        $select  = $gateway->select(
            $this->builder->withSqlString('foo as (select 1)', [], Fragment::PRIORITY_LOWER)
                ->withSqlString('bar as (select 2)', [], Fragment::PRIORITY_HIGHER)
        );

        $this::assertStringEqualsStringNormalizingWhitespace(
            'with bar as ( select 2 ), foo as ( select 1 ) select self.* from public.update_test as self',
            $select->createSelectStatement()->getSql()
        );
    }

    public function testWithClauseUsingSelectProxy(): void
    {
        $gateway = self::$tableLocator->createGateway('update_test');
        $select  = $gateway->select($this->builder->withSelect($gateway->select(), 'aaa')
            ->recursive()
            ->notMaterialized()
            ->columnAliases(['foo', 'bar']));

        $this::assertStringEqualsStringNormalizingWhitespace(
            'with recursive aaa (foo, bar) as not materialized ( select self.* from public.update_test as self ) '
            . 'select self.* from public.update_test as self',
            $select->createSelectStatement()->getSql()
        );
    }

    public function testOrderBy(): void
    {
        $select = self::$tableLocator->createGateway('update_test')
            ->select($this->builder->orderBy('added'));

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select self.* from public.update_test as self order by added',
            $select->createSelectStatement()->getSql()
        );

        $this::expectException(UnexpectedValueException::class);
        $this::expectExceptionMessage('column names or ordinal numbers');
        self::$tableLocator->createGateway('update_test')
            ->select($this->builder->orderBy('upper(title)'))
            ->createSelectStatement();
    }

    public function testOrderByUnsafe(): void
    {
        $select = self::$tableLocator->createGateway('update_test')
            ->select($this->builder->orderByUnsafe('upper(title)'));

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select self.* from public.update_test as self order by upper(title)',
            $select->createSelectStatement()->getSql()
        );
    }

    public function testLimit(): void
    {
        $this::assertEquals(
            new FragmentList(new LimitClauseFragment(5)),
            $this->builder->limit(5)->getFragment()
        );
    }

    public function testOffset(): void
    {
        $this::assertEquals(
            new FragmentList(new OffsetClauseFragment(5)),
            $this->builder->offset(5)->getFragment()
        );
    }

    public function testAddCustom(): void
    {
        $this->builder->add(new class ('custom') extends CustomFragment {
            public function applyTo(Statement $statement): void
            {
                /** @var Select $statement */
                $statement->group->replace(['foo', 'bar']);
            }
        });

        $select = self::$tableLocator->createGateway('update_test')
            ->select($this->builder);

        $this::assertNotNull($select->getKey());
        $this::assertStringEqualsStringNormalizingWhitespace(
            'select self.* from public.update_test as self group by foo, bar',
            $select->createSelectStatement()->getSql()
        );
    }

    public function testAddCustomWithParameters(): void
    {
        $this->builder->addWithParameters(
            new class ('custom-params') extends CustomFragment {
                public function applyTo(Statement $statement): void
                {
                    /** @var Select $statement */
                    $statement->order->replace('title');
                    $statement->limit = ':foo::integer';
                    $statement->limitWithTies = true;
                }
            },
            ['foo' => 'bar']
        );

        $select = self::$tableLocator->createGateway('update_test')
            ->select($this->builder);

        $this::assertNotNull($select->getKey());
        $this::assertEquals(['foo' => 'bar'], $select->getParameterHolder()->getParameters());
        $this::assertStringEqualsStringNormalizingWhitespace(
            'select self.* from public.update_test as self order by title '
            . 'fetch first ($1::pg_catalog.int4) rows with ties',
            $select->createSelectStatement()->getSql()
        );
    }
}
