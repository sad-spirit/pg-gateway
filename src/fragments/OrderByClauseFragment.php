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

namespace sad_spirit\pg_gateway\fragments;

use sad_spirit\pg_gateway\{
    SelectFragment,
    TableLocator,
    exceptions\InvalidArgumentException,
    exceptions\UnexpectedValueException
};
use sad_spirit\pg_builder\{
    Parser,
    SelectCommon,
    Statement,
    Values
};
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    OrderByElement,
    expressions\NumericConstant,
    lists\RowList
};

/**
 * Updates the `ORDER BY` clause of a `SELECT` statement
 */
final class OrderByClauseFragment implements SelectFragment
{
    use VariablePriority;

    /**
     * Class constructor
     *
     * @param Parser $parser
     * @param iterable<OrderByElement|string>|string $orderBy As accepted by `OrderByList`
     * @param bool $restricted Whether only column names and ordinal numbers are allowed in ORDER BY list
     * @param bool $merge Whether new sort expressions should be added to the existing list
     *                    via merge() rather than replace() it
     * @param int $priority
     */
    public function __construct(
        private readonly Parser $parser,
        private readonly string|iterable $orderBy,
        private readonly bool $restricted = true,
        private readonly bool $merge = false,
        int $priority = self::PRIORITY_DEFAULT
    ) {
        $this->setPriority($priority);
    }

    public function applyTo(Statement $statement, bool $isCount = false): void
    {
        if (!$statement instanceof SelectCommon) {
            throw new InvalidArgumentException(\sprintf(
                "OrderByClauseFragment instances can only be applied to SELECT statements, %s given",
                $statement::class
            ));
        }

        // As $orderBy may be a string or may contain strings, we need to pass it through Parser attached to a "donor"
        // ($statement itself is not guaranteed to have a Parser). Values is used here as it has
        // less unneeded properties than Select
        $donor = new Values(new RowList([]));
        $donor->setParser($statement->getParser() ?? $this->parser);
        $donor->order->replace($this->orderBy);

        // By default, only allow sorting by column names or ordinal numbers, not arbitrary expressions
        if ($this->restricted) {
            foreach ($donor->order as $item) {
                if (
                    !$item->expression instanceof ColumnReference
                    && !$item->expression instanceof NumericConstant
                ) {
                    throw new UnexpectedValueException(\sprintf(
                        "OrderByClauseFragment only allows sorting by column names or ordinal numbers"
                        . " in restricted mode, instance of %s found as expression in ORDER BY list",
                        $item->expression::class
                    ));
                }
            }
        }

        if ($this->merge) {
            $statement->order->merge(clone $donor->order);
        } else {
            $statement->order->replace(clone $donor->order);
        }
    }

    public function getKey(): ?string
    {
        return 'order-' . TableLocator::hash([$this->orderBy, $this->restricted, $this->merge]);
    }

    public function isUsedForCount(): bool
    {
        return false;
    }
}
