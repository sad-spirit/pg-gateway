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
final class TableName implements \Stringable
{
    private string $schema = 'public';
    private string $relation;
    private string $asString;

    /**
     * Constructor, requires at least relation name, will set schema to 'public' if not given
     *
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
                throw new InvalidArgumentException(self::class . ' constructor expects at least one name part');
            default:
                throw new InvalidArgumentException("Too many parts in qualified name: " . \implode('.', $nameParts));
        }
        $this->asString = $this->createNode()->__toString();
    }

    /**
     * Creates an instance of TableName based on QualifiedName node
     *
     * $catalog property is ignored, missing $schema property will default to 'public'
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
     */
    public function getRelation(): string
    {
        return $this->relation;
    }

    /**
     * Returns the schema part of a qualified table name
     */
    public function getSchema(): string
    {
        return $this->schema;
    }

    /**
     * Checks whether two TableName instances reference the same table
     */
    public function equals(self $other): bool
    {
        return $this->getRelation() === $other->getRelation()
            && $this->getSchema() === $other->getSchema();
    }

    /**
     * Creates a QualifiedName node with current schema and relation name
     */
    public function createNode(): QualifiedName
    {
        return new QualifiedName($this->schema, $this->relation);
    }

    /**
     * Returns the string representation of table name, with double quotes added as needed
     */
    public function __toString(): string
    {
        return $this->asString;
    }

    /**
     * Serialized representation is [schema, relation]
     */
    public function __serialize(): array
    {
        return [$this->schema, $this->relation];
    }

    /**
     * Sets properties from serialized representation [schema, relation]
     */
    public function __unserialize(array $data): void
    {
        [$this->schema, $this->relation] = $data;
        $this->asString = $this->createNode()->__toString();
    }
}
