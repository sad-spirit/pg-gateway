========
Gateways
========

Table Gateways in this package are responsible for generating base SQL for table operations and applying additional
fragments to it. Default implementations described in this chapter will be usable in the majority of cases, with
custom code going into fragment builders.

Interfaces
==========

``TableGateway``
----------------

This interface extends ``TableAccessor`` (thus gateways provide :ref:`access to table metadata <metadata-containers>`)
and defines four methods corresponding to SQL statements:

.. code-block:: php

    namespace sad_spirit\pg_gateway;

    use sad_spirit\pg_builder\SelectCommon;
    use sad_spirit\pg_wrapper\Result;

    interface TableGateway extends TableAccessor
    {
        public function delete(
            null|Fragment|FragmentBuilder|iterable<Fragment|FragmentBuilder>|\Closure(builders\FragmentListBuilder) $fragments = null,
            array<string, mixed> $parameters = []
        ) : Result;

        public function insert(
            array<string, mixed>|SelectCommon|SelectBuilder $values,
            null|Fragment|FragmentBuilder|iterable<Fragment|FragmentBuilder>|\Closure(builders\FragmentListBuilder) $fragments = null,
            array<string, mixed> $parameters = []
        ) : Result;

        public function select(
            null|Fragment|FragmentBuilder|iterable<Fragment|FragmentBuilder>|\Closure(builders\FragmentListBuilder) $fragments = null,
            array<string, mixed> $parameters = []
        ) : SelectProxy;

        public function update(
            array<string, mixed> $set,
            null|Fragment|FragmentBuilder|iterable<Fragment|FragmentBuilder>|\Closure(builders\FragmentListBuilder) $fragments = null,
            array<string, mixed> $parameters = []
        ): Result;
    }

``$fragments`` parameter for the above methods provides additional parts for the query being generated. Most commonly,
it will either be a :ref:`fluent builder object <default-builder>` created by ``TableLocator::createBuilder()``

.. code-block:: php

    $locator->createGateway('example')
        ->select(
            $locator->createBuilder('example')
                ->returningColumns()
                    ->only(['id', 'name'])
                ->notBoolColumn('deleted')
                ->orderBy('added')
                ->limit(10)
        );

or a closure that receives such an object

.. code-block:: php

    use sad_spirit\pg_gateway\builders\FluentBuilder;

    $locator->createGateway('example')
        ->select(fn(FluentBuilder $builder) => $builder
             ->returningColumns()
                ->only(['id', 'name'])
             ->notBoolColumn('deleted')
             ->orderBy('added')
             ->limit(10));

``$values`` (when an array) / ``$set`` parameter for ``insert()`` / ``update()`` is an associative array of the form
``'column name' => 'value'``. Here ``'value'`` may be either a literal or an instance of ``Expression`` which is used
to set the column value to an SQL expression:

.. code-block:: php

    $documentsGateway->insert([
        'id'    => 1,
        'title' => 'default',
        'added' => new Expression('now()')
    ]);

Literals will not be embedded into the generated SQL, parameter placeholders will be inserted and their values
eventually passed to ``Connection::executeParams()``.

Note also that while ``delete()`` / ``insert()`` / ``update()`` methods immediately return ``Result`` objects,
``select()`` returns a ``SelectProxy`` implementation.

``$parameters`` array provides values for parameter placeholders in the query. If using the builder as shown above,
those are more likely to be passed with the fragment

.. code-block:: php

    // equal() adds "foo = :foo::foo_type" condition to the query, 'bar' is a value for :foo placeholder
    $gateway->select(fn (FluentBuilder $builder) => $builder
        ->equal('foo', 'bar'));

but can be passed separately if fragments are created explicitly

.. code-block:: php

    $gateway->select(
        new OperatorCondition(
            $gateway->getDefinition()->getColumns()->get('foo'),
            $locator->getTypeConverterFactory(),
            '='
        ),
        ['foo' => 'bar']
    );


``AdHocStatement``
------------------

It is sometimes needed to modify the query AST in a completely custom way. Methods defined in ``AdHocStatement``
interface allow exactly this:

.. code-block:: php

    namespace sad_spirit\pg_gateway;

    use sad_spirit\pg_builder\{
        Delete,
        Insert,
        SelectCommon,
        Update
    };
    use sad_spirit\pg_wrapper\Result;

    interface AdHocStatement
    {
        public function deleteWithAST(\Closure(Delete) $closure, array<string, mixed> $parameters = []) : Result;
        public function insertWithAST($values, \Closure(Insert) $closure, array<string, mixed> $parameters = []) : Result;
        public function selectWithAST(\Closure(SelectCommon) $closure, array<string, mixed> $parameters = []) : SelectProxy;
        public function updateWithAST(array $set, \Closure(Update) $closure, array<string, mixed> $parameters = []) : Result;
    }

Closures passed to its methods accept the base AST of the query and may change it using the full
capabilities of **pg_builder**:

.. code-block:: php

    use sad_spirit\pg_builder\Delete;

    $gateway->deleteWithAST(function (Delete $delete) {
        // Modify the $delete query any way you like
        $delete->with->merge('with recursive foo as (...)');
        $delete->using[] = 'foo'
        $delete->where->and('self.bar @@@ foo.id');
    });

The downside is that a query built in that way will not be cached.

``PrimaryKeyAccess``
--------------------

Accessing rows by primary key is an extremely common operation, this interface defines methods for it

.. code-block:: php

    namespace sad_spirit\pg_gateway;

    use sad_spirit\pg_wrapper\Result;

    interface PrimaryKeyAccess
    {
        public function deleteByPrimaryKey(mixed $primaryKey) : Result;
        public function selectByPrimaryKey(mixed $primaryKey) : SelectProxy;
        public function updateByPrimaryKey(mixed $primaryKey, array $set): Result;

        public function upsert(array $values): array;
    }

The ``upsert()`` method builds and executes an ``INSERT ... ON CONFLICT DO UPDATE ...`` statement
returning the primary key of the inserted / updated row. Assuming
:ref:`schema from the tutorial <tutorial-schema>`, this code

.. code-block:: php

    $rolesGateway->upsert([
        'id'          => 1
        'name'        => 'visitor',
        'description' => 'can view stuff'
    ]);

will most probably return ``['id' => 1]`` after either creating a new visitor role or updating an existing row.

``SelectProxy``
---------------

Unlike other methods of ``TableGateway``, ``select()`` *will not* immediately execute the generated ``SELECT`` statement,
but will return a proxy object implementing ``SelectProxy`` interface

.. code-block:: php

    namespace sad_spirit\pg_gateway;

    use sad_spirit\pg_wrapper\Result;

    interface SelectProxy extends SelectBuilder, Parametrized, TableAccessor, \IteratorAggregate<int, array>
    {
        public function executeCount() : int|numeric-string;
        public function getIterator() : Result;
    }

where ``SelectBuilder`` is an interface for objects generating AST of the complete ``SELECT`` statement

.. code-block:: php

    namespace sad_spirit\pg_gateway;

    use sad_spirit\pg_builder\SelectCommon;

    interface SelectBuilder extends KeyEquatable
    {
        public function createSelectAST() : SelectCommon;
    }

``KeyEquatable`` and ``Parametrized`` are base interfaces for query fragments, implementing them is required
to use ``SelectProxy`` inside fragments.

An implementation of ``SelectProxy`` should contain all the data needed to execute
``SELECT`` (and ``SELECT COUNT(*)``), with actual queries executed only when ``getIterator()``
or ``executeCount()`` is called, respectively.

The most common case still looks the same way as if ``select()`` did return ``Result``:

.. code-block:: php

    foreach ($gateway->select($fragments) as $row) {
        // process the row
    }

But having a proxy object allows less common cases as well:

- It is frequently needed to additionally execute a query returning the total number of rows that satisfy
  the given conditions (e.g. for pagination), this is done with ``executeCount()``;
- The configured object can be used inside a more complex query, this is covered by ``createSelectAST()`` method.

Implementations
===============

The package contains three implementations of ``TableGateway`` interface.
An instance of one of these will be returned by ``TableLocator::createGateway()`` if the locator was not configured
with custom gateway factories or if none of these returned a more specific gateway object.

What exactly will be returned depends on

- whether a ``PRIMARY KEY`` constraint was defined on the table and
- the number of columns in that key.


``gateways\GenericTableGateway``
--------------------------------

This is the simplest gateway implementation, an instance of which is returned for tables that do not have a primary key
defined. In addition to the methods defined in ``TableGateway`` it contains methods to create statements and
to create the builder for that particular table

.. code-block:: php

    namespace sad_spirit\pg_gateway\gateways;

    use sad_spirit\pg_gateway\{
        AdHocStatement,
        FragmentList,
        TableGateway,
        builders\FragmentListBuilder
    };
    use sad_spirit\pg_builder\NativeStatement;

    class GenericTableGateway implements TableGateway, AdHocStatement
    {
        public function createDeleteStatement(FragmentList $fragments) : NativeStatement;
        public function createInsertStatement(FragmentList $fragments) : NativeStatement;
        public function createUpdateStatement(FragmentList $fragments) : NativeStatement

        public function createBuilder() : FragmentListBuilder;
    }

The results of those can be used for e.g. ``prepare()`` / ``execute()``. ``FragmentList``
is an object that keeps all the fragments used in a query and possibly parameter values for those.
It is returned by ``getFragment()`` method of a fluent builder
and can also be created via ``FragmentList::normalize()``
from whatever can be passed as ``$fragments`` to ``TableGateway`` methods.

Note the lack of ``createSelectStatement()``, methods of ``TableSelect`` can be used for that.

``createBuilder()`` calls :ref:`TableLocator::createBuilder() <table-locator-factories>` under the hood so everything
said about that method applies.

``gateways\PrimaryKeyTableGateway``
-----------------------------------

If a table has a ``PRIMARY KEY`` constraint defined and the key has only one column, then an instance of this class
will be returned.

.. code-block:: php

    namespace sad_spirit\pg_gateway\gateways;

    use sad_spirit\pg_gateway\{
        FragmentList,
        PrimaryKeyAccess,
        builders\PrimaryKeyBuilder
    };
    use sad_spirit\pg_builder\NativeStatement;

    class PrimaryKeyTableGateway extends GenericTableGateway implements PrimaryKeyAccess
    {
        use PrimaryKeyBuilder;

        public function createUpsertStatement(FragmentList $fragments) : NativeStatement;
    }

where ``PrimaryKeyBuilder`` contains one method: ``createPrimaryKey()``. It is used to create a ``WHERE`` condition
for the table's primary key.

``gateways\CompositePrimaryKeyTableGateway``
--------------------------------------------

When the table's ``PRIMARY KEY`` constraint contains two or more columns, this subclass of ``PrimaryKeyTableGateway``
will be used. As such a table is generally used for defining an M:N relationship, we provide a method
that allows to replace all records related to a key from one side of relationship:

- ``replaceRelated(array $primaryKeyPart, iterable $rows) : array``

Assuming the :ref:`schema from tutorial <tutorial-schema>` we can use this method to replace the list of roles
assigned to the user after e.g. editing user's profile:

.. code-block:: php

    $tableLocator->atomic(function (TableLocator $locator) use ($userData, $roles) {
        $pkey = $locator->createGateway('example.users')
            ->upsert($userData);

        return $locator->createGateway('example.users_roles')
            ->replaceRelated($pkey, $roles);
    });

``TableSelect``
---------------

This is the default implementation of ``SelectProxy``, it is implemented immutable as is the case with
all other Fragments

.. code-block:: php

    namespace sad_spirit\pg_gateway;

    use sad_spirit\pg_builder\NativeStatement;

    final class TableSelect implements SelectProxy
    {
        public function __construct(
            TableLocator $tableLocator,
            TableGateway $gateway,
            FragmentList $fragments,
            \Closure $baseSelectAST = null,
            \Closure $baseCountAST = null
        );

        public function createSelectStatement() : NativeStatement;
        public function createSelectCountStatement() : NativeStatement;

        public function fetchFirst() : ?array;
    }

The constructor accepts closures creating base statement ASTs for ``SELECT`` and ``SELECT count(*)`` queries.
If e.g. a table uses "soft-deletes" then it may make sense to start from

.. code-block:: postgres

    SELECT self.* FROM foo AS self WHERE not self.deleted

Results of ``createSelectStatement()`` / ``createSelectCountStatement()`` can be used for ``prepare()`` / ``execute()``.

``$select->fetchFirst()`` method is a shorthand for ``$select->getIterator()->current()``.
