=============================
How to create custom gateways
=============================

:ref:`Table gateway implementations <gateways-implementations>` in the package are designed to be usable as-is,
fragments that are passed to gateway methods should be generated by either :ref:`generic <default-builder>` or
:ref:`custom <howto-builders>` builders.

That being said, there are some valid reasons for creating custom implementations of ``TableGateway``:

- Table access should be read-only (or append-only);
- Gateway needs custom base AST and / or pre-applying some ``Fragment``\ s;
- Gateway will access a relation that is not an ordinary table (view, foreign table, ...).

The first case is trivial (just make DML methods throw exceptions) and the third one is way out of scope for
a simple how-to: it requires *at least* a custom implementation of :ref:`metadata interfaces <metadata-interfaces>`.
Thus we'll cover the remaining case.

Example: soft deletes
=====================

We'll implement a gateway that marks table rows as deleted rather than permanently deletes them. We will also
make sure that "deleted" rows are not accessed.

:ref:`Similar to builders <howto-builders-steps>` several steps should be performed

- Create a new implementation of ``TableGateway``;
- Make ``TableLocator`` aware of the new class;
- Make IDE and psalm aware of the new class. Gateways aren't usually type-hinted, so this is more or less required.

Table definition
----------------

.. code-block:: postgres

    create schema howto;

    create table howto.soft_deletes (
        id integer not null generated by default as identity,
        data text,
        deleted_at timestamp with time zone,

        constraint soft_pkey primary key (id)
    );

Limiting the returned rows
--------------------------

We'll create a subclass of ``gateways\PrimaryKeyTableGateway`` as the example table has a scalar primary key
and put it in ``app\howto\db`` namespace:

.. code-block:: php

    namespace app\howto\db;

    use sad_spirit\pg_builder\Select;
    use sad_spirit\pg_gateway\{
        Fragment,
        FragmentBuilder,
        TableSelect,
        gateways\PrimaryKeyTableGateway
    };

    class SoftDeletesGateway extends PrimaryKeyTableGateway
    {
        public function select(
            iterable|FragmentBuilder|Fragment|\Closure|null $fragments = null,
            array $parameters = []
        ): TableSelect {
            return new TableSelect(
                $this->tableLocator,
                $this,
                $this->convertFragments($fragments, $parameters),
                function (): Select {
                    /** @var Select $select */
                    $select = $this->tableLocator->createFromString(\sprintf(
                        'select self.* from %s as self where self.deleted_at is null',
                        $this->getDefinition()->getName()
                    ));

                    return $select;
                },
                function (): Select {
                    /** @var Select $select */
                    $select = $this->tableLocator->createFromString(\sprintf(
                        'select count(self.*) from %s as self where self.deleted_at is null',
                        $this->getDefinition()->getName()
                    ));

                    return $select;
                }
            );
        }
    }

``TableSelect`` constructor accepts closures that generate the base AST for ``SELECT`` / ``SELECT COUNT(*)`` statements,
here we are creating these from strings for readability.

Implementing soft deletes
-------------------------

We need to

- Make sure that generated ``UPDATE`` statements work only with not-yet-deleted rows;
- Instead of ``DELETE`` statements generate ``UPDATE``\ s setting ``deleted_at`` to current time.

The first item is done the same way as for ``SELECT`` above, by replacing base AST used for ``UPDATE``:

.. code-block:: php

    use sad_spirit\pg_builder\NativeStatement;
    use sad_spirit\pg_builder\Update;
    use sad_spirit\pg_gateway\FragmentList;
    use sad_spirit\pg_gateway\StatementType;

    public function createUpdateStatement(FragmentList $fragments): NativeStatement
    {
        return $this->tableLocator->createNativeStatementUsingCache(
            function () use ($fragments): Update {
                /** @var Update $update */
                $update = $this->tableLocator->createFromString(\sprintf(
                    // update does not allow empty set clause, the fake one will be replaced
                    'update %s as self set foo = bar where self.deleted_at is null',
                    $this->getDefinition()->getName()
                ));
                $fragments->applyTo($update);
                return $update;
            },
            $this->generateStatementKey(StatementType::Update, $fragments)
        );
    }

The second one is done by "reimplementing" ``createDeleteStatement()``:

.. code-block:: php

    use sad_spirit\pg_gateway\Expression;
    use sad_spirit\pg_gateway\fragments\SetClauseFragment;

    public function createDeleteStatement(FragmentList $fragments): NativeStatement
    {
        $fragments->add(new SetClauseFragment(
            $this->getDefinition()->getColumns(),
            $this->tableLocator,
            ['deleted_at' => new Expression('now()')]
        ));
        return $this->createUpdateStatement($fragments);
    }

Here we are taking advantage of the fact that all ``Fragment``\ s that can be applied to ``DELETE`` can also
be applied to ``UPDATE``. So we just add a new ``Fragment`` to the list that marks the row as deleted
and pass the list to the method creating an ``UPDATE``.

Wrapping up
-----------

As with builders, we add an instance of ``NameMappingGatewayFactory`` to ``$locator``:

.. code-block:: php

    $locator->addTableGatewayFactory(new NameMappingGatewayFactory([
        'howto' => '\\app\\howto\\db'
    ]));

and add a directive to ``.phpstorm.meta.php``

.. code-block:: php

    namespace PHPSTORM_META {
        override(\sad_spirit\pg_gateway\TableLocator::createGateway(), map([
            'howto.soft_deletes' => \app\howto\db\SoftDeletesGateway::class
        ]));
    }

After that we can check that whatever is being generated looks good:

.. code-block:: php

    $gateway = $locator->createGateway('howto.soft_deletes');

    echo $gateway->selectByPrimaryKey(1)
        ->createSelectStatement()
        ->getSql();

    echo \PHP_EOL . \PHP_EOL;

    echo $gateway->createUpdateStatement(new FragmentList(
        $gateway->createPrimaryKey(1),
        new SetClauseFragment(
            $gateway->getDefinition()->getColumns(),
            $locator,
            ['data' => 'some data']
        )
    ))
        ->getSql();

    echo \PHP_EOL . \PHP_EOL;

    echo $gateway->createDeleteStatement(new FragmentList($gateway->createPrimaryKey(1)))
        ->getSql();

outputting

.. code-block:: postgres

    select self.*
    from howto.soft_deletes as self
    where self.deleted_at is null
        and self.id = $1::int4

    update howto.soft_deletes as self
    set "data" = $1::"text"
    where self.deleted_at is null
        and self.id = $2::int4

    update howto.soft_deletes as self
    set deleted_at = now()
    where self.deleted_at is null
        and self.id = $1::int4
