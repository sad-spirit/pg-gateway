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
    SelectProxy,
    TableLocator,
    fragments\WithClauseFragment
};
use sad_spirit\pg_builder\Statement;
use sad_spirit\pg_builder\nodes\{
    CommonTableExpression,
    Identifier,
    WithClause,
    lists\IdentifierList
};

/**
 * Adds a part of a WITH clause using a wrapped SelectProxy
 *
 * @since 0.2.0
 */
class SelectProxyFragment extends WithClauseFragment
{
    private SelectProxy $select;
    private Identifier $alias;
    private ?IdentifierList $columnAliases;
    private ?bool $materialized;
    private bool $recursive;

    public function __construct(
        SelectProxy $select,
        Identifier $alias,
        IdentifierList $columnAliases = null,
        ?bool $materialized = null,
        bool $recursive = false,
        int $priority = self::PRIORITY_DEFAULT
    ) {
        parent::__construct($priority);
        $this->select = $select;
        $this->alias = $alias;
        $this->columnAliases = $columnAliases;
        $this->materialized = $materialized;
        $this->recursive = $recursive;
    }

    protected function createWithClause(Statement $statement): WithClause
    {
        $ast = clone $this->select->createSelectAST();
        return new WithClause(
            [new CommonTableExpression($ast, $this->alias, $this->columnAliases, $this->materialized)],
            $this->recursive
        );
    }

    public function getKey(): ?string
    {
        $selectKey = $this->select->getKey();
        return null === $selectKey
            ? null
            : 'with.' . $selectKey . '.'
              . TableLocator::hash([$this->alias, $this->columnAliases, $this->materialized, $this->recursive]);
    }

    public function getParameterHolder(): ParameterHolder
    {
        return $this->select->getParameterHolder();
    }
}
