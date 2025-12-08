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

namespace sad_spirit\pg_gateway\fragments\with;

use sad_spirit\pg_gateway\{
    ParameterHolder,
    TableLocator,
    fragments\WithClauseFragment,
    holders\SimpleParameterHolder
};
use sad_spirit\pg_builder\{
    Keyword,
    Parser,
    Statement,
    nodes\WithClause
};

/**
 * Adds [part of] a WITH clause represented by an SQL string
 *
 * @since 0.2.0
 */
final class SqlStringFragment extends WithClauseFragment
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
        $stream = $parser->lexer->tokenize($this->sql);

        return Keyword::WITH === $stream->getKeyword()
            ? $parser->parseWithClause($stream)
            : new WithClause([$parser->parseCommonTableExpression($stream)]);
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
