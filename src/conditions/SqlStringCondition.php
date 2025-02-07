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

namespace sad_spirit\pg_gateway\conditions;

use sad_spirit\pg_gateway\Condition;
use sad_spirit\pg_gateway\TableLocator;
use sad_spirit\pg_builder\{
    nodes\ScalarExpression,
    Parser
};

/**
 * Condition represented by an SQL string
 */
class SqlStringCondition extends Condition
{
    public function __construct(private readonly Parser $parser, private readonly string $sql)
    {
    }

    protected function generateExpressionImpl(): ScalarExpression
    {
        return $this->parser->parseExpression($this->sql);
    }

    public function getKey(): ?string
    {
        return TableLocator::hash([self::class, $this->sql]);
    }
}
