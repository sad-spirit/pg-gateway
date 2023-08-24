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

namespace sad_spirit\pg_gateway\fragments\target_list;

use sad_spirit\pg_gateway\{
    Condition,
    ParameterHolder,
    Parametrized,
    SelectProxy,
    TableGateway,
    TableLocator,
    exceptions\UnexpectedValueException,
    fragments\TargetListManipulator,
    holders\ParameterHolderFactory,
    walkers\ReplaceTableAliasWalker
};
use sad_spirit\pg_builder\nodes\{
    Identifier,
    TargetElement,
    expressions\SubselectExpression,
    lists\TargetList
};

/**
 * Adds a scalar subquery created from SelectProxy to the TargetList
 *
 * https://www.postgresql.org/docs/current/sql-expressions.html#SQL-SYNTAX-SCALAR-SUBQUERIES
 */
class SubqueryAppender extends TargetListManipulator implements Parametrized
{
    private SelectProxy $select;
    private ?Condition $joinCondition;
    private ?string $explicitTableAlias;
    private ?string $columnAlias;
    private ?string $tableAlias = null;

    public function __construct(
        SelectProxy $select,
        ?Condition $joinCondition = null,
        ?string $explicitAlias = null,
        ?string $columnAlias = null
    ) {
        $this->select = $select;
        $this->joinCondition = $joinCondition;
        $this->explicitTableAlias = $explicitAlias;
        $this->columnAlias = $columnAlias;
    }

    /**
     * Returns an alias for the table inside scalar subquery
     *
     * As we usually need to reference the outer table within subquery using its default "self" alias,
     * the default "self" alias for the table inside subquery should be changed to something
     *
     * @return string
     */
    public function getTableAlias(): string
    {
        return $this->tableAlias ??= $this->explicitTableAlias ?? TableLocator::generateAlias();
    }

    public function modifyTargetList(TargetList $targetList): void
    {
        $select = clone $this->select->createSelectAST();
        $alias  = $this->getTableAlias();
        $select->dispatch(new ReplaceTableAliasWalker(TableGateway::ALIAS_SELF, $alias));

        if (null !== $this->joinCondition) {
            if (!isset($select->where)) {
                throw new UnexpectedValueException(\sprintf(
                    "Join conditions require a Statement containing a WHERE clause, instance of %s given",
                    \get_class($select)
                ));
            }
            $condition = $this->joinCondition->generateExpression();
            $condition->dispatch(new ReplaceTableAliasWalker(TableGateway::ALIAS_JOINED, $alias));
            $select->where->and($condition);
        }

        $targetList[] = new TargetElement(
            new SubselectExpression($select),
            null === $this->columnAlias ? null : new Identifier($this->columnAlias)
        );
    }

    public function getKey(): ?string
    {
        $selectKey      = $this->select->getKey();
        $conditionKey   = null === $this->joinCondition ? 'none' : $this->joinCondition->getKey();
        $tableAliasKey  = null === $this->explicitTableAlias
            ? ''
            : '.' . TableLocator::hash(['table', $this->explicitTableAlias]);
        $columnAliasKey = null === $this->columnAlias
            ? ''
            : '.' . TableLocator::hash(['column', $this->columnAlias]);

        return null === $selectKey || null === $conditionKey
            ? null
            : 'output.' . $selectKey . '.' . $conditionKey . $tableAliasKey . $columnAliasKey;
    }

    public function getParameterHolder(): ParameterHolder
    {
        return ParameterHolderFactory::create($this->select, $this->joinCondition);
    }
}
