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

namespace sad_spirit\pg_gateway\tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use sad_spirit\pg_builder\enums\ConstantName;
use sad_spirit\pg_builder\nodes\expressions\KeywordConstant;
use sad_spirit\pg_gateway\{
    FragmentList,
    exceptions\InvalidArgumentException,
    exceptions\UnexpectedValueException
};
use sad_spirit\pg_gateway\tests\assets\{
    FragmentImplementation,
    FragmentBuilderImplementation,
    ParametrizedFragmentImplementation
};

/**
 * Test for class aggregating Fragments
 */
class FragmentListTest extends TestCase
{
    public function testEmptyList(): void
    {
        $list = new FragmentList();

        $this::assertCount(0, $list);
        $this::assertEquals([], $list->getParameters());
        $this::assertEquals('empty', $list->getKey());
    }

    public function testNormalizeNull(): void
    {
        $list = FragmentList::normalize(null);

        $this::assertCount(0, $list);
    }

    public function testNormalizeSelf(): void
    {
        $fragment = new FragmentImplementation(new KeywordConstant(ConstantName::FALSE), 'self');
        $input    = new FragmentList($fragment);
        $list     = FragmentList::normalize($input);

        $this::assertNotSame($input, $list);
        $this::assertEquals($input->getKey(), $list->getKey());
    }

    public function testNormalizeFragment(): void
    {
        $fragment = new FragmentImplementation(new KeywordConstant(ConstantName::FALSE), 'single');
        $list     = FragmentList::normalize($fragment);

        $this::assertEquals(
            [$fragment],
            $list->getIterator()->getArrayCopy()
        );
    }

    public function testNormalizeFragmentBuilder(): void
    {
        $fragment = new FragmentImplementation(new KeywordConstant(ConstantName::FALSE), 'built');
        $list     = FragmentList::normalize(new FragmentBuilderImplementation($fragment));

        $this::assertEquals(
            [$fragment],
            $list->getIterator()->getArrayCopy()
        );
    }

    public function testNormalizeIterable(): void
    {
        $fragmentOne = new FragmentImplementation(new KeywordConstant(ConstantName::FALSE), 'one');
        $fragmentTwo = new FragmentImplementation(new KeywordConstant(ConstantName::TRUE), 'two');

        $list = FragmentList::normalize(new \ArrayIterator([
            $fragmentOne,
            new FragmentBuilderImplementation($fragmentTwo)
        ]));

        $this::assertEquals(
            [$fragmentOne, $fragmentTwo],
            $list->getIterator()->getArrayCopy()
        );
    }


    public function testFlattensAddedLists(): void
    {
        $fragmentOne = new FragmentImplementation(
            new KeywordConstant(ConstantName::FALSE),
            'one'
        );
        $fragmentTwo = new FragmentImplementation(
            new KeywordConstant(ConstantName::TRUE),
            'two'
        );
        $fragmentThree = new FragmentImplementation(
            new KeywordConstant(ConstantName::NULL),
            'three'
        );

        $list = new FragmentList($fragmentOne);
        $list->add(new FragmentList($fragmentTwo, $fragmentThree));

        $this::assertCount(3, $list);
        $this::assertEquals(
            [$fragmentOne, $fragmentTwo, $fragmentThree],
            $list->getIterator()->getArrayCopy()
        );
    }

    public function testAddFragmentWithDuplicateKey(): void
    {
        $fragmentOne = new FragmentImplementation(new KeywordConstant(ConstantName::FALSE), 'a key');
        $fragmentTwo = new ParametrizedFragmentImplementation(
            new KeywordConstant(ConstantName::FALSE),
            ['foo' => 'bar'],
            'a key'
        );

        $listOne = new FragmentList($fragmentOne, $fragmentTwo);
        $listTwo = new FragmentList($fragmentTwo, $fragmentOne);
        $this::assertEquals([$fragmentTwo], $listOne->getIterator()->getArrayCopy());
        $this::assertEquals([$fragmentTwo], $listTwo->getIterator()->getArrayCopy());
    }

    public function testAddFragmentWithDuplicateKeyAndExtraParameters(): void
    {
        $fragmentOne = new ParametrizedFragmentImplementation(
            new KeywordConstant(ConstantName::FALSE),
            ['param' => 'value'],
            'a key'
        );
        $fragmentTwo = new ParametrizedFragmentImplementation(
            new KeywordConstant(ConstantName::FALSE),
            ['foo' => 'bar'],
            'a key'
        );

        $list = new FragmentList($fragmentOne, $fragmentTwo);
        $this::assertEquals([$fragmentOne], $list->getIterator()->getArrayCopy());
        $this::assertEquals(['param' => 'value', 'foo' => 'bar'], $list->getParameters());
    }

    public function testAddFragmentWithDuplicateKeyAndConflictingParameters(): void
    {
        $fragmentOne = new ParametrizedFragmentImplementation(
            new KeywordConstant(ConstantName::FALSE),
            ['param' => 'value'],
            'a key'
        );
        $fragmentTwo = new ParametrizedFragmentImplementation(
            new KeywordConstant(ConstantName::FALSE),
            ['param' => 'another value'],
            'a key'
        );

        $this::expectException(UnexpectedValueException::class);
        $this::expectExceptionMessage('Multiple values');
        new FragmentList($fragmentOne, $fragmentTwo);
    }

    public function testNullKeyIfFragmentHasNullKey(): void
    {
        $fragmentOne = new FragmentImplementation(
            new KeywordConstant(ConstantName::FALSE),
            'one'
        );
        $fragmentNull = new FragmentImplementation(
            new KeywordConstant(ConstantName::TRUE),
            null
        );

        $list = new FragmentList($fragmentOne);
        $this::assertNotNull($list->getKey());

        $list->add($fragmentNull);
        $this::assertNull($list->getKey());
    }

    public function testFragmentsAreSorted(): void
    {
        $fragmentOne = new FragmentImplementation(
            new KeywordConstant(ConstantName::FALSE),
            'one',
            2
        );
        $fragmentTwo = new FragmentImplementation(
            new KeywordConstant(ConstantName::TRUE),
            'two',
            1
        );

        $listOne = new FragmentList($fragmentOne);
        $listTwo = new FragmentList($fragmentTwo);
        $this::assertNotEquals($listOne->getKey(), $listTwo->getKey());

        $listOne->add($fragmentTwo);
        $listTwo->add($fragmentOne);
        $this::assertEquals($listOne->getKey(), $listTwo->getKey());

        $this::assertEquals($listOne->getSortedFragments(), $listTwo->getSortedFragments());

        $fragmentNull = new FragmentImplementation(
            new KeywordConstant(ConstantName::FALSE),
            null,
            2
        );
        $listOne->add($fragmentNull);

        $this::assertEquals(
            [$fragmentOne, $fragmentNull, $fragmentTwo],
            $listOne->getSortedFragments()
        );
    }

    public function testMergeParameters(): void
    {
        $list = new FragmentList();

        $list->mergeParameters(['foo' => 'bar', 'baz' => 'xyzzy']);
        $list->mergeParameters(['foo' => 'bar', 'param' => 'value']);
        $this::assertEquals(['foo' => 'bar', 'baz' => 'xyzzy', 'param' => 'value'], $list->getParameters());

        $this::expectException(UnexpectedValueException::class);
        $this::expectExceptionMessage('Multiple values');
        $list->mergeParameters(['foo' => 'different value']);
    }

    public function testFilteredListInheritsParameters(): void
    {
        $list = new FragmentList();
        $list->mergeParameters(['foo' => 'bar', 'baz' => 'xyzzy']);

        $filtered = $list->filter(fn($fragment): true => true);
        $this::assertEquals(['foo' => 'bar', 'baz' => 'xyzzy'], $filtered->getParameters());
    }

    #[DataProvider('invalidFragmentsProvider')]
    public function testNormalizeFailure(mixed $fragments): void
    {
        $this::expectException(InvalidArgumentException::class);
        $this::expectExceptionMessageMatches('/Fragment or FragmentBuilder/');
        FragmentList::normalize($fragments);
    }

    public static function invalidFragmentsProvider(): array
    {
        return [
            ['a string'],
            [666],
            [new \stdClass()],
            [[new \stdClass()]],
            [[new FragmentImplementation(new KeywordConstant(ConstantName::TRUE), null), 'false']]
        ];
    }
}
