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

use sad_spirit\pg_wrapper\Result;
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
    /**
     * Constructor, sets the Select being decorated and additional dependencies
     *
     * @param SelectProxy  $wrapped
     * @param TableLocator $tableLocator
     * @param string|null  $key          Passing null as the key will make the generated statement non-cacheable
     */
    public function __construct(
        protected readonly SelectProxy $wrapped,
        protected readonly TableLocator $tableLocator,
        private readonly ?string $key = null
    ) {
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

    public function getIterator(): Result
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
                    $this->getDefinition()->getName(),
                    $this->key
                ]),
                $wrappedKey
            );
        }

        return $this->tableLocator->createNativeStatementUsingCache(
            $this->createSelectAST(...),
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

    public function getDefinition(): TableDefinition
    {
        return $this->wrapped->getDefinition();
    }

    /**
     * Transforms the given statement returning a new one
     *
     * @param SelectCommon $original
     * @return SelectCommon
     */
    abstract protected function transform(SelectCommon $original): SelectCommon;
}
