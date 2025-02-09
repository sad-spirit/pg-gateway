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

namespace sad_spirit\pg_gateway\fragments\with;

use sad_spirit\pg_gateway\{
    ParameterHolder,
    TableLocator,
    fragments\WithClauseFragment,
    holders\SimpleParameterHolder
};
use sad_spirit\pg_builder\{
    Parser,
    Statement,
    exceptions\SyntaxException,
    nodes\WithClause
};

/**
 * Adds [part of] a WITH clause represented by an SQL string
 *
 * @since 0.2.0
 */
class SqlStringFragment extends WithClauseFragment
{
    /**
     * Constructor
     *
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        private readonly Parser $parser,
        private readonly string $sql,
        private readonly array $parameters = [],
        int $priority = self::PRIORITY_DEFAULT
    ) {
        parent::__construct($priority);
    }

    protected function createWithClause(Statement $statement): WithClause
    {
        $parser = $statement->getParser() ?? $this->parser;
        if (\preg_match('/^\s*[wW][iI][tT][hH][\s"]/', $this->sql)) {
            return $parser->parseWithClause($this->sql);
        } else {
            try {
                return new WithClause([$parser->parseCommonTableExpression($this->sql)]);
            } catch (SyntaxException) {
                return $parser->parseWithClause($this->sql);
            }
        }
    }

    public function getKey(): ?string
    {
        return TableLocator::hash([self::class, $this->sql]);
    }

    public function getParameterHolder(): ParameterHolder
    {
        return new SimpleParameterHolder($this, $this->parameters);
    }
}
