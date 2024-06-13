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

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_builder\nodes\QualifiedName;
use sad_spirit\pg_gateway\exceptions\InvalidArgumentException;
use sad_spirit\pg_gateway\metadata\TableName;

class TableNameTest extends TestCase
{
    public function testAtLeastOnePart(): void
    {
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('at least one name part');

        new TableName();
    }

    public function testNoMoreThanTwoParts(): void
    {
        $this::expectException(\InvalidArgumentException::class);
        $this::expectExceptionMessage('Too many parts');

        new TableName('foo', 'bar', 'baz');
    }

    public function testSchemaDefaultsToPublic(): void
    {
        $name = new TableName('foo');

        $this::assertEquals('foo', $name->getRelation());
        $this::assertEquals('public', $name->getSchema());
    }

    public function testCreateFromNode(): void
    {
        $one   = TableName::createFromNode(new QualifiedName('one'));
        $two   = TableName::createFromNode(new QualifiedName('one', 'two'));
        $three = TableName::createFromNode(new QualifiedName('one', 'two', 'three'));

        $this::assertEquals(['public', 'one'], [$one->getSchema(), $one->getRelation()]);
        $this::assertEquals(['one', 'two'], [$two->getSchema(), $two->getRelation()]);
        $this::assertEquals(['two', 'three'], [$three->getSchema(), $three->getRelation()]);
    }

    public function testCreateNode(): void
    {
        $name = new TableName('foo', 'bar');
        $one  = $name->createNode();
        $two  = $name->createNode();

        $this::assertEquals(['foo', 'bar'], [$one->schema->value, $one->relation->value]);
        $this::assertEquals($one, $two);
        $this::assertNotSame($one, $two);
    }

    public function testEquals(): void
    {
        $foobar  = new TableName('foo', 'bar');
        $foobaz  = new TableName('foo', 'baz');
        $quuxbar = new TableName('quux', 'bar');
        $foobar2 = new TableName('foo', 'bar');

        $this::assertTrue($foobar->equals($foobar2));
        $this::assertFalse($foobar->equals($foobaz));
        $this::assertFalse($foobar->equals($quuxbar));
    }

    public function testSerialize(): void
    {
        $foobar  = new TableName('foo', 'bar');
        $mangled = \unserialize(\serialize($foobar));

        $this::assertEquals((string)$foobar, (string)$mangled);
        $this::assertTrue($foobar->equals($mangled));
    }
}
