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

use sad_spirit\pg_gateway\exceptions\InvalidArgumentException;

/**
 * Implementation of methods defined in References using an array of ForeignKey instances
 *
 * @psalm-require-implements References
 * @since 0.3.0
 */
trait ArrayOfForeignKeys
{
    /** @var ForeignKey[] */
    private array $foreignKeys  = [];
    /** @var array<string, array<int, int>> */
    private array $referencing  = [];
    /** @var array<string, array<int, int>> */
    private array $referencedBy = [];

    /**
     * Adds an index from $foreignKeys property to the $referencing property
     */
    private function addReferencing(string $tableName, int $index): void
    {
        if (!isset($this->referencing[$tableName])) {
            $this->referencing[$tableName]   = [$index];
        } else {
            $this->referencing[$tableName][] = $index;
        }
    }

    /**
     * Adds an index from $foreignKeys property to the $referencedBy property
     */
    private function addReferencedBy(string $tableName, int $index): void
    {
        if (!isset($this->referencedBy[$tableName])) {
            $this->referencedBy[$tableName]   = [$index];
        } else {
            $this->referencedBy[$tableName][] = $index;
        }
    }

    public function get(TableName $relatedTable, array $keyColumns = []): ForeignKey
    {
        $relatedStr = (string)$relatedTable;
        $keys       = \array_merge(
            $this->getMatchingKeys($this->referencedBy, $relatedStr, $keyColumns),
            \array_filter(
                $this->getMatchingKeys($this->referencing, $relatedStr, $keyColumns),
                fn(ForeignKey $key): bool => !$key->isRecursive()
            )
        );

        if ([] === $keys) {
            throw new InvalidArgumentException(\sprintf(
                "No matching foreign keys for %s%s",
                $relatedStr,
                [] === $keyColumns ? '' : ' using (' . \implode(', ', $keyColumns) . ')'
            ));
        } elseif (1 < \count($keys)) {
            throw new InvalidArgumentException(\sprintf(
                "Several matching foreign keys for %s%s: %s",
                $relatedStr,
                [] === $keyColumns ? '' : ' using (' . \implode(', ', $keyColumns) . ')',
                \implode(', ', \array_map(fn(ForeignKey $key): string => $key->getConstraintName(), $keys))
            ));
        }

        return \reset($keys);
    }

    /**
     * {@inheritDoc}
     *
     * @return ForeignKey[]
     */
    public function to(TableName $referencedTable, array $keyColumns = []): array
    {
        return $this->getMatchingKeys($this->referencing, (string)$referencedTable, $keyColumns);
    }

    /**
     * {@inheritDoc}
     *
     * @return ForeignKey[]
     */
    public function from(TableName $childTable, array $keyColumns = []): array
    {
        return $this->getMatchingKeys($this->referencedBy, (string)$childTable, $keyColumns);
    }

    /**
     * Finds matching foreign keys using one of $referencing or $referencedBy arrays
     *
     * @param string[] $keyColumns
     * @return ForeignKey[]
     */
    private function getMatchingKeys(array $source, string $tableName, array $keyColumns = []): array
    {
        $result = [];
        foreach ($source[$tableName] ?? [] as $index) {
            $foreignKey = $this->foreignKeys[$index];
            if (
                [] === $keyColumns
                || $keyColumns === \array_intersect($keyColumns, $foreignKey->getChildColumns())
            ) {
                $result[] = $foreignKey;
            }
        }
        return $result;
    }

    /**
     * Method required by IteratorAggregate interface
     *
     * {@inheritDoc}
     * @return \ArrayIterator<int, ForeignKey>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->foreignKeys);
    }

    public function count(): int
    {
        return \count($this->foreignKeys);
    }
}
