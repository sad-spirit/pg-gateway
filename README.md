# sad_spirit/pg_gateway

[![Continuous Integration](https://github.com/sad-spirit/pg-gateway/actions/workflows/continuous-integration.yml/badge.svg?branch=master)](https://github.com/sad-spirit/pg-gateway/actions/workflows/continuous-integration.yml)

[![Static Analysis](https://github.com/sad-spirit/pg-gateway/actions/workflows/static-analysis.yml/badge.svg?branch=master)](https://github.com/sad-spirit/pg-gateway/actions/workflows/static-analysis.yml)

This is a [Table Data Gateway](https://martinfowler.com/eaaCatalog/tableDataGateway.html) implementation built upon
[pg_wrapper](https://github.com/sad-spirit/pg-wrapper) and [pg_builder](https://github.com/sad-spirit/pg-builder) packages.

Using those packages immediately allows
 * Transparent conversion of PHP types to Postgres types and back;
 * Writing parts of the query as SQL strings while later processing those parts as Nodes in the Abstract Syntax Tree.

## Installation

Require the package with composer:
```
composer require sad_spirit/pg_gateway
```

## Design goals

 * Code generation is not necessary, default gateway implementations are useful as-is.
 * Gateways are aware of the table metadata: columns, primary key, foreign keys.
 * It is possible to cache the generated SQL, skipping the whole parsing/building process.
 * API encourages building parametrized queries.
 * Queries built by several Gateways can be combined via joins / `EXISTS()` / etc.

## Usage example

Assuming the following database schema
```SQL

create schema example;

create table example.users (
    id integer not null generated by default as identity,
    login text not null,
    password_hash text not null,
    
    constraint users_pkey primary key (id)
);

create table example.roles (
    id integer not null generated by default as identity,
    name text not null,
    description text,
    
    constraint roles_pkey primary key (id)
);

create table example.users_roles (
    user_id integer not null,
    role_id integer not null,
    valid_from date,
    valid_to date,
    
    constraint users_roles_pkey primary key (user_id, role_id),
    constraint roles_users_fkey foreign key (user_id)
        references example.users (id)
        on delete cascade on update restrict,
    constraint users_roles_fkey foreign key (role_id)
        references example.roles (id)
        on delete cascade on update restrict
);
```

we can use default gateways and default builders to perform a non-trivial query to the above tables

```PHP
use sad_spirit\pg_gateway\{
    TableLocator,
    builders\FluentBuilder
};
use sad_spirit\pg_wrapper\Connection;

$connection = new Connection('...');
$locator    = new TableLocator($connection);

$adminRoles = $locator->createGateway('example.roles')
    ->select(fn(FluentBuilder $builder) => $builder
        ->operatorCondition('name', '~*', 'admin')
        ->outputColumns()
            ->except(['description'])
            ->replace('/^/', 'role_'));

$activeAdminRoles = $locator->createGateway('example.users_roles')
    ->select(fn(FluentBuilder $builder) => $builder
        ->sqlCondition("current_date between coalesce(self.valid_from, 'yesterday') and coalesce(self.valid_to, 'tomorrow')")
        ->join($adminRoles)
            ->onForeignKey()
        ->outputColumns()
            ->only(['valid_from', 'valid_to']));

$activeAdminUsers = $locator->createGateway('example.users')
    ->select(fn(FluentBuilder $builder) => $builder
        ->outputColumns()
            ->except(['password_hash'])
            ->replace('/^/', 'user_')
        ->join($activeAdminRoles)
            ->onForeignKey()
        ->orderBy('user_login, role_name')
        ->limit(5));

// Let's assume we want to output that list with pagination
echo "Total users with active admin roles: " . $activeAdminUsers->executeCount() . "\n\n";

foreach ($activeAdminUsers as $row) {
    print_r($row);
}

echo $activeAdminUsers->createSelectCountStatement()->getSql() . ";\n\n";
echo $activeAdminUsers->createSelectStatement()->getSql() . ';';
```

where the last two `echo` statements will output something similar to
```SQL
select count(self.*)
from example.users as self, example.users_roles as gw_1, example.roles as gw_2
where gw_2."name" ~* $1::"text"
    and gw_1.role_id = gw_2.id
    and current_date between coalesce(gw_1.valid_from, 'yesterday') and coalesce(gw_1.valid_to, 'tomorrow')
    and gw_1.user_id = self.id;

select gw_2.id as role_id, gw_2."name" as role_name, gw_1.valid_from, gw_1.valid_to, self.id as user_id,
    self.login as user_login
from example.users as self, example.users_roles as gw_1, example.roles as gw_2
where gw_2."name" ~* $1::"text"
    and gw_1.role_id = gw_2.id
    and current_date between coalesce(gw_1.valid_from, 'yesterday') and coalesce(gw_1.valid_to, 'tomorrow')
    and gw_1.user_id = self.id
order by user_login, role_name
limit $2;
```


## Documentation

* [Package overview](./docs/index.md)
* [`TableLocator` class](./docs/locator.md)
* [Working with table metadata](./docs/metadata.md)
* [`TableGateway` interface and its implementations](./docs/gateways.md)
* [Fluent Builders](./docs/builders-methods.md)
* [`FragmentBuilder` implementations](./docs/builders-classes.md)

* [Query fragments: base interfaces, parameters, aliases](./docs/fragments-base.md)
* [`Fragment` implementations](./docs/fragments-implementations.md)
* [`Condition` classes](./docs/conditions.md)

## Requirements

`pg_gateway` requires at least PHP 7.4 with native [pgsql extension](https://php.net/manual/en/book.pgsql.php).

Minimum supported PostgreSQL version is 10.

It is highly recommended to use [PSR-6 compatible](https://www.php-fig.org/psr/psr-6/) cache in production,
both for metadata lookup and for generated queries.
