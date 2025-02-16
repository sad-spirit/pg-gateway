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

use sad_spirit\pg_builder\StatementFactory;
use sad_spirit\pg_gateway\Condition;
use sad_spirit\pg_gateway\fragments\WhereClauseFragment;
use sad_spirit\pg_gateway\conditions\OrCondition;
use sad_spirit\pg_gateway\tests\assets\ConditionImplementation;

class OrConditionTest extends LogicalConditionTestCase
{
    protected function getTestedClassName(): string
    {
        return OrCondition::class;
    }

    /**
     * @noinspection SqlNoDataSourceInspection, SqlResolve
     */
    public function testAddToStatementWithExistingWhereClause(): void
    {
        $factory = new StatementFactory();
        $parser  = $factory->getParser();
        $select  = $factory->createFromString(
            'select * from foo as f, bar as b where f.bar_id = b.id'
        );

        $conditionOne = new ConditionImplementation($parser->parseExpression('one = 1'));
        $conditionTwo = new ConditionImplementation($parser->parseExpression('two = 2'));
        (new WhereClauseFragment(Condition::or($conditionOne, $conditionTwo)))
            ->applyTo($select);

        $this::assertStringEqualsStringNormalizingWhitespace(
            <<<SQL
            select *
            from foo as f, bar as b
            where f.bar_id = b.id and (
                      one = 1 or
                      two = 2
                  )
            SQL
            ,
            $factory->createFromAST($select)->getSql()
        );
    }
}
