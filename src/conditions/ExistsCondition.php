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

namespace sad_spirit\pg_gateway\conditions;

use sad_spirit\pg_gateway\{
    Condition,
    ParameterHolder,
    Parametrized,
    SelectBuilder,
    TableGateway,
    TableLocator,
    exceptions\UnexpectedValueException,
    holders\ParameterHolderFactory,
    walkers\ReplaceTableAliasWalker
};
use sad_spirit\pg_builder\Select;
use sad_spirit\pg_builder\enums\SubselectConstruct;
use sad_spirit\pg_builder\nodes\{
    TargetElement,
    ScalarExpression,
    expressions\NumericConstant,
    expressions\SubselectExpression
};

/**
 * Generates the "EXISTS(SELECT ...)" condition using the given SelectProxy
 */
final class ExistsCondition extends Condition implements Parametrized
{
    private ?string $alias = null;

    public function __construct(
        private readonly SelectBuilder $builder,
        private readonly ?Condition $joinCondition = null,
        private readonly ?string $explicitAlias = null
    ) {
    }

    protected function generateExpressionImpl(): ScalarExpression
    {
        $select = clone $this->builder->createSelectAST();
        $alias  = $this->getAlias();
        $select->dispatch(new ReplaceTableAliasWalker(TableGateway::ALIAS_SELF, $alias));
        // https://www.postgresql.org/docs/current/functions-subquery.html#FUNCTIONS-SUBQUERY-EXISTS
        if ($select instanceof Select) {
            $select->list->replace([new TargetElement(new NumericConstant('1'))]);
        }

        if (null !== $this->joinCondition) {
            if (!isset($select->where)) {
                throw new UnexpectedValueException(\sprintf(
                    "Join conditions require a Statement containing a WHERE clause, instance of %s given",
                    $select::class
                ));
            }
            $condition = $this->joinCondition->generateExpression();
            $condition->dispatch(new ReplaceTableAliasWalker(TableGateway::ALIAS_JOINED, $alias));
            $select->where->and($condition);
        }

        return new SubselectExpression($select, SubselectConstruct::EXISTS);
    }

    /**
     * Returns the alias for the table in EXISTS()
     *
     * As we usually need to reference the main table inside EXISTS() by its "self" alias,
     * the "self" alias for the table inside EXISTS() should be changed to something
     */
    public function getAlias(): string
    {
        return $this->alias ??= $this->explicitAlias ?? TableLocator::generateAlias();
    }

    public function getKey(): ?string
    {
        $selectKey    = $this->builder->getKey();
        $conditionKey = null === $this->joinCondition ? 'none' : $this->joinCondition->getKey();
        $aliasKey     = null === $this->explicitAlias ? '' : '.' . TableLocator::hash($this->explicitAlias);

        return null === $selectKey || null === $conditionKey
            ? null
            : $selectKey . '.' . $conditionKey . $aliasKey;
    }

    public function getParameterHolder(): ParameterHolder
    {
        return ParameterHolderFactory::create($this->builder, $this->joinCondition);
    }
}
