================================
Query Fragments: implementations
================================

This chapter lists built-in implementations of ``Fragment`` interface and some related classes. Most of these have
:ref:`corresponding Builders <fragment-builders>` and / or :ref:`methods of the default builder <default-builder-api>`,
but may offer additional features when used directly.

.. _fragments-list:

``FragmentList``
================

An instance of this class aggregates fragments used to build a query and parameter values used to execute it:

.. code-block:: php

    namespace sad_spirit\pg_gateway;

    class FragmentList implements SelectFragment, Parametrized, \IteratorAggregate<int, Fragment>, \Countable
    {
        public static function normalize(null|iterable|Fragment|FragmentBuilder $fragments) : self;

        public function __construct(Fragment|FragmentBuilder ...$fragments);

        public function add(Fragment|FragmentBuilder $fragment) : $this;
        public function mergeParameters(array<string, mixed> $parameters, ?KeyEquatable $owner = null) : $this;
        public function getParameters() : array<string, mixed>;
        public function getSortedFragments() : Fragment[];
        public function filter(callable $callback) : self;

        // Methods defined in interfaces omitted
    }

The static ``normalize()`` method accepts ``$fragments`` parameter that usually was passed to a query method
of ``TableGateway`` and returns an instance of ``FragmentList``. ``$fragments`` can be either an implementation of
``Fragment`` or ``FragmentBuilder``, or an iterable over ``Fragment`` or ``FragmentBuilder`` implementations.
Anything else will result in ``InvalidArgumentException``.

``add()``
    Adds a fragment to the list. If an instance of ``FragmentList`` is given, it will be "flattened" with its items
    added rather than the list itself. If ``FragmentBuilder`` is given, the return value of its
    ``getFragment()`` method is added to the list, not the builder.
``mergeParameters()``
    Adds values for several named parameters. ``$owner`` is used only for a possible exception message
    in ``RecursiveParameterHolder``.
``getParameters()``
    Shorthand for

    .. code-block:: php

        $list->getParameterHolder()->getParameters();

   Note that all parameter values are returned: those that were merged into the list itself and those that belong
   to ``Parametrized`` fragments in the list.
``getSortedFragments()``
    Returns fragments sorted by priority (higher first) and key (alphabetically).
    This is used by ``applyTo()`` to apply contained Fragments in a defined order.
``filter()``
    Filters the ``FragmentList`` using the given callback (uses ``array_filter()`` internally).
    ``TableSelect::executeCount()`` uses this to leave only relevant fragments in the list.

.. tip::

    You only really need an explicit instance of ``FragmentList`` when you want to use ``create*()`` methods
    of ``GenericTableGateway``. Anywhere else the ``$fragments`` parameter will be normalized to ``FragmentList``
    automatically.

.. _fragments-list-builder:

``builders\FragmentListBuilder``
================================

This abstract class is used as a return type hint in ``TableGatewayFactory::createBuilder()`` and consequently in
``TableLocator::createBuilder()`` and ``gateways\GenericTableGateway::createBuilder()``. It is extended by
the :ref:`default builder class <default-builder>`, ``builders\FluentBuilder``.

.. code-block:: php

    namespace sad_spirit\pg_gateway\builders;

    use sad_spirit\pg_gateway\{
        Fragment,
        FragmentBuilder,
        FragmentList,
        TableDefinition,
        TableLocator
    };

    abstract class FragmentListBuilder implements FragmentBuilder
    {
        public function __construct(TableDefinition $definition, TableLocator $tableLocator);

        final public function __clone();

        // return type narrowed from FragmentBuilder
        final public function getFragment() : FragmentList;
        final public function add(Fragment|FragmentBuilder $fragment) : $this;
        final public function addWithParameters(Fragment $fragment, array $parameters) : $this;

        final protected function addProxy(Proxy $proxy) : void;
    }

It is configured with an instance of ``TableDefinition`` to create fragments suitable for a specific table.

``getFragment()`` will eventually return a ``FragmentList`` containing fragments that were directly added to a builder
via ``add()`` / ``addWithParameters()`` and those created by ``Proxy`` implementations added in ``addProxy()``.

.. _fragments-custom:

``fragments\CustomFragment``, ``fragments\CustomSelectFragment``
================================================================

These abstract classes should be extended by ``Fragment``\ s that define a custom ``applyTo()`` implementation.
Their constructors accept a ``$key`` argument that will be returned by ``getKey()`` methods, so statements using these
are cacheable, unlike ``ClosureFragment`` below.

``fragments\ParametrizedFragment``
==================================

This is a decorator for instances of ``Fragment`` that also accepts an array of parameters used by that ``Fragment``.

It is recommended to use this with custom ``Fragment``\ s rather than implement ``Parametrized``.

``FragmentListBuilder::addWithParameters()`` uses this class internally.

.. code-block:: php
    :caption: Using ``CustomSelectFragment`` and ``ParametrizedFragment`` to add ``LIMIT ... WITH TIES``

    use sad_spirit\pg_gateway\fragments\CustomSelectFragment;
    use sad_spirit\pg_gateway\fragments\ParametrizedFragment;
    use sad_spirit\pg_builder\Statement;
    use sad_spirit\pg_builder\Select;

    $fragment = new ParametrizedFragment(
        new class ('limit-ties', false) extends CustomSelectFragment {
            public function applyTo(Statement $statement, bool $isCount = false): void
            {
               /** @var Select $statement */
               $statement->order->replace('title');
               $statement->limit = ':ties::integer';
               $statement->limitWithTies = true;
            }
        },
        ['ties' => 10]
    );


``fragments\ClosureFragment``
=============================

Wrapper for a closure passed to a query method defined in ``AdHocStatement`` interface. Queries using this fragment
won't be cached.

``fragments\InsertSelectFragment``
==================================

Wrapper for ``SelectBuilder`` object passed as ``$values`` to ``GenericTableGateway::insert()``.

.. _fragments-set:

``fragments\SetClauseFragment``
===============================

Fragment populating either the ``SET`` clause of an ``UPDATE`` statement
or columns and ``VALUES`` clause of an ``INSERT``.

This is created from ``$values`` given as an array to ``GenericTableGateway::insert()`` and from ``$set`` parameter
to ``GenericTableGateway::update()``.

You may need to use that explicitly if you want to create a preparable ``INSERT`` / ``UPDATE`` statement, e.g.

.. code-block:: php

    $update = $gateway->createUpdateStatement(new FragmentList(
        new SetClauseFragment(
            $gateway->getDefinition()->getColumns(),
            $tableLocator,
            ['name' => null]
        ),
        // For the sake of example only, using $builder->createPrimaryKey() is easier
        new PrimaryKeyCondition($gateway->getDefinition()->getPrimaryKey(), $tableLocator->getTypeConverterFactory())
    ));

    $update->prepare($gateway->getConnection());
    $update->executePrepared([
        'id'   => 1,
        'name' => 'New name'
    ]);
    $update->executePrepared([
        'id'   => 2,
        'name' => 'Even newer name'
    ]);

.. _fragments-where-having:

``fragments\WhereClauseFragment`` and ``fragments\HavingClauseFragment``
========================================================================

These fragments add an expression generated by a ``Condition`` instance to the ``WHERE`` or ``HAVING`` clause of
a ``Statement`` being built, respectively.

``Condition`` instances can be used directly in the query methods of ``TableGateway`` as they implement
the ``FragmentBuilder`` interface. This will add their expressions to the ``WHERE`` clause due to their ``getFragment()``
methods returning ``WhereClauseFragment``:

.. code-block:: php

    $gateway->select(
        $builder->createIsNotNull('field') // Adds a Condition to FragmentList
        // ...
    )

If a ``Condition`` should be applied to the ``HAVING`` clause, you should explicitly use ``HavingClauseFragment``:

.. code-block:: php

    $gateway->select(
        $builder->add(new HavingClauseFragment(
            $builder->createSqlCondition('count(self.field) > 1')
        ))
        // ...
    )

.. _fragments-target:

``fragments\TargetListFragment`` and its subclasses
===================================================

``TargetListFragment`` is an abstract base class for fragments that modify either
the output list of ``SELECT`` statement or the ``RETURNING`` clause of ``DELETE`` / ``INSERT`` / ``UPDATE``,
whichever is passed to their ``applyTo()`` method.

It is rarely needed to use its subclasses directly as there are builders and builder methods available:

.. code-block:: php

    $gateway->update(
        $builder->returningColumns()
            ->primaryKey()
        // ...
    );

    $gateway->select(
        $builder->returningExpression("coalesce(self.a, self.b) as ab")
        // ...
    );

.. _fragments-join:

``fragments\JoinFragment``
==========================

Joins an implementation of ``SelectBuilder`` to the current statement using the given ``fragments\JoinStrategy``
implementation. Can be additionally configured by a join ``Condition``.

It is recommended to use ``builders\JoinBuilder`` and related ``builders\FluentBuilder::join()`` method
rather than instantiating this class directly:

.. code-block:: php

    use sad_spirit\pg_gateway\metadata\TableName;

    $documentsGateway->select(
        $documentsBuilder->join(new TableName('documents_tags'))
            ->onForeignKey()        // configures join condition
            ->lateralLeft()         // configures join strategy (LateralSubselectStrategy)
            ->useForCount(false)    // join will not be used by executeCount()
        // ...
    );

.. _fragments-limit-offset:

``fragments\LimitClauseFragment`` and ``fragments\OffsetClauseFragment``
========================================================================

These add the ``LIMIT`` and ``OFFSET`` clauses to ``SELECT`` statements. The clauses are added with parameter placeholders
``:limit`` and ``:offset``, values for these parameters are passed to the query with the fragments
as those implement ``Parametrized``.

Builder methods are available for these:

.. code-block:: php

    $gateway->select(
        $builder->limit(5)
            ->offset(10)
 );

.. _fragments-order:

``fragments\OrderByClauseFragment``
===================================

This fragment modifies the ``ORDER BY`` list of a ``SELECT`` query using the given expressions. Its constructor accepts
two flags modifying the behaviour:

.. code-block:: php

    namespace sad_spirit\pg_gateway\fragments;

    use sad_spirit\pg_gateway\SelectFragment;
    use sad_spirit\pg_builder\Parser;
    use sad_spirit\pg_builder\nodes\OrderByElement;

    class OrderByClauseFragment implements SelectFragment
    {
        public function __construct(
            Parser $parser,
            iterable<OrderByElement|string>|string $orderBy,
            bool $restricted = true,
            bool $merge = false,
            int $priority = self::PRIORITY_DEFAULT
        );
    }

``$restricted`` toggles whether only column names and ordinal numbers are allowed in ``ORDER BY`` list. As sort options
often come from user input and have to be embedded in SQL, there is that additional protection from SQL injection
by default.

``$merge`` toggles whether the new expressions should be added to the existing ``ORDER BY`` items
rather than replace those. In that case the order in which fragments are added can be controlled with ``$priority``.

There are builder methods that create fragments replacing the existing items

.. code-block:: php

    $gateway->select(
        $builder->orderBy('foo, bar') // $restricted = true
    );

    $gateway->select(
        $builder->orderByUnsafe('coalesce(foo, bar)') // $restricted = false
    );

If there is a need to merge items, the class can be instantiated directly:

.. code-block:: php

    $gateway->select([
        new OrderByClauseFragment($parser, 'foo, bar', true, true, Fragment::PRIORITY_HIGH)
    ]);

.. _fragments-with:

``fragments\WithClauseFragment``
================================

Subclasses of this abstract class add Common Table Expressions to the query's ``WITH`` clause:

``fragments\with\SqlStringFragment``
    Accepts an SQL string that can be either a complete ``WITH`` clause (possibly
    containing several CTEs) or a single CTE: ``foo AS (...)``.
``fragments\with\SelectProxyFragment``
    Accepts an implementation of ``SelectProxy`` returned by ``TableGateway::select()``
    essentially allowing to prepare a CTE with one gateway and use it with the other.

Instances of these are added by ``withSqlString()`` and  ``withSelect()`` methods of ``builders\FluentBuilder``,
respectively. ``SelectProxyFragment`` is configured by ``builders\WithClauseBuilder``.
