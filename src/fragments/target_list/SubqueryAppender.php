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

namespace sad_spirit\pg_gateway\fragments\target_list;

use sad_spirit\pg_gateway\{
    Condition,
    ParameterHolder,
    Parametrized,
    SelectBuilder,
    TableGateway,
    TableLocator,
    exceptions\UnexpectedValueException,
    fragments\TargetListFragment,
    holders\ParameterHolderFactory,
    walkers\ReplaceTableAliasWalker
};
use sad_spirit\pg_builder\enums\SubselectConstruct;
use sad_spirit\pg_builder\nodes\{
    Identifier,
    TargetElement,
    expressions\RowExpression,
    expressions\SubselectExpression,
    lists\TargetList
};

/**
 * Adds a scalar subquery created using SelectBuilder to the TargetList
 *
 * @link https://www.postgresql.org/docs/current/sql-expressions.html#SQL-SYNTAX-SCALAR-SUBQUERIES
 */
final class SubqueryAppender extends TargetListFragment implements Parametrized
{
    private ?string $tableAlias = null;

    public function __construct(
        private readonly SelectBuilder $select,
        private readonly ?Condition $joinCondition = null,
        private readonly ?string $explicitTableAlias = null,
        private readonly ?string $columnAlias = null,
        private readonly bool $returningRow = false,
        private readonly bool $asArray = false
    ) {
    }

    /**
     * Returns an alias for the table inside scalar subquery
     *
     * As we usually need to reference the outer table within subquery using its default "self" alias,
     * the default "self" alias for the table inside subquery should be changed to something
     */
    public function getTableAlias(): string
    {
        return $this->tableAlias ??= $this->explicitTableAlias ?? TableLocator::generateAlias();
    }

    protected function modifyTargetList(TargetList $targetList): void
    {
        $select = clone $this->select->createSelectAST();
        $alias  = $this->getTableAlias();
        $select->dispatch(new ReplaceTableAliasWalker(TableGateway::ALIAS_SELF, $alias));

        if (null !== $this->joinCondition) {
            if (!isset($select->where)) {
                throw new UnexpectedValueException(\sprintf(
                    "Join conditions require a Statement containing a WHERE clause, instance of %s given",
                    $select::class
                ));
            }
            $select->where->and($condition = $this->joinCondition->generateExpression());
            // Done after adding the condition, as it should have the parent node set
            $condition->dispatch(new ReplaceTableAliasWalker(TableGateway::ALIAS_JOINED, $alias));
        }

        if (false !== $this->returningRow) {
            if (!isset($select->list)) {
                throw new UnexpectedValueException(\sprintf(
                    "Statement containing a target list needed for ROW() wrapping, instance of %s given",
                    $select::class
                ));
            }
            $row = new RowExpression();
            /** @var TargetElement $item */
            foreach ($select->list as $item) {
                $row[] = clone $item->expression;
            }
            $select->list->replace([new TargetElement($row)]);
        }

        $targetList[] = new TargetElement(
            new SubselectExpression($select, $this->asArray ? SubselectConstruct::ARRAY : null),
            null === $this->columnAlias ? null : new Identifier($this->columnAlias)
        );
    }

    public function getKey(): ?string
    {
        $selectKey    = $this->select->getKey();
        $conditionKey = null === $this->joinCondition ? 'none' : $this->joinCondition->getKey();
        if (null === $selectKey || null === $conditionKey) {
            return null;
        }

        $key = 'returning.' . $selectKey . '.' . $conditionKey;
        if (null !== $this->explicitTableAlias) {
            $key .= '.' . TableLocator::hash(['table', $this->explicitTableAlias]);
        }
        if (null !== $this->columnAlias) {
            $key .= '.' . TableLocator::hash(['column', $this->columnAlias]);
        }
        return $key;
    }

    public function getParameterHolder(): ParameterHolder
    {
        return ParameterHolderFactory::create($this->select, $this->joinCondition);
    }
}
