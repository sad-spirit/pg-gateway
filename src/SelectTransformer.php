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

use sad_spirit\pg_wrapper\ResultSet;
use sad_spirit\pg_wrapper\Connection;
use sad_spirit\pg_builder\NativeStatement;
use sad_spirit\pg_builder\SelectCommon;

/**
 * A decorator for a SelectProxy replacing its generated Select statement with another one
 *
 * Fragments can only modify the child Nodes of an existing Statement, sometimes it is needed to return a new one.
 * A subclass of SelectTransformer may e.g.
 *  - Combine the given Select with another one using UNION, returning a new SetOpSelect object
 *  - Put the original Select into a CTE or a sub-query in FROM, returning the outer Select
 */
abstract class SelectTransformer implements SelectProxy
{
    protected TableLocator $tableLocator;
    protected SelectProxy $wrapped;
    private ?string $key;

    /**
     * Constructor, sets the Select being decorated and additional dependencies
     *
     * @param SelectProxy  $wrapped
     * @param TableLocator $tableLocator
     * @param string|null  $key          Passing null as the key will make the generated statement non-cacheable
     */
    public function __construct(SelectProxy $wrapped, TableLocator $tableLocator, ?string $key = null)
    {
        $this->wrapped      = $wrapped;
        $this->tableLocator = $tableLocator;
        $this->key          = $key;
    }

    public function getKey(): ?string
    {
        if (
            null === ($wrappedKey = $this->wrapped->getKey())
            || null === $this->key
        ) {
            return null;
        }
        return TableLocator::hash([
            $this->key,
            $wrappedKey
        ]);
    }

    public function getParameterHolder(): ParameterHolder
    {
        return $this->wrapped->getParameterHolder();
    }

    public function executeCount()
    {
        return $this->wrapped->executeCount();
    }

    public function getIterator(): ResultSet
    {
        $native = $this->createSelectStatement();
        return [] === $native->getParameterTypes()
            ? $this->wrapped->getConnection()->execute($native->getSql())
            : $native->executeParams($this->wrapped->getConnection(), $this->getParameterHolder()->getParameters());
    }

    public function createSelectStatement(): NativeStatement
    {
        if (
            null === ($wrappedKey = $this->wrapped->getKey())
            || null === $this->key
        ) {
            $cacheKey = null;
        } else {
            $cacheKey = \sprintf(
                '%s.%s.%s.%s',
                $this->getConnection()->getConnectionId(),
                TableGateway::STATEMENT_SELECT,
                TableLocator::hash([
                    $this->getName(),
                    $this->key
                ]),
                $wrappedKey
            );
        }

        return $this->tableLocator->createNativeStatementUsingCache(
            \Closure::fromCallable([$this, 'createSelectAST']),
            $cacheKey
        );
    }

    public function createSelectAST(): SelectCommon
    {
        return $this->transform($this->wrapped->createSelectAST());
    }

    public function getConnection(): Connection
    {
        return $this->wrapped->getConnection();
    }

    public function getName(): metadata\TableName
    {
        return $this->wrapped->getName();
    }

    public function getColumns(): metadata\Columns
    {
        return $this->wrapped->getColumns();
    }

    public function getPrimaryKey(): metadata\PrimaryKey
    {
        return $this->wrapped->getPrimaryKey();
    }

    public function getReferences(): metadata\References
    {
        return $this->wrapped->getReferences();
    }

    /**
     * Transforms the given statement returning a new one
     *
     * @param SelectCommon $original
     * @return SelectCommon
     */
    abstract protected function transform(SelectCommon $original): SelectCommon;
}
