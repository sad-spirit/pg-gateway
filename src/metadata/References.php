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

namespace sad_spirit\pg_gateway\metadata;

/**
 * Contains data about FOREIGN KEY constraints added to the table and those referencing it
 *
 * NB: For a recursive FOREIGN KEY (e.g. on a table storing a tree-like structure) a single record will be kept
 *
 * @extends \IteratorAggregate<int, ForeignKey>
 */
interface References extends \IteratorAggregate, \Countable
{
    /**
     * Returns a ForeignKey object matching the given related table and constraint columns
     *
     * While $relatedTable should almost always be the "other" member of the foreign key constraint
     * (unless you are requesting a recursive foreign key), $keyColumns always represent the columns
     * of the child table, where the constraint is defined.
     *
     * Consider the following schema
     * <code>
     * create table documents (
     *     ...
     *     employee_id integer references employees (id),
     *     boss_id integer references employees (id),
     *     ...
     * );
     * </code>
     * Here specifying the constraint column "employee_id" or "boss_id" uniquely identifies the constraint,
     * while specifying referenced "id" column is useless, it will most likely be the primary key.
     *
     * @param TableName $relatedTable
     * @param string[] $keyColumns
     * @return ForeignKey
     */
    public function get(TableName $relatedTable, array $keyColumns = []): ForeignKey;

    /**
     * Returns foreign keys on the current table referencing the given one and containing the given columns
     *
     * @param TableName $referencedTable Target table of the FOREIGN KEY constraint
     * @param string[] $keyColumns If empty, all keys to the referenced table will be returned
     * @return ForeignKey[]
     */
    public function to(TableName $referencedTable, array $keyColumns = []): array;

    /**
     * Returns foreign keys defined on the given table referencing the current one and containing the given columns
     *
     * @param TableName $childTable The table where FOREIGN KEY constraint is defined
     * @param string[] $keyColumns If empty, all foreign keys defined on the table will be returned
     * @return ForeignKey[]
     */
    public function from(TableName $childTable, array $keyColumns = []): array;
}
