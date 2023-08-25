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
        $manipulatorOne   = new SqlStringAppender(self::$factory->getParser(), 'foo as bar');
        $manipulatorTwo   = new SqlStringAppender(self::$factory->getParser(), 'foo as baz');
        $manipulatorThree = new SqlStringAppender(self::$factory->getParser(), 'foo as baz');

        $this::assertNotNull($manipulatorOne->getKey());
        $this::assertNotNull($manipulatorTwo->getKey());
        $this::assertNotEquals($manipulatorOne->getKey(), $manipulatorTwo->getKey());
        $this::assertEquals($manipulatorTwo->getKey(), $manipulatorThree->getKey());
    }

    public function testKeyDependsOnAlias(): void
    {
        $manipulatorOne   = new SqlStringAppender(self::$factory->getParser(), 'self.foo', 'bar');
        $manipulatorTwo   = new SqlStringAppender(self::$factory->getParser(), 'self.foo', 'baz');
        $manipulatorThree = new SqlStringAppender(self::$factory->getParser(), 'self.foo', 'baz');

        $this::assertNotEquals($manipulatorOne->getKey(), $manipulatorTwo->getKey());
        $this::assertEquals($manipulatorTwo->getKey(), $manipulatorThree->getKey());
    }

    public function testModifyTargetListWithoutSeparateAlias(): void
    {
        /** @var Select $select */
        $select = self::$factory->createFromString('select self.foo as bar, quux.xyzzy');
        $manipulator = new SqlStringAppender(self::$factory->getParser(), 'other.foo as baz, foobar');

        $manipulator->modifyTargetList($select->list);

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select self.foo as bar, quux.xyzzy, other.foo as baz, foobar',
            self::$factory->createFromAST($select)->getSql()
        );
    }

    public function testModifyTargetListWithSeparateAlias(): void
    {
        /** @var Select $select */
        $select = self::$factory->createFromString('select self.foo as bar, quux.xyzzy');
        $manipulator = new SqlStringAppender(self::$factory->getParser(), 'other.foo', 'baz');

        $manipulator->modifyTargetList($select->list);

        $this::assertStringEqualsStringNormalizingWhitespace(
            'select self.foo as bar, quux.xyzzy, other.foo as baz',
            self::$factory->createFromAST($select)->getSql()
        );
    }

    public function testDisallowSeparateAliasForMultipleExpressions(): void
    {
        /** @var Select $select */
        $select = self::$factory->createFromString('select self.foo as bar, quux.xyzzy');
        $manipulator = new SqlStringAppender(self::$factory->getParser(), 'foo, bar', 'baz');

        $this::expectException(LogicException::class);
        $this::expectExceptionMessage('multiple expressions');
        $manipulator->modifyTargetList($select->list);
    }
}
