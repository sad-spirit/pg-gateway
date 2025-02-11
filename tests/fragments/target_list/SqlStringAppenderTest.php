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

namespace sad_spirit\pg_gateway\tests\fragments\target_list;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_gateway\tests\NormalizeWhitespace;
use sad_spirit\pg_gateway\exceptions\LogicException;
use sad_spirit\pg_gateway\fragments\target_list\SqlStringAppender;
use sad_spirit\pg_builder\Select;
use sad_spirit\pg_builder\StatementFactory;

class SqlStringAppenderTest extends TestCase
{
    use NormalizeWhitespace;

    private static StatementFactory $factory;

    public static function setUpBeforeClass(): void
    {
        self::$factory = new StatementFactory();
    }

    public function testKeyDependsOnSql(): void
    {
        $fragmentOne   = new SqlStringAppender(self::$factory->getParser(), 'foo as bar');
        $fragmentTwo   = new SqlStringAppender(self::$factory->getParser(), 'foo as baz');
        $fragmentThree = new SqlStringAppender(self::$factory->getParser(), 'foo as baz');

        $this::assertNotNull($fragmentOne->getKey());
        $this::assertNotNull($fragmentTwo->getKey());
        $this::assertNotEquals($fragmentOne->getKey(), $fragmentTwo->getKey());
        $this::assertEquals($fragmentTwo->getKey(), $fragmentThree->getKey());
    }

    public function testKeyDependsOnAlias(): void
    {
        $fragmentOne   = new SqlStringAppender(self::$factory->getParser(), 'self.foo', 'bar');
        $fragmentTwo   = new SqlStringAppender(self::$factory->getParser(), 'self.foo', 'baz');
        $fragmentThree = new SqlStringAppender(self::$factory->getParser(), 'self.foo', 'baz');

        $this::assertNotEquals($fragmentOne->getKey(), $fragmentTwo->getKey());
        $this::assertEquals($fragmentTwo->getKey(), $fragmentThree->getKey());
    }

    public function testModifyTargetListWithoutSeparateAlias(): void
    {
        /** @var Select $select */
        $select = self::$factory->createFromString('select self.foo as bar, quux.xyzzy');
        $fragment = new SqlStringAppender(self::$factory->getParser(), 'other.foo as baz, foobar');

        $fragment->applyTo($select);

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select self.foo as bar, quux.xyzzy, other.foo as baz, foobar',
            self::$factory->createFromAST($select)->getSql()
        );
    }

    public function testModifyTargetListWithSeparateAlias(): void
    {
        /** @var Select $select */
        $select = self::$factory->createFromString('select self.foo as bar, quux.xyzzy');
        $fragment = new SqlStringAppender(self::$factory->getParser(), 'other.foo', 'baz');

        $fragment->applyTo($select);

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select self.foo as bar, quux.xyzzy, other.foo as baz',
            self::$factory->createFromAST($select)->getSql()
        );
    }

    public function testDisallowSeparateAliasForMultipleExpressions(): void
    {
        /** @var Select $select */
        $select = self::$factory->createFromString('select self.foo as bar, quux.xyzzy');
        $fragment = new SqlStringAppender(self::$factory->getParser(), 'foo, bar', 'baz');

        $this::expectException(LogicException::class);
        $this::expectExceptionMessage('multiple expressions');
        $fragment->applyTo($select);
    }
}
