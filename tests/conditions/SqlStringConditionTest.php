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

namespace sad_spirit\pg_gateway\tests\conditions;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_builder\Lexer;
use sad_spirit\pg_builder\Parser;
use sad_spirit\pg_gateway\conditions\SqlStringCondition;

class SqlStringConditionTest extends TestCase
{
    public function testHashesStringForKey(): void
    {
        $parser = new Parser(new Lexer());
        $one    = new SqlStringCondition($parser, 'foo = bar');
        $two    = new SqlStringCondition($parser, 'foo = bar');
        $three  = new SqlStringCondition($parser, 'baz = quux');

        $this::assertNotNull($one->getKey());
        $this::assertStringNotContainsString('foo = bar', $one->getKey());
        $this::assertSame($one->getKey(), $two->getKey());
        $this::assertNotSame($one->getKey(), $three->getKey());
    }
}
