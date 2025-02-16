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
    TableGateway,
    TableLocator,
    exceptions\InvalidArgumentException,
    fragments\TargetListFragment
};
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    Identifier,
    TargetElement,
    lists\TargetList,
};

/**
 * Changes the list of returned columns and / or adds aliases to them
 *
 * The "projection" and "rename" operations are combined here for convenience
 *  - By default SELECT returns all columns using "self.*", it is not possible to apply aliases in this form;
 *  - Other statements have an empty RETURNING clause, it isn't possible to apply aliases either.
 */
final class SelfColumnsList extends TargetListFragment
{
    /** @var string[] */
    private readonly array $columns;

    /**
     * Constructor
     *
     * @param string[] $columns
     */
    public function __construct(array $columns, private readonly ?ColumnAliasStrategy $strategy = null)
    {
        if ([] === $columns) {
            throw new InvalidArgumentException('$columns array should not be empty');
        }
        $this->columns = $columns;
    }

    protected function modifyTargetList(TargetList $targetList): void
    {
        (new SelfColumnsNone())->modifyTargetList($targetList);

        foreach ($this->columns as $columnName) {
            $targetList[] = new TargetElement(
                new ColumnReference(TableGateway::ALIAS_SELF, $columnName),
                $this->getColumnAlias($columnName)
            );
        }
    }

    private function getColumnAlias(string $columnName): ?Identifier
    {
        if (null === $alias = $this->strategy?->getAlias($columnName)) {
            return null;
        }
        return new Identifier($alias);
    }

    public function getKey(): ?string
    {
        $strategyKey = null === $this->strategy ? 'none' : $this->strategy->getKey();
        if (null === $strategyKey) {
            return null;
        }

        return TableLocator::hash(self::class)
            . '.' . TableLocator::hash($this->columns)
            . '.' . $strategyKey;
    }
}
