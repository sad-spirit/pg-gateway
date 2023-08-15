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

namespace sad_spirit\pg_gateway;

use sad_spirit\pg_builder\Statement;

/**
 * Interface for fragments that add themselves to the given statement
 *
 * Classes implementing this interface serve as a sort of proxy for a part of Statement AST, they should usually
 * create the actual Nodes only when the <code>apply()</code> method is called.
 *
 * Fragments should either be immutable, receiving all their dependencies in constructor, or should return null
 * from <code>getKey()</code>. A {@see FragmentBuilder} may be used if the class requires complex configuration.
 */
interface Fragment extends KeyEquatable
{
    public const PRIORITY_DEFAULT = 0;
    public const PRIORITY_HIGH    = 1;
    public const PRIORITY_LOW     = -1;
    public const PRIORITY_HIGHER  = 10;
    public const PRIORITY_LOWER   = -10;
    public const PRIORITY_HIGHEST = 100;
    public const PRIORITY_LOWEST  = -100;

    /**
     * Applies the fragment to the given Statement
     *
     * @param Statement $statement
     */
    public function applyTo(Statement $statement): void;

    /**
     * Returns the fragment's priority
     *
     * Fragments with higher priority will be processed earlier, this may be relevant for CTEs, joins,
     * and parts of ORDER BY / GROUP BY clauses
     *
     * @return int
     */
    public function getPriority(): int;
}
