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

use sad_spirit\pg_gateway\exceptions\InvalidArgumentException;
use sad_spirit\pg_gateway\TableGateway;
use sad_spirit\pg_gateway\TableLocator;
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
final class SelfColumnsList extends SelfColumnsNone
{
    /** @var string[] */
    private array $columns;
    private ?ColumnAliasStrategy $strategy;

    public function __construct(array $columns, ColumnAliasStrategy $strategy = null)
    {
        if ([] === $columns) {
            throw new InvalidArgumentException('$columns array should not be empty');
        }
        $this->columns  = $columns;
        $this->strategy = $strategy;
    }

    public function modifyTargetList(TargetList $targetList): void
    {
        parent::modifyTargetList($targetList);

        foreach ($this->columns as $columnName) {
            $targetList[] = new TargetElement(
                new ColumnReference(TableGateway::ALIAS_SELF, $columnName),
                $this->getColumnAlias($columnName)
            );
        }
    }

    private function getColumnAlias(string $columnName): ?Identifier
    {
        if (
            null === $this->strategy
            || (null === ($alias = $this->strategy->getAlias($columnName)))
        ) {
            return null;
        }
        return new Identifier($alias);
    }

    public function getKey(): ?string
    {
        if (null === $this->strategy) {
            $strategyKey = 'none';
        } elseif (null === ($strategyKey = $this->strategy->getKey())) {
            return null;
        }

        return parent::getKey()
            . '.' . TableLocator::hash($this->columns)
            . '.' . $strategyKey;
    }
}
