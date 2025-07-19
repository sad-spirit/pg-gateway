.. _conditions:

==========
Conditions
==========

Subclasses of ``Condition`` serve as wrappers for ``Node`` instances implementing ``ScalarExpression`` interface
(defined in **pg_builder** package), that should (presumably) return boolean values when used in SQL.

``Condition`` instances are used by fragments that modify ``WHERE`` and ``HAVING`` clauses and by the ``JoinFragment``
for an actual ``JOIN`` condition.

Conditions behave like Specifications from
`the similarly named pattern <https://en.wikipedia.org/wiki/Specification_pattern>`__  and can be combined
via ``AND`` / ``OR`` / ``NOT`` operators.
They do not implement ``isSatisfiedBy()`` method, though, for more or less obvious reasons.

Base ``Condition`` class
========================

.. code-block:: php

    namespace sad_spirit\pg_gateway;

    use sad_spirit\pg_builder\nodes\ScalarExpression;

    abstract class Condition implements KeyEquatable, FragmentBuilder
    {
        // defined in FragmentBuilder
        public function getFragment() : fragments\WhereClauseFragment;

        final public function generateExpression() : ScalarExpression;
        abstract protected function generateExpressionImpl() : ScalarExpression;

        final public static function and(self ...$children) : conditions\AndCondition;
        final public static function or(self ...$children) : conditions\OrCondition;
        final public static function not(self $child) : conditions\NotCondition;
    }

This abstract class implements ``KeyEquatable`` and ``FragmentBuilder`` interfaces. The latter allows passing instances of
``Condition`` to query methods of ``TableGateway``, appending them to the query's ``WHERE`` clause.
``getKey()`` method required by the former is not actually implemented, this should be done in child classes. 

The public ``generateExpression()`` method returns a clone of whatever was returned by ``generateExpressionImpl()``.
This is done to prevent potential problems when reusing the same expression in multiple
queries / multiple parts of the query, as **pg_builder**'s ``Node`` classes keep a reference to their parent ``Node``.

``generateExpressionImpl()``  should be implemented by child classes. Its name starts with "generate" as a hint:
it should preferably generate the ``ScalarExpression`` on "as needed" basis rather than pre-generate and store that.
Real world ``Condition``\ s will often use ``Parser`` and parsing may be slow.

Combining ``Condition``\ s via ``AND`` / ``OR`` / ``NOT``
=========================================================

Static methods of ``Condition`` allow combining conditions using logical operators:

``and()``
    Creates a ``Condition`` that combines several other ``Condition``\ s using ``AND`` operator.
``or()``
    Creates a ``Condition`` that combines several other ``Condition``\ s using ``OR`` operator.
``not()``
    Creates a negated ``Condition``.

For example:

.. code-block:: php

    $combined = Condition::and(
        Condition::not($firstCondition),
        Condition::or($secondCondition, $thirdCondition)
    );

Classes returned by the above methods can also be instantiated explicitly.

Constructors of ``AndCondition`` / ``OrCondition`` accept variable number of ``Condition`` arguments
``__construct(Condition ...$children)`` and will throw an ``InvalidArgumentException`` if none are given.

Constructor of ``NotCondition`` naturally accepts only one ``Condition`` argument.

Using the above example with explicit classes:

.. code-block:: php

    $combined = new AndCondition(
        new NotCondition($firstCondition),
        new OrCondition($secondCondition, $thirdCondition)
    );

All these classes implement ``Parametrized`` interface and will propagate parameters from their child ``Condition``\ s.

Passing parameters with ``Condition``\ s
========================================

While it is possible to also implement ``Parametrized`` in custom conditions, the suggested approach is to use 
``conditions\ParametrizedCondition`` decorator instead:

.. code-block:: php

    namespace sad_spirit\pg_gateway\conditions;

    use sad_spirit\pg_gateway\{
        Condition,
        Parametrized
    };

    final class ParametrizedCondition extends Condition implements Parametrized
    {
        public function __construct(Condition $wrapped, array<string, mixed> $parameters)
    }

Its constructor will throw an ``InvalidArgumentException`` if ``$wrapped`` already implements ``Parametrized``.

Example:

.. code-block:: php

    $condition = new ParametrizedCondition(
        new SqlStringCondition($parser, 'foo = :bar::baz'),
        ['bar' => new Baz()]
    );

Other ``Condition`` subclasses
==============================

It is rarely needed to manually create these classes as they are all supported by 
:ref:`custom builder classes <fragment-builders>` and 
:ref:`builder methods of FluentBuilder <default-builder-api>`.

``conditions\column\AnyCondition``
----------------------------------

Generates a ``self.foo = any(:foo::foo_type[])`` condition for the ``foo`` table column.
This is similar to ``foo IN (...)``, but requires only one placeholder.

Constructor accepts a ``metadata\Column`` instance and
an implementation of ``TypeNameNodeHandler`` (from **pg_builder**):

.. code-block:: php

    $condition = new AnyCondition(
        $gateway->getDefinition()->getColumns()->get('foo'),
        $locator->getTypeConverterFactory()
    );


``conditions\column\BoolCondition``
-----------------------------------

Uses the value of the ``bool``-typed column as a ``Condition``. Constructor accepts an instance of
``metadata\Column``, will throw ``LogicException`` if it is not of type bool.

.. code-block:: php

    $condition = new BoolCondition($gateway->getDefinition()->getColumns()->get('flag'));

``conditions\ExistsCondition``
------------------------------

Generates the ``EXISTS(SELECT ...)`` condition using the given ``SelectBuilder`` implementation.
The constructor may also accept a join ``Condition`` and an explicit alias for a table within ``EXISTS(...)``
(will be autogenerated if not given):

.. code-block:: php

    $condition = new ExistsCondition(
        $gateway->select(/* ... some configuration ... */),
        new ForeignKeyCondition($foreignKey),
        'custom'
    );

``conditions\ForeignKeyCondition``
----------------------------------

Generates a join condition using the given foreign key constraint. Constructor accepts a ``metadata\ForeignKey`` object
and a flag specifying whether we are joining from the side of the child table (one having the constraint defined)
or the parent one (referenced by constraint). ``self`` and ``joined`` aliases will be used according to that flag.

.. code-block:: php

    // This will use 'self' alias for referenced table and 'joined' alias for child one
    $condition = new ForeignKeyCondition($foreignKey, false);


``conditions\column\IsNullCondition``
-------------------------------------

Generates a ``self.foo IS NULL`` Condition for the ``foo`` table column.
Constructor accepts an instance of ``metadata\Column``.

.. code-block:: php

    $condition = new IsNullCondition($gateway->getDefinition()->getColumns()->get('foo'));


``conditions\column\NotAllCondition``
-------------------------------------

Generates a ``self.foo <> all(:foo::foo_type[])`` condition for the ``foo`` table column. 
This is similar to ``foo NOT IN (...)``, but requires only one placeholder.

Constructor accepts a ``metadata\Column`` instance and an implementation of ``TypeNameNodeHandler``:

.. code-block:: php

    $condition = new NotAllCondition(
        $gateway->getDefinition()->getColumns()->get('foo'),
        $locator->getTypeConverterFactory()
    );


``conditions\column\OperatorCondition``
---------------------------------------

Generates a ``self.foo OPERATOR :foo::foo_type`` condition for the ``foo`` table column. Constructor accepts an instance
of ``metadata\Column``, an implementation of ``TypeNameNodeHandler`` and operator as string:

.. code-block:: php

    $condition = new OperatorCondition(
        $gateway->getDefinition()->getColumns()->get('foo'),
        $locator->getTypeConverterFactory(),
        '>='
    );


``conditions\PrimaryKeyCondition``
----------------------------------

A condition for finding a table row by its primary key. Constructor accepts a ``metadata\PrimaryKey`` object and
an implementation of ``TypeNameNodeHandler``:

.. code-block:: php

    $condition = new PrimaryKeyCondition(
        $gateway->getDefinition()->getPrimaryKey(),
        $locator->getTypeConverterFactory()
    );

This class has an additional ``normalizeValue(mixed $value): array<string, mixed>`` method which accepts the value
probably passed to one of the methods of ``PrimaryKeyAccess`` interface and ensures that it can be used in parameter
values for the query. E.g.

.. code-block:: php

    $condition->normalizeValue(1);

will return a ``['id' => 1]`` array if table's primary key consists of a single ``id`` column and

.. code-block:: php

    $condition->normalizeValue(['foo_id' => 2]);

will throw an Exception if table's primary key consists of ``foo_id`` and ``bar_id`` columns.


``conditions\SqlStringCondition``
---------------------------------

Condition represented by an SQL string. Constructor accepts a ``Parser`` instance and a string.

.. code-block:: php

    $condition = new SqlStringCondition(
        $locator->getParser(),
        "current_date between coalesce(self.valid_from, 'yesterday') and coalesce(self.valid_to, 'tomorrow')"
    );

The string will eventually be processed by ``Parser::parseExpression()`` method and added to query AST, so
``self`` aliases in it will be replaced if needed and parameter placeholders will be processed.
