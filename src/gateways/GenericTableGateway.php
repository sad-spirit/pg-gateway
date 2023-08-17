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

namespace sad_spirit\pg_gateway\gateways;

use sad_spirit\pg_gateway\{
    FragmentList,
    SelectProxy,
    TableGateway,
    TableLocator,
    TableSelect,
    fragments\ClosureFragment,
    fragments\InsertSelectFragment,
    fragments\SetClauseFragment,
    exceptions\InvalidArgumentException,
    metadata\Columns,
    metadata\PrimaryKey,
    metadata\References
};
use sad_spirit\pg_builder\{
    Delete,
    Insert,
    NativeStatement,
    SelectCommon,
    Update
};
use sad_spirit\pg_builder\nodes\{
    Identifier,
    QualifiedName,
    lists\SetClauseList,
    range\InsertTarget,
    range\UpdateOrDeleteTarget
};
use sad_spirit\pg_wrapper\{
    Connection,
    ResultSet
};

/**
 * A generic implementation of TableGateway
 */
class GenericTableGateway implements TableGateway
{
    private QualifiedName $name;
    protected TableLocator $tableLocator;
    private ?Columns $columns = null;
    private ?PrimaryKey $primaryKey = null;
    private ?References $references = null;

    /**
     * Creates an instance of GenericTableGateway or its subclass based on table's primary key
     *
     * @param QualifiedName $name
     * @param TableLocator $tableLocator
     * @return self
     */
    public static function create(QualifiedName $name, TableLocator $tableLocator): self
    {
        $primaryKey = new PrimaryKey($tableLocator->getConnection(), $name);

        switch (\count($primaryKey)) {
            case 0:
                $gateway = new self($name, $tableLocator);
                break;

            case 1:
                $gateway = new PrimaryKeyTableGateway($name, $tableLocator);
                break;

            default:
                $gateway = new CompositePrimaryKeyTableGateway($name, $tableLocator);
        }

        $gateway->primaryKey = $primaryKey;
        return $gateway;
    }

    public function __construct(QualifiedName $name, TableLocator $tableLocator)
    {
        $this->name = $name;
        $this->tableLocator = $tableLocator;
    }

    public function getName(): QualifiedName
    {
        return clone $this->name;
    }

    public function getConnection(): Connection
    {
        return $this->tableLocator->getConnection();
    }

    public function getColumns(): Columns
    {
        return $this->columns ??= new Columns($this->getConnection(), $this->name);
    }

    public function getPrimaryKey(): PrimaryKey
    {
        return $this->primaryKey ??= new PrimaryKey($this->getConnection(), $this->name);
    }

    public function getReferences(): References
    {
        return $this->references ??= new References($this->getConnection(), $this->name);
    }

    public function delete($fragments = null, array $parameters = []): ResultSet
    {
        $fragmentList = FragmentList::normalize($fragments)
            ->mergeParameters($parameters);

        return $this->execute($this->createDeleteStatement($fragmentList), $fragmentList);
    }

    /**
     * {@inheritDoc}
     * @psalm-suppress RedundantConditionGivenDocblockType
     */
    public function insert($values, $fragments = null, array $parameters = []): ResultSet
    {
        $fragmentList = FragmentList::normalize($fragments)
            ->mergeParameters($parameters);

        if ($values instanceof SelectProxy) {
            $fragmentList->add(new InsertSelectFragment($values));
        } elseif ($values instanceof SelectCommon) {
            $fragmentList->add(new ClosureFragment(
                static function (Insert $insert) use ($values) {
                    $insert->values = $values;
                }
            ));
        } elseif (\is_array($values)) {
            if ([] !== $values) {
                $fragmentList->add(new SetClauseFragment(
                    $this->getColumns(),
                    $this->tableLocator,
                    $values
                ));
            }
        } else {
            throw new InvalidArgumentException(sprintf(
                "\$values should be either of: an array, an instance of SelectCommon,"
                . " an implementation of SelectProxy; %s given",
                \is_object($values) ? 'object(' . \get_class($values) . ')' : \gettype($values)
            ));
        }

        return $this->execute($this->createInsertStatement($fragmentList), $fragmentList);
    }

    public function select($fragments = null, array $parameters = []): TableSelect
    {
        return new TableSelect($this->tableLocator, $this, $fragments, $parameters);
    }

    public function update(array $set, $fragments = null, array $parameters = []): ResultSet
    {
        $native = $this->createUpdateStatement($list = new FragmentList(
            new SetClauseFragment($this->getColumns(), $this->tableLocator, $set),
            FragmentList::normalize($fragments)
                ->mergeParameters($parameters)
        ));

        return $this->execute($native, $list);
    }

    /**
     * Executes the given $statement possibly using parameters from $fragments
     *
     * @param NativeStatement $statement
     * @param FragmentList $fragments
     * @return ResultSet
     */
    private function execute(NativeStatement $statement, FragmentList $fragments): ResultSet
    {
        return [] === $statement->getParameterTypes()
            ? $this->getConnection()->execute($statement->getSql())
            : $statement->executeParams($this->getConnection(), $fragments->getParameters());
    }

    /**
     * Generates a DELETE statement using given fragments
     *
     * @param FragmentList $fragments
     * @return NativeStatement
     */
    public function createDeleteStatement(FragmentList $fragments): NativeStatement
    {
        return $this->tableLocator->createNativeStatementUsingCache(
            function () use ($fragments): Delete {
                $delete = $this->tableLocator->getStatementFactory()->delete(new UpdateOrDeleteTarget(
                    $this->getName(),
                    new Identifier(self::ALIAS_SELF)
                ));
                $fragments->applyTo($delete);

                return $delete;
            },
            $this->generateStatementKey(self::STATEMENT_DELETE, $fragments)
        );
    }

    /**
     * Generates an INSERT statement using given fragments
     *
     * @param FragmentList $fragments
     * @return NativeStatement
     */
    public function createInsertStatement(FragmentList $fragments): NativeStatement
    {
        return $this->tableLocator->createNativeStatementUsingCache(
            function () use ($fragments): Insert {
                $insert = $this->tableLocator->getStatementFactory()->insert(new InsertTarget(
                    $this->getName(),
                    new Identifier(TableGateway::ALIAS_SELF)
                ));
                $fragments->applyTo($insert);
                return $insert;
            },
            $this->generateStatementKey(self::STATEMENT_INSERT, $fragments)
        );
    }

    /**
     * Generates an UPDATE statement using given fragments
     *
     * @param FragmentList $fragments
     * @return NativeStatement
     */
    public function createUpdateStatement(FragmentList $fragments): NativeStatement
    {
        return $this->tableLocator->createNativeStatementUsingCache(
            function () use ($fragments): Update {
                $update = $this->tableLocator->getStatementFactory()->update(
                    new UpdateOrDeleteTarget(
                        $this->getName(),
                        new Identifier(TableGateway::ALIAS_SELF)
                    ),
                    new SetClauseList()
                );
                $fragments->applyTo($update);
                return $update;
            },
            $this->generateStatementKey(self::STATEMENT_UPDATE, $fragments)
        );
    }

    /**
     * Returns a cache key for the statement being generated
     */
    protected function generateStatementKey(string $statementType, FragmentList $fragments): ?string
    {
        if (null === ($fragmentKey = $fragments->getKey())) {
            return null;
        }
        return \sprintf(
            '%s.%s.%s.%s',
            $this->getConnection()->getConnectionId(),
            $statementType,
            TableLocator::hash($this->getName()),
            $fragmentKey
        );
    }
}
