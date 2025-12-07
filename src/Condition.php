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

namespace sad_spirit\pg_gateway;

use sad_spirit\pg_builder\nodes\ScalarExpression;

/**
 * Wrapper for ScalarExpression Nodes that (presumably) return boolean values
 *
 * Conditions are used by fragments that modify `WHERE` and `HAVING` clauses and by the
 * {@see \sad_spirit\pg_gateway\fragments\JoinFragment JoinFragment} for an actual `JOIN` condition
 *
 * Conditions behave like Specifications from the
 * {@link https://en.wikipedia.org/wiki/Specification_pattern pattern of the same name} and can be combined via
 * `AND` / `OR` operators. They do not implement `isSatisfiedBy()` method, though, for more or less obvious reasons.
 */
abstract class Condition implements KeyEquatable, FragmentBuilder
{
    /**
     * Returns the built fragment
     *
     * Implementing the `FragmentBuilder` interface allows directly using the `Condition` in a list of Fragments
     * passed to a `Gateway` query method. This returns a `WhereClauseFragment `wrapping around the `Condition`,
     * so it will eventually be appended to the `WHERE` clause of the query.
     */
    public function getFragment(): fragments\WhereClauseFragment
    {
        return new fragments\WhereClauseFragment($this);
    }

    /**
     * Wrapper method for generateExpression() that clones its return value
     *
     * The same `ScalarExpression` instance should not be returned on consecutive calls: it is a feature of the `Node`
     * to keep track of its parent, so it will be removed from one parent if added to the other.
     */
    final public function generateExpression(): ScalarExpression
    {
        return clone $this->generateExpressionImpl();
    }

    /**
     * Generates the expression that will be added to the Statement
     *
     * Method name starts with "generate" as a hint: it should preferably generate the `ScalarExpression` on "as needed"
     * basis rather than pre-generate and store that. Real world Conditions will use
     * {@see \sad_spirit\pg_builder\Parser Parser} and parsing may be slow.
     */
    abstract protected function generateExpressionImpl(): ScalarExpression;

    /**
     * Creates a Condition that combines several other Conditions using AND operator
     */
    final public static function and(self ...$children): conditions\AndCondition
    {
        return new conditions\AndCondition(...$children);
    }

    /**
     * Creates a Condition that combines several other Conditions using OR operator
     */
    final public static function or(self ...$children): conditions\OrCondition
    {
        return new conditions\OrCondition(...$children);
    }

    /**
     * Creates a negated Condition
     */
    final public static function not(self $child): conditions\NotCondition
    {
        return new conditions\NotCondition($child);
    }
}
