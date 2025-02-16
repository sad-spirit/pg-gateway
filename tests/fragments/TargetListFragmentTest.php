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

/**
 * @noinspection SqlWithoutWhere
 * @noinspection SqlResolve
 */

declare(strict_types=1);

namespace sad_spirit\pg_gateway\tests\fragments;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use sad_spirit\pg_gateway\{
    exceptions\InvalidArgumentException,
    fragments\TargetListFragment,
    tests\NormalizeWhitespace
};
use sad_spirit\pg_builder\StatementFactory;
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    Identifier,
    TargetElement,
    lists\TargetList
};

/**
 * Test for a fragment adding stuff to output list of SELECT or to RETURNING clause of data-modifying statements
 */
class TargetListFragmentTest extends TestCase
{
    use NormalizeWhitespace;

    #[DataProvider('applicableStatementsProvider')]
    public function testAppliesToDmlStatements(string $sql): void
    {
        $factory   = new StatementFactory();
        $statement = $factory->createFromString($sql);

        $this->createFragment()->applyTo($statement);

        $this::assertStringContainsString(
            'returning foo as bar',
            $factory->createFromAST($statement)->getSql()
        );
    }
    public function testAppliesToSelect(): void
    {
        $factory  = new StatementFactory();
        $select   = $factory->createFromString('select one, two, three from a_table');

        $this->createFragment()->applyTo($select);

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select foo as bar from a_table',
            $factory->createFromAST($select)->getSql()
        );
    }

    #[DataProvider('nonApplicableStatementsProvider')]
    public function testDoesNotApplyToStatementsWithoutTargetList(string $sql): void
    {
        $factory   = new StatementFactory();
        $statement = $factory->createFromString($sql);

        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('can only be applied');
        $this->createFragment()->applyTo($statement);
    }

    public static function applicableStatementsProvider(): array
    {
        return [
            ['delete from a_table'],
            ['update a_table set foo = null'],
            ['insert into a_table default values']
        ];
    }

    public static function nonApplicableStatementsProvider(): array
    {
        return [
            ["select one from a_table union all select 'two'"],
            ["values ('one'), ('two')"]
        ];
    }

    private function createFragment(): TargetListFragment
    {
        return new class () extends TargetListFragment {
            protected function modifyTargetList(TargetList $targetList): void
            {
                $targetList->replace([new TargetElement(new ColumnReference('foo'), new Identifier('bar'))]);
            }

            public function getKey(): ?string
            {
                return null;
            }
        };
    }
}
