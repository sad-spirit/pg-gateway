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

namespace sad_spirit\pg_gateway\conditions;

use sad_spirit\pg_gateway\{
    Condition,
    ParameterHolder,
    Parametrized,
    exceptions\InvalidArgumentException,
    holders\ParameterHolderFactory
};

/**
 * Base class for Conditions combining several other Conditions using logical operators
 */
abstract class LogicalCondition extends Condition implements Parametrized
{
    /** @var Condition[] */
    protected array $children = [];

    public function __construct(Condition ...$children)
    {
        if ([] === $children) {
            throw new InvalidArgumentException(sprintf(
                '%s: at least one child Condition is required',
                \get_class($this)
            ));
        }

        $this->children = $children;
    }

    /**
     * Returns the array of child Conditions' keys
     *
     * Note that the array is sorted alphabetically. We do not care in what order Conditions are added:
     * https://www.postgresql.org/docs/current/sql-expressions.html#SYNTAX-EXPRESS-EVAL
     * Sorting the keys allows us to have the same key for the same set of Conditions.
     *
     * @return array|null Returns null if any child Condition returns null from {@see getKey()}
     */
    protected function getChildKeys(): ?array
    {
        $keys = [];
        foreach ($this->children as $child) {
            if (null === ($key = $child->getKey())) {
                return null;
            }
            $keys[] = $key;
        }
        \sort($keys, SORT_STRING);
        return $keys;
    }

    public function getParameterHolder(): ?ParameterHolder
    {
        return ParameterHolderFactory::create(...$this->children);
    }
}
