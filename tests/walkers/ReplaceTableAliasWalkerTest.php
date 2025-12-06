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
 * @noinspection SqlResolve
 * @noinspection SqlCheckUsingColumns
 */

declare(strict_types=1);

namespace sad_spirit\pg_gateway\tests\walkers;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_builder\StatementFactory;
use sad_spirit\pg_gateway\tests\NormalizeWhitespace;
use sad_spirit\pg_gateway\walkers\ReplaceTableAliasWalker;

class ReplaceTableAliasWalkerTest extends TestCase
{
    use NormalizeWhitespace;

    public function testReplacesTableAlias(): void
    {
        $factory = new StatementFactory();
        $select  = $factory->createFromString(
            <<<QRY
select foo.one, bar.two
from sometable as foo, bar
where foo.id = bar.id
QRY
        );
        $select->dispatch(new ReplaceTableAliasWalker('foo', 'xyzzy'));

        $this::assertStringEqualsStringNormalizingWhitespace(
            <<<QRY
select xyzzy.one, bar.two
from sometable as xyzzy, bar
where xyzzy.id = bar.id
QRY
            ,
            $factory->createFromAST($select)->getSql()
        );
    }

    public function testReplacesTableAliasAndColumnNames(): void
    {
        $factory = new StatementFactory();
        $select  = $factory->createFromString(
            <<<QRY
select foo.one, foo.two, bar.one, bar.two
from sometable as foo, bar
where foo.id = bar.id
QRY
        );
        $select->dispatch(new ReplaceTableAliasWalker('foo', 'xyzzy', ['one' => 'alias']));

        $this::assertStringEqualsStringNormalizingWhitespace(
            <<<QRY
select xyzzy.alias, xyzzy.two, bar.one, bar.two
from sometable as xyzzy, bar
where xyzzy.id = bar.id
QRY
            ,
            $factory->createFromAST($select)->getSql()
        );
    }

    public function testDoesNotReplaceQualifiedTableName(): void
    {
        $factory = new StatementFactory();
        $select  = $factory->createFromString(
            $original = <<<QRY
select foo.bar.baz
from foo.bar
where foo.bar.xyzzy is null
QRY
        );
        $select->dispatch(new ReplaceTableAliasWalker('bar', 'baz'));
        $this::assertStringEqualsStringNormalizingWhitespace($original, $factory->createFromAST($select)->getSql());
    }
}
