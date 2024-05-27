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
final class TableSelect implements SelectProxy
{
    private TableLocator $tableLocator;
    private TableGateway $gateway;
    private FragmentList $fragments;
    /** @var \Closure(): SelectCommon */
    private \Closure $baseSelectAST;
    /** @var \Closure(): SelectCommon */
    private \Closure $baseCountAST;

    /**
     * Class constructor
     *
     * @param TableLocator $tableLocator
     * @param TableGateway $gateway
     * @param null|\Closure|Fragment|FragmentBuilder|iterable<Fragment|FragmentBuilder> $fragments
     * @param array<string, mixed> $parameters
     * @param \Closure(): SelectCommon|null $baseSelectAST Overrides the base AST
     *      (corresponding to "SELECT self.* from tablename as self" in SQL) which is used when building
     *      the SELECT query. May be used to add some default calculated fields, default JOINs, etc.
     * @param \Closure(): SelectCommon|null $baseCountAST Overrides the base AST
     *      (corresponding to "SELECT count(self.*) from tablename as self" in SQL) which is used in executeCount()
     */
    public function __construct(
        TableLocator $tableLocator,
        TableGateway $gateway,
        $fragments = null,
        array $parameters = [],
        \Closure $baseSelectAST = null,
        \Closure $baseCountAST = null
    ) {
        $this->tableLocator  = $tableLocator;
        $this->gateway       = $gateway;
        $this->fragments     = FragmentList::normalize($fragments)
            ->mergeParameters($parameters);

        $this->baseSelectAST = $baseSelectAST ?? function (): Select {
            $from = new RelationReference($this->getName()->createNode());
            $from->setAlias(new Identifier(TableGateway::ALIAS_SELF));
            return $this->tableLocator->getStatementFactory()->select(
                [new TargetElement(new ColumnReference(TableGateway::ALIAS_SELF, '*'))],
                [$from]
            );
        };

        $this->baseCountAST  = $baseCountAST ?? function (): Select {
            $from = new RelationReference($this->getName()->createNode());
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

    public function getName(): metadata\TableName
    {
        return $this->gateway->getName();
    }

    public function getColumns(): metadata\Columns
    {
        return $this->gateway->getColumns();
    }

    public function getPrimaryKey(): metadata\PrimaryKey
    {
        return $this->gateway->getPrimaryKey();
    }

    public function getReferences(): metadata\References
    {
        return $this->gateway->getReferences();
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
            $this->getName(),
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
            \Closure::fromCallable([$this, 'createSelectAST']),
            $this->generateStatementKey(TableGateway::STATEMENT_SELECT, $this->baseSelectAST, $this->fragments)
        );
    }

    public function createSelectCountStatement(): NativeStatement
    {
        $fragments = $this->fragments->filter(
            fn(Fragment $fragment) => !$fragment instanceof SelectFragment || $fragment->isUsedForCount()
        );
        return $this->tableLocator->createNativeStatementUsingCache(
            function () use ($fragments): SelectCommon {
                $select = ($this->baseCountAST)();
                $fragments->applyTo($select, true);
                return $select;
            },
            $this->generateStatementKey(TableGateway::STATEMENT_COUNT, $this->baseCountAST, $fragments)
        );
    }

    /**
     * Returns a cache key for the statement being generated
     */
    private function generateStatementKey(string $statementType, \Closure $baseAST, FragmentList $fragments): ?string
    {
        if (null === ($fragmentKey = $fragments->getKey())) {
            return null;
        }
        return \sprintf(
            '%s.%s.%s.%s',
            $this->getConnection()->getConnectionId(),
            $statementType,
            TableLocator::hash([
                $this->getName(),
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

    public function executeCount()
    {
        $native = $this->createSelectCountStatement();
        if ([] === ($namesHash = $native->getNamedParameterMap())) {
            $result = $this->gateway->getConnection()->execute($native->getSql());
        } else {
            $parameters = \array_filter(
                $this->fragments->getParameters(),
                fn($key) => isset($namesHash[$key]),
                \ARRAY_FILTER_USE_KEY
            );
            $result     = $native->executeParams($this->gateway->getConnection(), $parameters);
        }

        $result->setMode(\PGSQL_NUM);
        return $result[0][0];
    }
}
