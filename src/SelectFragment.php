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

use sad_spirit\pg_builder\Statement;

/**
 * Interface for fragments specific for SELECT statements
 */
interface SelectFragment extends Fragment
{
    /**
     * {@inheritDoc}
     *
     * The second parameter is intended for the JOIN-type queries: while the join itself may be needed as it affects
     * the number of returned rows, adding fields from the joined table to the target list should be omitted
     *
     * @param bool $isCount   Whether a "SELECT COUNT(*)" query is being processed
     */
    public function applyTo(Statement $statement, bool $isCount = false): void;

    /**
     * Returns whether this fragment should be added to a "SELECT COUNT(*)" query
     *
     * If the fragment does not change the number of returned rows or if it doesn't make sense for "SELECT COUNT(*)"
     * query (e.g. ORDER, LIMIT, OFFSET), then it should be skipped
     */
    public function isUsedForCount(): bool;
}
