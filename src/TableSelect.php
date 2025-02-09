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

use sad_spirit\pg_builder\{
    NativeStatement,
    Select,
    SelectCommon
};
use sad_spirit\pg_builder\nodes\{
    ColumnReference,
    Identifier,
    QualifiedName,
    TargetElement,
    expressions\FunctionExpression,
    lists\FunctionArgumentList,
    range\RelationReference
};
use sad_spirit\pg_wrapper\{
    Connection,
    Result
};

/**
 * Default implementation of SelectProxy that is returned by GenericTableGateway
 */
final readonly class TableSelect implements SelectProxy
{
    private FragmentList $fragments;
    /** @var \Closure(): SelectCommon */
    private \Closure $baseSelectAST;
    /** @var \Closure(): SelectCommon */
    private \Closure $baseCountAST;

    /**
     * Class constructor
     *
     * @param null|\Closure(): SelectCommon $baseSelectAST Overrides the base AST
     *      (corresponding to "SELECT self.* from tablename as self" in SQL) which is used when building
     *      the SELECT query. May be used to add some default calculated fields, default JOINs, etc.
     * @param null|\Closure(): SelectCommon $baseCountAST Overrides the base AST
     *      (corresponding to "SELECT count(self.*) from tablename as self" in SQL) which is used in executeCount()
     */
    public function __construct(
        private TableLocator $tableLocator,
        private TableGateway $gateway,
        FragmentList $fragments,
        ?\Closure $baseSelectAST = null,
        ?\Closure $baseCountAST = null
    ) {
        // FragmentList instance used here should be immutable
        $this->fragments     = clone $fragments;

        $this->baseSelectAST = $baseSelectAST ?? function (): Select {
            $from = new RelationReference($this->getDefinition()->getName()->createNode());
            $from->setAlias(new Identifier(TableGateway::ALIAS_SELF));
            return $this->tableLocator->getStatementFactory()->select(
                [new TargetElement(new ColumnReference(TableGateway::ALIAS_SELF, '*'))],
                [$from]
            );
        };

        $this->baseCountAST  = $baseCountAST ?? function (): Select {
            $from = new RelationReference($this->getDefinition()->getName()->createNode());
            $from->setAlias(new Identifier(TableGateway::ALIAS_SELF));

            return $this->tableLocator->getStatementFactory()->select(
                [
                    new TargetElement(new FunctionExpression(
                        new QualifiedName('count'),
                        new FunctionArgumentList([new ColumnReference(TableGateway::ALIAS_SELF, '*')])
                    ))
                ],
                [$from]
            );
        };
    }

    public function getConnection(): Connection
    {
        return $this->gateway->getConnection();
    }

    public function getDefinition(): TableDefinition
    {
        return $this->gateway->getDefinition();
    }

    public function getParameterHolder(): ParameterHolder
    {
        return $this->fragments->getParameterHolder();
    }

    public function getKey(): ?string
    {
        if (null === ($fragmentKey = $this->fragments->getKey())) {
            return null;
        }
        return TableLocator::hash([
            self::class,
            (string)(new \ReflectionFunction($this->baseSelectAST)),
            (string)(new \ReflectionFunction($this->baseCountAST)),
            $this->getConnection()->getConnectionId(),
            $this->getDefinition()->getName(),
            $fragmentKey
        ]);
    }

    public function createSelectAST(): SelectCommon
    {
        $select = ($this->baseSelectAST)();
        $this->fragments->applyTo($select);
        return $select;
    }

    public function createSelectStatement(): NativeStatement
    {
        return $this->tableLocator->createNativeStatementUsingCache(
            $this->createSelectAST(...),
            $this->generateStatementKey(StatementType::Select, $this->baseSelectAST, $this->fragments)
        );
    }

    public function createSelectCountStatement(): NativeStatement
    {
        $fragments = $this->fragments->filter(
            fn(Fragment $fragment): bool => !$fragment instanceof SelectFragment || $fragment->isUsedForCount()
        );
        return $this->tableLocator->createNativeStatementUsingCache(
            function () use ($fragments): SelectCommon {
                $select = ($this->baseCountAST)();
                $fragments->applyTo($select, true);
                return $select;
            },
            $this->generateStatementKey(StatementType::Count, $this->baseCountAST, $fragments)
        );
    }

    /**
     * Returns a cache key for the statement being generated
     */
    private function generateStatementKey(StatementType $type, \Closure $baseAST, FragmentList $fragments): ?string
    {
        if (null === $fragmentKey = $fragments->getKey()) {
            return null;
        }
        return \sprintf(
            '%s.%s.%s.%s',
            $this->getConnection()->getConnectionId(),
            $type->value,
            TableLocator::hash([
                $this->getDefinition()->getName(),
                (string)(new \ReflectionFunction($baseAST))
            ]),
            $fragmentKey
        );
    }

    public function getIterator(): Result
    {
        $native = $this->createSelectStatement();
        if ([] === $native->getParameterTypes()) {
            return $this->gateway->getConnection()->execute($native->getSql());
        } else {
            return $native->executeParams($this->gateway->getConnection(), $this->fragments->getParameters());
        }
    }

    public function executeCount(): int|string
    {
        $native = $this->createSelectCountStatement();
        if ([] === $namesHash = $native->getNamedParameterMap()) {
            $result = $this->gateway->getConnection()->execute($native->getSql());
        } else {
            /** @psalm-suppress MixedArrayTypeCoercion */
            $parameters = \array_filter(
                $this->fragments->getParameters(),
                fn($key): bool => isset($namesHash[$key]),
                \ARRAY_FILTER_USE_KEY
            );
            $result     = $native->executeParams($this->gateway->getConnection(), $parameters);
        }

        $result->setMode(\PGSQL_NUM);
        /** @psalm-suppress MixedReturnStatement */
        return $result[0][0];
    }

    /**
     * Executes the "SELECT [target list]" query with current fragments and returns the first row of the result
     */
    public function fetchFirst(): ?array
    {
        return $this->getIterator()->current();
    }
}
