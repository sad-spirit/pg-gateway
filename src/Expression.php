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

namespace sad_spirit\pg_gateway;

/**
 * Wrapper for expressions used as column values in INSERT and UPDATE statements
 *
 * Passing
 * <code>['foo' => 'default']</code>
 * to insert() or update() means: set the value of column 'foo' to a string 'default', while
 * <code>['foo' => new Expression('default')]</code>
 * means: set foo to its default value.
 */
final readonly class Expression implements \Stringable
{
    public function __construct(private string $expression)
    {
    }

    /**
     * Returns the expression.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->expression;
    }
}
