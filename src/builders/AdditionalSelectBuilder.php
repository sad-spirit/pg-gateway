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
    FragmentBuilder,
    SelectBuilder,
    TableAccessor,
    TableDefinition,
    conditions\ForeignKeyCondition,
    exceptions\LogicException
};

/**
 * Base class for builders that create Fragments based on an additional SELECT statement
 */
abstract class AdditionalSelectBuilder implements FragmentBuilder
{
    protected ?string $alias = null;

    public function __construct(protected TableDefinition $base, protected SelectBuilder $additional)
    {
    }

    /**
     * Creates the join condition based on a FOREIGN KEY constraint between the base and the additional tables
     *
     * @param string[] $keyColumns If there are several FOREIGN KEY constraints between the tables,
     *                             specify the columns on the child side that should be part of the key
     * @param bool|null $fromChild If a self-join is being made, this specifies whether the base table should be
     *                             on the child side of the join or the parent one. Ignored otherwise.
     * @return ForeignKeyCondition
     */
    protected function createForeignKeyCondition(array $keyColumns = [], ?bool $fromChild = null): ForeignKeyCondition
    {
        if (!$this->additional instanceof TableAccessor) {
            throw new LogicException(\sprintf(
                "Cannot create a foreign key condition: an instance of %s does not contain table metadata",
                $this->additional::class
            ));
        }
        $foreignKey = $this->base->getReferences()
            ->get($this->additional->getDefinition()->getName(), $keyColumns);
        if (!$foreignKey->isRecursive()) {
            $fromChild = true;
            foreach ($this->base->getReferences()->from($this->additional->getDefinition()->getName()) as $to) {
                if ($to === $foreignKey) {
                    $fromChild = false;
                    break;
                }
            }
        } elseif (null === $fromChild) {
            throw new LogicException(\sprintf(
                "Cannot join on recursive foreign key without specifying whether to join"
                . " from child side (%s) or parent side (%s) of the key",
                "'" . \implode("', '", $foreignKey->getChildColumns()) . "'",
                "'" . \implode("', '", $foreignKey->getReferencedColumns()) . "'"
            ));
        }

        return new ForeignKeyCondition($foreignKey, $fromChild);
    }

    /**
     * Sets the explicit alias for the added table
     *
     * If not given, a generated alias will be used
     *
     * @return $this
     */
    public function alias(string $alias): self
    {
        $this->alias = $alias;

        return $this;
    }
}
