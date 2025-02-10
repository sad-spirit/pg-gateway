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

namespace sad_spirit\pg_gateway\builders;

use sad_spirit\pg_gateway\{
    Fragment,
    FragmentBuilder,
    TableDefinition,
    exceptions\InvalidArgumentException,
    exceptions\LogicException,
    exceptions\OutOfBoundsException,
    exceptions\UnexpectedValueException
};
use sad_spirit\pg_gateway\fragments\target_list\{
    ColumnAliasStrategy,
    SelfColumnsList,
    SelfColumnsNone,
    SelfColumnsShorthand,
    alias_strategies\ClosureStrategy,
    alias_strategies\MapStrategy,
    alias_strategies\PregReplaceStrategy
};

/**
 * Builder for classes that change the list of returned columns and add aliases to those
 */
class ColumnsBuilder implements FragmentBuilder
{
    /** @var string[] */
    private array $columns = [];
    private bool $shorthand = true;
    private ?ColumnAliasStrategy $strategy = null;

    public function __construct(private readonly TableDefinition $definition)
    {
    }

    public function getFragment(): Fragment
    {
        if ([] !== $this->columns) {
            return new SelfColumnsList($this->columns, $this->strategy);
        } elseif ($this->shorthand) {
            return new SelfColumnsShorthand();
        } else {
            return new SelfColumnsNone();
        }
    }

    /**
     * The fragment being built will remove all "self" columns from the target list
     *
     * @return $this
     */
    public function none(): self
    {
        if (null !== $this->strategy) {
            throw new LogicException("Cannot remove all columns when using aliases");
        }
        $this->columns   = [];
        $this->shorthand = false;

        return $this;
    }

    /**
     * The fragment being built will add "self.*" shorthand to the target list
     *
     * This is a no-op for SELECT statements as they have "self.*" for a target list by default. Obviously
     * aliases are not possible here.
     *
     * @return $this
     */
    public function star(): self
    {
        if (null !== $this->strategy) {
            throw new LogicException("Cannot use 'self.*' shorthand with aliases");
        }
        $this->columns   = [];
        $this->shorthand = true;

        return $this;
    }

    /**
     * The fragment being built will add a list of all columns to the target list
     *
     * @return $this
     */
    public function all(): self
    {
        $this->columns = $this->definition->getColumns()->getNames();

        return $this;
    }

    /**
     * The fragment being built will add a list of columns from $onlyColumns to the target list
     *
     * @param string[] $onlyColumns List of columns to add, should not be empty and should contain only
     *                              the actual table column names (an exception will be thrown otherwise)
     * @return $this
     */
    public function only(array $onlyColumns): self
    {
        if ([] === $onlyColumns) {
            throw new InvalidArgumentException('$onlyColumns array should not be empty');
        }
        $filtered = \array_intersect($onlyColumns, $this->definition->getColumns()->getNames());
        if ([] !== ($unknown = \array_diff($onlyColumns, $filtered))) {
            throw new OutOfBoundsException(sprintf(
                "\$onlyColumns array should only contain column names; unknown value(s) '%s' found",
                \implode("', '", $unknown)
            ));
        }
        $this->columns = $filtered;

        return $this;
    }

    /**
     * The fragment being built will add a list of all columns except those in $exceptColumns to the target list
     *
     * @param string[] $exceptColumns List of columns to omit, should not be empty, should contain the subset of
     *                                actual table column names (an exception will be thrown otherwise)
     * @return $this
     */
    public function except(array $exceptColumns): self
    {
        if ([] === $exceptColumns) {
            throw new InvalidArgumentException('$exceptColumns array should not be empty');
        }
        $columnNames = $this->definition->getColumns()->getNames();
        $filtered    = \array_diff($columnNames, $exceptColumns);
        if ([] !== ($unknown = \array_diff($exceptColumns, $columnNames))) {
            throw new OutOfBoundsException(sprintf(
                "\$exceptColumns array should only contain column names; unknown value(s) '%s' found",
                implode("', '", $unknown)
            ));
        }
        if ([] === $filtered) {
            throw new InvalidArgumentException(
                'All columns omitted, $exceptColumns array should contain only a subset of table columns'
            );
        }
        $this->columns = $filtered;

        return $this;
    }

    /**
     * The fragment being built will add a list of columns forming table's primary key to the target list
     *
     * @return $this
     */
    public function primaryKey(): self
    {
        if ([] === ($columns = $this->definition->getPrimaryKey()->getNames())) {
            throw new UnexpectedValueException("No columns in table's primary key");
        }
        $this->columns = $columns;

        return $this;
    }

    /**
     * The fragment being built will use the explicitly provided strategy for adding aliases
     *
     * @return $this
     */
    public function alias(ColumnAliasStrategy $strategy): self
    {
        if ([] === $this->columns) {
            throw new LogicException("Can only use aliases with a column list specified");
        }
        $this->strategy = $strategy;

        return $this;
    }

    /**
     * The fragment being built will use preg_replace() on column names to create aliases
     *
     * @param non-empty-string|non-empty-string[] $pattern
     * @param string|string[]                     $replacement
     * @return $this
     */
    public function replace(string|array $pattern, string|array $replacement): self
    {
        return $this->alias(new PregReplaceStrategy($pattern, $replacement));
    }

    /**
     * The fragment being built will search for aliases in explicitly provided mapping 'column name' => 'alias'
     *
     * @param array<string, string> $columnMap
     * @return $this
     */
    public function map(array $columnMap): self
    {
        return $this->alias(new MapStrategy($columnMap));
    }

    /**
     * The fragment being built will get aliases by executing the provided callback with column names
     *
     * @param \Closure(string): (null|string|\Stringable) $closure
     * @return $this
     */
    public function apply(\Closure $closure, ?string $key = null): self
    {
        return $this->alias(new ClosureStrategy($closure, $key));
    }
}
