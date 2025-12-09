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

use sad_spirit\pg_builder\Parser;
use sad_spirit\pg_builder\SelectCommon;

/**
 * Creates the SELECT statement from the given SQL string
 *
 * @since 0.4.0
 */
final readonly class SqlStringSelectBuilder implements SelectBuilder
{
    public function __construct(private Parser $parser, private string $sql)
    {
    }

    public function createSelectAST(): SelectCommon
    {
        return $this->parser->parseSelectStatement($this->sql);
    }

    public function getKey(): ?string
    {
        return TableLocator::hash([self::class, $this->sql]);
    }
}
