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

use sad_spirit\pg_builder\nodes\QualifiedName;
use sad_spirit\pg_gateway\exceptions\InvalidArgumentException;

/**
 * Represents a qualified name of a database table (or maybe other relation)
 *
 * The name represented by this class always has two parts (schema and relation), while {@see QualifiedName}
 * may have from one to three. It also doesn't need to be cloned like QualifiedName.
 *
 * @since 0.2.0
 */
final class TableName
{
    private string $schema = 'public';
    private string $relation;

    /**
     * Constructor, requires at least relation name, will set schema to 'public' if not given
     *
     * @param string ...$nameParts
     * @noinspection PhpMissingBreakStatementInspection
     */
    public function __construct(string ...$nameParts)
    {
        switch (\count($nameParts)) {
            case 2:
                $this->schema = \array_shift($nameParts);
                // fall-through is intentional
            case 1:
                $this->relation = \array_shift($nameParts);
                break;

            case 0:
                throw new InvalidArgumentException(__CLASS__ . ' constructor expects at least one name part');
            default:
                throw new InvalidArgumentException("Too many parts in qualified name: " . \implode('.', $nameParts));
        }
    }

    /**
     * Creates an instance of TableName based on QualifiedName node
     *
     * $catalog property is ignored, missing $schema property will default to 'public'
     *
     * @param QualifiedName $qualifiedName
     * @return self
     */
    public static function createFromNode(QualifiedName $qualifiedName): self
    {
        if (null === $qualifiedName->schema) {
            return new self($qualifiedName->relation->value);
        } else {
            return new self($qualifiedName->schema->value, $qualifiedName->relation->value);
        }
    }

    /**
     * Returns the relation part of a qualified table name
     *
     * @return string
     */
    public function getRelation(): string
    {
        return $this->relation;
    }

    /**
     * Returns the schema part of a qualified table name
     *
     * @return string
     */
    public function getSchema(): string
    {
        return $this->schema;
    }

    /**
     * Checks whether two TableName instances reference the same table
     *
     * @param TableName $other
     * @return bool
     */
    public function equals(self $other): bool
    {
        return $this->getRelation() === $other->getRelation()
            && $this->getSchema() === $other->getSchema();
    }

    /**
     * Creates a QualifiedName node with current schema and relation name
     *
     * @return QualifiedName
     */
    public function createNode(): QualifiedName
    {
        return new QualifiedName($this->schema, $this->relation);
    }

    /**
     * Returns the string representation of table name, with double quotes added as needed
     *
     * @return string
     */
    public function __toString()
    {
        return $this->createNode()->__toString();
    }
}
