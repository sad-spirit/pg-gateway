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

namespace sad_spirit\pg_gateway\tests\holders;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_gateway\{
    exceptions\InvalidArgumentException,
    exceptions\UnexpectedValueException,
    holders\RecursiveParameterHolder,
    holders\SimpleParameterHolder
};
use sad_spirit\pg_gateway\tests\assets\FragmentImplementation;
use sad_spirit\pg_builder\enums\ConstantName;
use sad_spirit\pg_builder\nodes\expressions\KeywordConstant;

class RecursiveParameterHolderTest extends TestCase
{
    public function testAtLeastOneHolderIsRequired(): void
    {
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessage('at least one ParameterHolder');
        new RecursiveParameterHolder();
    }

    public function testGetOwnerReturnsFirstOwner(): void
    {
        $first  = new FragmentImplementation(new KeywordConstant(ConstantName::FALSE), 'first');
        $second = new FragmentImplementation(new KeywordConstant(ConstantName::TRUE), 'second');

        $holderOne = new RecursiveParameterHolder(
            new SimpleParameterHolder($first, []),
            new SimpleParameterHolder($second, [])
        );
        $this::assertSame($first, $holderOne->getOwner());

        $holderTwo = new RecursiveParameterHolder(
            new SimpleParameterHolder($second, []),
            new SimpleParameterHolder($first, [])
        );
        $this::assertSame($second, $holderTwo->getOwner());
    }

    public function testFlatten(): void
    {
        $first = new SimpleParameterHolder(
            new FragmentImplementation(new KeywordConstant(ConstantName::FALSE), 'first'),
            []
        );
        $second = new SimpleParameterHolder(
            new FragmentImplementation(new KeywordConstant(ConstantName::TRUE), 'second'),
            []
        );

        $holder = new RecursiveParameterHolder(
            new RecursiveParameterHolder($first),
            new RecursiveParameterHolder(new RecursiveParameterHolder($second))
        );
        $this::assertEquals(new RecursiveParameterHolder($first, $second), $holder->flatten());
    }

    public function testGetParameters(): void
    {
        $first = new SimpleParameterHolder(
            new FragmentImplementation(new KeywordConstant(ConstantName::FALSE), 'first'),
            ['foo' => 'bar']
        );
        $second = new SimpleParameterHolder(
            new FragmentImplementation(new KeywordConstant(ConstantName::NULL), 'second'),
            ['param' => 'value']
        );
        $third = new SimpleParameterHolder(
            new FragmentImplementation(new KeywordConstant(ConstantName::TRUE), 'third'),
            ['foo' => 'bar']
        );

        $holder = new RecursiveParameterHolder($first, $second, $third);
        $this::assertEquals(['foo' => 'bar', 'param' => 'value'], $holder->getParameters());
    }

    public function testDisallowMultipleValuesForParameter(): void
    {
        $first = new SimpleParameterHolder(
            new FragmentImplementation(new KeywordConstant(ConstantName::FALSE), 'first'),
            ['foo' => 'bar']
        );
        $second = new SimpleParameterHolder(
            new FragmentImplementation(new KeywordConstant(ConstantName::TRUE), 'second'),
            ['foo' => 'baz']
        );
        $holder = new RecursiveParameterHolder($first, $second);

        $this::expectException(UnexpectedValueException::class);
        $this::expectExceptionMessage('Multiple values');
        $holder->getParameters();
    }
}
