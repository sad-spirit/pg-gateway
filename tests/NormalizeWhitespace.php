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

/**
 * @psalm-require-extends TestCase
 */
trait NormalizeWhitespace
{
    public static function assertStringEqualsStringNormalizingWhitespace(
        string $expected,
        string $actual,
        string $message = ''
    ): void {
        self::assertEquals(
            self::normalizeWhitespace($expected),
            self::normalizeWhitespace($actual),
            $message
        );
    }

    protected static function normalizeWhitespace(string $string): string
    {
        return \implode(' ', \preg_split('/\s+/', $string, -1, \PREG_SPLIT_NO_EMPTY));
    }

    abstract public static function assertEquals($expected, $actual, string $message = ''): void;
}
