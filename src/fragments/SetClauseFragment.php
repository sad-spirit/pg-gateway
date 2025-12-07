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

namespace sad_spirit\pg_gateway\fragments;

use sad_spirit\pg_builder\{
    Insert,
    Node,
    Statement,
    Update,
    Values
};
use sad_spirit\pg_builder\nodes\{
    ScalarExpression,
    SetTargetElement,
    SetToDefault,
    SingleSetClause,
    expressions\NamedParameter,
    expressions\RowExpression,
    expressions\TypecastExpression,
    lists\RowList
};
use sad_spirit\pg_gateway\{
    Expression,
    Fragment,
    Parametrized,
    TableLocator,
    exceptions\InvalidArgumentException,
    exceptions\OutOfBoundsException,
    holders\SimpleParameterHolder,
    metadata\Column,
    metadata\Columns
};

/**
 * Fragment populating either the SET clause of an UPDATE statement or columns and VALUES clause of an INSERT
 *
 * NB: passing empty array to the constructor is disallowed: it may be technically legit for an `INSERT`, but simply
 * not adding this fragment is easier.
 */
class SetClauseFragment implements Fragment, Parametrized
{
    /**
     * Mapping "column name" -> "new column value"
     *
     * The values are processed as follows:
     *  - `null` will trigger generation of `field = :field::type` clause;
     *  - Node implementing `ScalarExpression` (or instance of `SetToDefault`) is directly inserted into SQL:
     *    `field = expression`;
     *  - Value of `Expression` instance is fed to Parser and the resultant Node is processed as above.
     *
     * @var array<string, null|Expression|ScalarExpression|SetToDefault>
     */
    private array $columns = [];

    /**
     * Mapping "column name" => "parameter value"
     *
     * If `$set` array passed to constructor contains actual column values (i.e. anything different from Expressions),
     * those end up here.
     *
     * @var array<string, mixed>
     */
    private array $parameters = [];

    /**
     * Mapping "column name" => "column type OID", from Columns::getAll()
     * @var array<string, int|numeric-string>
     */
    private array $types;

    public function __construct(Columns $columns, private readonly TableLocator $tableLocator, array $set)
    {
        if ([] === $set) {
            throw new InvalidArgumentException('At least one column should be specified, empty $set array given');
        }

        $seen = [];
        foreach ($columns->getNames() as $name) {
            if (\array_key_exists($name, $set)) {
                $seen[] = $name;
                if (
                    $set[$name] instanceof Expression
                    || $set[$name] instanceof ScalarExpression
                    || $set[$name] instanceof SetToDefault
                ) {
                    $this->columns[$name] = $set[$name];
                } else {
                    $this->columns[$name]    = null;
                    $this->parameters[$name] = $set[$name];
                }
            }
        }

        if ([] !== ($extra = \array_diff(\array_keys($set), $seen))) {
            throw new OutOfBoundsException(\sprintf(
                "Keys in \$set array should correspond to actual table columns; unknown key(s) '%s' given",
                \implode("', '", $extra)
            ));
        }

        $this->types = \array_map(fn (Column $column) => $column->typeOID, $columns->getAll());
    }

    public function getParameterHolder(): SimpleParameterHolder
    {
        return new SimpleParameterHolder($this, $this->parameters);
    }

    public function applyTo(Statement $statement): void
    {
        if ($statement instanceof Update) {
            $this->applyToUpdate($statement);
        } elseif ($statement instanceof Insert) {
            $this->applyToInsert($statement);
        } else {
            throw new InvalidArgumentException(\sprintf(
                "This fragment can only be applied to INSERT or UPDATE statements, instance of %s given",
                $statement::class
            ));
        }
    }

    /**
     * Adds the fragment as the SET clause of the given UPDATE statement
     */
    private function applyToUpdate(Update $update): void
    {
        $list = [];
        foreach ($this->columns as $name => $value) {
            $list[] = new SingleSetClause(new SetTargetElement($name), $this->createValueNode($value, $name));
        }

        $update->set->replace($list);
    }

    /**
     * Adds the fragment as the columns and VALUES clause of the given INSERT statement
     */
    private function applyToInsert(Insert $insert): void
    {
        $valuesRow = new RowExpression();
        foreach ($this->columns as $name => $value) {
            $insert->cols[] = new SetTargetElement($name);
            $valuesRow[]    = $this->createValueNode($value, $name);
        }

        $insert->values = new Values(new RowList([$valuesRow]));
    }

    /**
     * Creates a Node that will be used for a column's value
     */
    private function createValueNode(
        Expression|ScalarExpression|SetToDefault|null $value,
        string $name
    ): ScalarExpression|SetToDefault {
        if ($value instanceof ScalarExpression || $value instanceof SetToDefault) {
            return clone $value;
        } elseif ($value instanceof Expression) {
            return $this->tableLocator->getParser()->parseExpressionWithDefault((string)$value);
        } else {
            return new TypecastExpression(
                new NamedParameter($name),
                $this->tableLocator->createTypeNameNodeForOID($this->types[$name])
            );
        }
    }

    public function getKey(): ?string
    {
        return TableLocator::hash($this->columns);
    }

    public function getPriority(): int
    {
        // Should be applied early
        return Fragment::PRIORITY_HIGHEST;
    }
}
