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

namespace sad_spirit\pg_gateway\tests;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_builder\StatementFactory;
use sad_spirit\pg_gateway\fragments\WhereClauseFragment;
use sad_spirit\pg_gateway\tests\assets\ConditionImplementation;

/**
 * Checks that the default applyTo() implementation actually clones the fragment
 */
class ConditionTest extends TestCase
{
    /**
     * @noinspection SqlNoDataSourceInspection, SqlResolve
     */
    public function testCanReuseCondition(): void
    {
        $factory    = new StatementFactory();
        $parser     = $factory->getParser();

        $condition  = new ConditionImplementation($parser->parseExpression('bar is null'));
        $update     = $factory->update('foo', 'one = 1');
        $delete     = $factory->delete('foo');
        $fragment   = new WhereClauseFragment($condition);

        $fragment->applyTo($delete);
        $fragment->applyTo($update);

        $this::assertEquals(
            $factory->createFromString('delete from foo where bar is null'),
            $delete
        );

        $this::assertEquals(
            $factory->createFromString('update foo set one = 1 where bar is null'),
            $update
        );
    }
}
