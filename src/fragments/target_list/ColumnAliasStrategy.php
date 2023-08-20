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

use sad_spirit\pg_gateway\KeyEquatable;

/**
 * Encapsulates the logic of assigning an alias to a table column in the query target list
 */
interface ColumnAliasStrategy extends KeyEquatable
{
    /**
     * Returns an alias for the given column
     *
     * @param string $column
     * @return string|null Null means that an alias will not be added
     */
    public function getAlias(string $column): ?string;
}
