================================
How to map query results to DTOs
================================

While **pg_gateway** and especially the underlying **pg_wrapper** library already do some "hydration" converting
Postgres data types to PHP ones, they do not provide a means to map result rows (and composite type values) represented
by PHP associative arrays to domain objects / DTOs.

Creating DTOs from the result of a query to a single table is trivial for any mapping library. Once we are adding joins
to tables with 1:N or M:N relationships, things get tricky as the data from the base table will usually be repeated.
The two options are

- Use a library that can handle "flat" / "denormalized" array;
- Return nested arrays directly from queries.

Mapping "flat" arrays
=====================

Assuming the :ref:`schema from tutorial <tutorial-schema>`, let's create a query that fetches
a list of users and their assigned roles:

.. code-block:: php

    use sad_spirit\pg_gateway\TableLocator;
    use sad_spirit\pg_gateway\builders\FluentBuilder;
    use sad_spirit\pg_wrapper\Connection;

    $locator = new TableLocator(new Connection('...'));

    $select = $locator->select('rbac.users', fn (FluentBuilder $builder) => $builder
        ->returningColumns()
            ->except(['password_hash'])
        ->join(
            $locator->select('rbac.users_roles', fn (FluentBuilder $builder) => $builder
                ->join(
                    $locator->select('rbac.roles', fn (FluentBuilder $builder) => $builder
                        ->returningColumns()
                            ->except(['id'])
                            ->replace('/^/', 'role_'))
                ))
        )
            ->left());

The query that ``$select->getIterator()`` executes looks like this (aliases may be different):

.. code-block:: postgres

    select self.id, self.login, gw_3.*
    from rbac.users as self left join (
            select gw_1.*, gw_2."name" as role_name, gw_2.description as role_description
            from rbac.users_roles as gw_1, rbac.roles as gw_2
            where gw_1.role_id = gw_2.id
        ) as gw_3 on gw_3.user_id = self.id

We'll use `pixelshaped/flat-mapper-bundle package <https://github.com/Pixelshaped/flat-mapper-bundle>`__ which
can build nested DTOs from query results. The DTOs are the following:

.. code-block:: php

    namespace app\howto\mapping\flat;

    use Pixelshaped\FlatMapperBundle\Mapping\Identifier;
    use Pixelshaped\FlatMapperBundle\Mapping\ReferenceArray;
    use Pixelshaped\FlatMapperBundle\Mapping\Scalar;

    // Cannot be readonly as mapper will need to modify $roles after creating the object
    final class User
    {
        public function __construct(
            #[Identifier]
            public readonly int $id,
            public readonly string $login,
            /** @var AssignedRole[] */
            #[ReferenceArray(AssignedRole::class)]
            private array $roles = [],
        ) {
        }

        /** @return AssignedRole[] */
        public function getRoles(): array
        {
            return $this->roles;
        }
    }

    final readonly class AssignedRole
    {
        public function __construct(
            #[Identifier('role_id')]
            public int $id,
            #[Scalar('role_name')]
            public string $name,
            #[Scalar('role_description')]
            public ?string $description,
            #[Scalar('valid_from')]
            public ?\DateTimeInterface $validFrom,
            #[Scalar('valid_to')]
            public ?\DateTimeInterface $validTo,
        ) {
        }
    }

The mapper does not require configuration other than that done via attributes above:

.. code-block:: php

    use app\howto\mapping\flat\User;
    use Pixelshaped\FlatMapperBundle\FlatMapper;

    $users = (new FlatMapper())
        ->map(User::class, $select->getIterator());


Returning nested arrays from queries
====================================

If a mapping library does not support denormalized arrays, it is possible to return nested arrays directly from
a query by using the :ref:`scalar subselect <fragment-builders-scalar>` features.

As in the previous example, let's get the list of users and their assigned roles

.. code-block:: php

    use sad_spirit\pg_gateway\TableLocator;
    use sad_spirit\pg_gateway\builders\FluentBuilder;
    use sad_spirit\pg_wrapper\Connection;

    $locator = new TableLocator(new Connection('...'));

    $select = $locator->select('rbac.users', fn (FluentBuilder $builder) => $builder
        ->returningColumns()
            ->except(['password_hash'])
        ->returningSubquery(
            $locator->select('rbac.users_roles', fn (FluentBuilder $builder) => $builder
                ->returningColumns()
                    ->except(['user_id', 'role_id'])
                ->join($locator->createGateway('rbac.roles')))
        )
            ->joinOnForeignKey()
            ->returningRow()
            ->asArray()
            ->columnAlias('roles'));

The query that will be executed by ``$select`` looks somewhat like this

.. code-block:: postgres

    select self.id, self.login, array(
            select (gw_2.valid_from, gw_2.valid_to, gw_1.*)
            from rbac.users_roles as gw_2, rbac.roles as gw_1
            where gw_2.role_id = gw_1.id
                and gw_2.user_id = self.id
        ) as roles
    from rbac.users as self

The ``roles`` field returned from this query is an array of (anonymous) composite type. The downside of using such
a field is that Postgres provides no info on the structure of the composite, it should be specified on PHP side

.. code-block:: php

    $result = $select->getIterator()
        ->setType('roles', [
            '' => [
                'validFrom'   => 'date',
                'validTo'     => 'date',
                'id'          => 'integer',
                'name'        => 'text',
                'description' => 'text',
            ]
        ]);

Note the ``['' => base type]`` alternative way of specifying an array type: usually that is given using ``typename[]``
syntax, but our composite type is anonymous and specified with an array itself. Note also that the names we are giving
for the fields of the type are not required to be equal to original ones. Field count and field order, however,
should be the same!

Now we can use a popular `Valinor package <https://github.com/CuyZ/Valinor>`__ to map the result to the following DTOs

.. code-block:: php

    namespace app\howto\mapping\nested;

    final class User
    {
        public function __construct(
            public readonly int $id,
            public readonly string $login,
            /** @var AssignedRole[] */
            public readonly array $roles,
        ) {
        }
    }

    final class AssignedRole
    {
        public function __construct(
            public readonly int $id,
            public readonly string $name,
            public readonly ?string $description,
            public readonly ?\DateTimeInterface $validFrom,
            public readonly ?\DateTimeInterface $validTo,
        ) {
        }
    }

with pretty much the default config:

.. code-block:: php

    use app\howto\mapping\nested\User;
    use CuyZ\Valinor\MapperBuilder;
    use CuyZ\Valinor\Mapper\Source\Source;

    $users = (new MapperBuilder())
        ->mapper()
        ->map(
            'array<' . User::class . '>',
            Source::iterable($result)
        );
