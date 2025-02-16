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

namespace sad_spirit\pg_gateway\fragments\join_strategies;

use sad_spirit\pg_gateway\{
    TableGateway,
    exceptions\InvalidArgumentException,
    exceptions\LogicException,
    walkers\ReplaceTableAliasWalker
};
use sad_spirit\pg_builder\{
    Select,
    SelectCommon,
    Statement,
    enums\ConstantName,
    enums\JoinType
};
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    Identifier,
    ScalarExpression,
    TargetElement,
    expressions\KeywordConstant,
    range\Subselect
};

/**
 * Wraps the joined Select statement in a LATERAL subselect and adds that to the base Select
 *
 * The main difference with ExplicitJoinStrategy is that join condition is added to the WHERE clause of subselect
 * rather than the ON clause of JOIN
 *
 * While lateral subselects *can* appear in FROM clause of UPDATE statements and USING clause of DELETE statements,
 * these cannot reference the base table (the one being modified). Therefore, we only allow joining with
 * Select statements which can eventually be added to Delete or Update
 */
class LateralSubselectStrategy extends SelectOnlyJoinStrategy
{
    public function __construct(public readonly LateralSubselectJoinType $joinType = LateralSubselectJoinType::APPEND)
    {
    }

    public function join(
        Statement $statement,
        SelectCommon $joined,
        ?ScalarExpression $condition,
        string $alias,
        bool $isCount
    ): void {
        if (!$statement instanceof Select) {
            throw new InvalidArgumentException(\sprintf(
                "Lateral subselects can only be joined with Select statements, instance of %s given",
                $statement::class
            ));
        }

        $subSelect = $this->prepareSubselect($joined, $condition, $alias);

        if (LateralSubselectJoinType::APPEND === $this->joinType) {
            $statement->from[] = $subSelect;
        } else {
            $this->findNodeForJoin($statement->from, 'self')
                ->join($subSelect, JoinType::from($this->joinType->value))
                ->on = new KeywordConstant(ConstantName::TRUE);
        }

        if (!$isCount) {
            $statement->list[] = new TargetElement(new ColumnReference($this->getSubselectAlias(), '*'));
        }
    }

    private function prepareSubselect(SelectCommon $joined, ?ScalarExpression $condition, string $alias): Subselect
    {
        if (null !== $condition) {
            if (!$joined instanceof Select) {
                throw new LogicException("Conditions can only be applied to simple SELECT statements");
            }
            $condition->dispatch(new ReplaceTableAliasWalker(TableGateway::ALIAS_JOINED, $alias));
            $joined->where->and($condition);
        }

        $subSelect = new Subselect($joined);
        $subSelect->setTableAlias(new Identifier($this->getSubselectAlias()));
        $subSelect->setLateral(true);

        return $subSelect;
    }

    public function getKey(): ?string
    {
        return 'lateral-' . $this->joinType->value;
    }
}
