.. _howto-builders:

=============================
How to create custom builders
=============================

While it is possible to

- create query ``Fragment``\ s without any builder and pass them in an array to ``TableGateway`` methods;
- use :ref:`generic methods of the default builder <default-builder-api>` to populate a ``FragmentList`` passed
  to ``TableGateway``;
- use the underlying methods of :ref:`its parent class <fragments-list-builder>` to add custom fragments to that list.

...this is only recommended for one-off queries or quick prototyping.

As soon as it's obvious that some fragments will be used in multiple places with a gateway to some table, it makes
sense to create a custom builder class with domain-specific methods. Using e.g. ``$builder->owner($ownerId)`` instead
of ``$builder->equal('owner_id', $ownerId)`` has an added benefit of not requiring code changes outside the ``owner()``
method itself if the relationship to owner table changes from one-to-many to many-to-many.

Builder API vs Repository API
=============================

The methods of a builder create ``Fragment`` implementations and / or add those to the list. Fragments
:ref:`should generally be independent <fragments-overview>` and combined as needed, so instead of defining
one ``withExtraStuffByThisAndThat()`` method you should define separate ``withExtraStuff()``, ``byThis()``,
and ``byThat()`` methods.

You can later create a repository class with ``selectWithExtraStuffByThisAndThat()`` method calling the above
three.

.. _howto-builders-steps:

Required steps
==============

- Create a subclass of ``builders\FluentBuilder`` or ``builders\FragmentListBuilder``;
- Make ``TableLocator`` aware of the new class;
- (Optional) Make IDE and psalm aware of the new class.

Creating a subclass
-------------------

The easiest approach is extending ``builders\FluentBuilder`` so you can wrap its methods accepting column names or
custom SQL into domain-specific methods, e.g.

.. code-block:: php

    class RolesPermissionsBuilder extends FluentBuilder
    {
        function allowed(): self
        {
            // This may be changed if you sometime decide to use enum instead of bool
            $this->boolColumn('allow');
        }
    }

However, it may sometimes make sense to limit the possible methods of the builder to *only* the domain-specific ones,
extending ``builders\FragmentListBuilder`` instead

.. code-block:: php

    class RolesPermissionsBuilder extends FragmentListBuilder
    {
        function allowed(): self
        {
            return $this->add(new BoolCondition($this->definition->getColumns()->get('allow'));
        }
    }

While this will still expose ``add()`` and ``addWithParameters()`` methods, those are more low-level and therefore less
tempting to use and easier to catch.

Configuring ``TableLocator``
----------------------------

``TableLocator`` class uses :ref:`implementations of TableGatewayFactory <factory-gateway>` to create gateways
and builders. Those can be given either to its constructor or to ``addTableGatewayFactory()`` method.

Note that constructor accepts an iterable of ``TableGatewayFactory`` so it can receive e.g. a ``tagged_iterator``
`from a Symfony DI container <https://symfony.com/doc/current/service_container/tags.html#reference-tagged-services>`__.

Unless you really need a custom factory, consider using the implementation that
:ref:`maps database schemas to PHP namespaces <factory-gateway-mapping>`.

Configuring ``.phpstorm.meta.php``
----------------------------------

Using
`the override directive with type mapping <https://www.jetbrains.com/help/phpstorm/ide-advanced-metadata.html#map>`__
allows specifying the class that is returned by ``TableLocator::createBuilder()`` for a given table name.

This step is not strictly necessary as gateway methods having a ``$fragments`` argument accept a closure for it,
that closure can be type-hinted with a proper builder's class name:

.. code-block:: php

    $locator->createGateway('rbac.roles_permissions')
        ->select(fn (RolesPermissionsBuilder $builder) => $builder->allowed());

Example
=======

Let's create a custom builder for ``rbac.users_roles`` table from the :ref:`tutorial schema <tutorial-schema>`.
We'll create it as a subclass of ``builders\FluentBuilder`` allowing generic method calls and put it into the
``app\rbac\db`` namespace

.. code-block:: php

    namespace app\rbac\db;

    use sad_spirit\pg_gateway\builders\FluentBuilder;

    class UsersRolesBuilder extends FluentBuilder
    {
        /** @return $this */
        public function active(): self
        {
            return $this->sqlCondition(
                "current_date between coalesce(self.valid_from, 'yesterday') and coalesce(self.valid_to, 'tomorrow')"
            );
        }

        /** @return $this */
        public function joinToRoles(): self
        {
            return $this->join(
                    $this->tableLocator->select('rbac.roles', fn (FluentBuilder $builder) => $builder
                        ->returningColumns()
                            ->except(['id'])
                            ->replace('/^/', 'role_')
                ))
                    // Skip this join if generating "SELECT count(*)" query
                    ->useForCount(false)
                // forces the join builder proxy to return the proxied object
                ->end();
        }
    }

For the sake of example, we are directly adding an instance of ``NameMappingGatewayFactory`` to ``$locator``,
in reality it should be done somewhere in DI container configuration:

.. code-block:: php

    $locator->addTableGatewayFactory(new NameMappingGatewayFactory([
        'rbac' => '\\app\\rbac\\db'
    ]));

Finally, let's create a ``.phpstorm.meta.php`` file containing the following directive

.. code-block:: php

    namespace PHPSTORM_META {
        override(\sad_spirit\pg_gateway\TableLocator::createBuilder(), map([
            'rbac.users_roles' => \app\rbac\db\UsersRolesBuilder::class
        ]));
    }

Now, assuming the autoloader can find the newly added class, the following code will work

.. code-block:: php

    $builder = $locator->createBuilder('rbac.users_roles');
    $result  = $locator->createGateway('rbac.users_roles')
        ->select(
            $builder->active()
                ->joinToRoles()
                ->equal('user_id', 1)
        );

with proper method suggestions on ``$builder`` object and no errors from psalm.
