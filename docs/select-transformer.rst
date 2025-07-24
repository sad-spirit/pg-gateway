=====================================
Transforming the generated ``SELECT``
=====================================

``Fragment`` implementations can only modify the child ``Node``\ s of an existing ``Statement``. Sometimes, though,
it is needed to replace the root ``Statement`` itself.

``SelectTransformer`` is a decorator for an implementation of ``SelectProxy``, replacing its generated ``Select``
statement with another one. Its subclass may e.g.

* Combine the given ``Select`` with another one using ``UNION``, returning a new ``SetOpSelect`` object.
* Put the original ``Select`` into a CTE or a sub-query in ``FROM``, returning the outer ``Select``.

``SelectTransformer`` class
===========================

.. code-block:: php

    abstract class SelectTransformer implements SelectProxy
    {
        public function __construct(
            protected readonly SelectProxy $wrapped,
            protected readonly TableLocator $tableLocator,
            private readonly ?string $key = null
        );

        public function createSelectStatement() : NativeStatement;
        abstract protected function transform(SelectCommon $original) : SelectCommon;
    }

Constructor accepts

* A ``SelectProxy`` to decorate;
* An instance of ``TableLocator``, needed for its :ref:`statement generation method <table-locator-statements>`
  in the base class, may have other uses in subclasses;
* A ``$key`` identifying the transformer.

The ``$key`` argument is used in ``getKey()`` method :ref:`defined in KeyEquatable interface <fragments-base-key>`:

* If the ``$key`` is ``null``, the method will return ``null``, consequently the generated statement will not be cached.
  This is also the case if ``$wrapped->getKey()`` returns ``null``.
* Otherwise, it will return a string key based on the ``$key`` argument and on the ``$wrapped->getKey()`` return value.

``transform()`` method of a subclass should accept the query AST and return the AST for another query,
which presumably includes the original one somewhere.

``createSelectStatement()`` returns SQL of the transformed statement, similar to the same method
of :ref:`TableSelect <gateways-table-select>`.

.. note::

    The query for a total number of rows in ``executeCount()`` will not be transformed.

Example
=======

Assuming the :ref:`schema from tutorial <tutorial-schema>`, let's create a transformer that fetches
a list of currently assigned roles for users

.. code-block:: php

    use sad_spirit\pg_builder\Select;
    use sad_spirit\pg_builder\SelectCommon;
    use sad_spirit\pg_gateway\SelectProxy;
    use sad_spirit\pg_gateway\SelectTransformer;
    use sad_spirit\pg_gateway\TableLocator;

    class ActiveRoles extends SelectTransformer
    {
        public function __construct(SelectProxy $wrapped, TableLocator $tableLocator)
        {
            parent::__construct($wrapped, $tableLocator, 'active-roles');
        }

        protected function transform(SelectCommon $original): SelectCommon
        {
            /** @var Select $outer */
            $outer = $this->tableLocator->createFromString(<<<SQL
    select u.*, rr.*
    from (select 1 as id) as u
         left join (
            select ur.*, r.name as role_name, r.description as role_description
            from rbac.users_roles as ur, rbac.roles as r
            where ur.role_id = r.id and
                  current_date between coalesce(ur.valid_from, 'yesterday') and coalesce(ur.valid_to, 'tomorrow')
         ) as rr on u.id = rr.user_id
    SQL
            );

            $outer->from[0]->left->query = $original;
            $outer->order->replace(clone $original->order);
            $outer->order[] = 'role_name';

            return $outer;
        }
    }

The ``select 1 as id`` part is added to make the query look more legit for an IDE, it is replaced by
the original query that should have an ``id`` field. It is possible to omit it, but more steps will be required
to inject the original query.

Note how we are copying the ``ORDER BY`` clause to the outer query and then additionally sorting by role name.
``clone`` is essential here, the clause will be moved rather than copied without it.

Let's check what's being generated:

.. code-block:: php

    use sad_spirit\pg_gateway\builders\FluentBuilder;
    use sad_spirit\pg_wrapper\Connection;

    $locator   = new TableLocator(new Connection(' ... '));
    $withRoles = new ActiveRoles(
        $locator->select('rbac.users', fn (FluentBuilder $builder) => $builder
            ->orderBy('login desc')
            ->limit(1)),
        $locator
    );

    echo $withRoles->createSelectStatement()->getSql();

outputting

.. code-block:: postgres

    select u.*, rr.*
    from (
            select self.*
            from rbac.users as self
            order by login desc
            limit $1
        ) as u left join (
            select ur.*, r."name" as role_name, r.description as role_description
            from rbac.users_roles as ur, rbac.roles as r
            where ur.role_id = r.id
                and current_date between coalesce(ur.valid_from, 'yesterday') and coalesce(ur.valid_to, 'tomorrow')
        ) as rr on u.id = rr.user_id
    order by login desc, role_name

The query generated by a gateway was successfully injected and its ``ORDER BY`` clause copied. The ``LIMIT`` is applied
to the number of users, so you'll get one user with all his currently assigned roles and may paginate users list
without caring about the number of assigned roles (or whether any are assigned).

Similar query can be generated using ``join()`` with ``ExplicitJoinStrategy``, though you'll have
to start from gateway to ``rbac.users_roles`` and check for number of users using the joined part for ``rbac.users``.

As you can see, transformers may be more expressive when generating joins. The possible downside is that using them
will require more knowledge of **pg_builder** API and structure of the AST, as in above

.. code-block:: php

    $outer->from[0]->left->query = $original;
