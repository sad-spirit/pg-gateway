# sad_spirit/pg_gateway

[![Build Status](https://github.com/sad-spirit/pg-gateway/workflows/Continuous%20Integration/badge.svg?branch=master)](https://github.com/sad-spirit/pg-gateway/actions?query=branch%3Amaster+workflow%3A%22Continuous+Integration%22)

[![Static Analysis](https://github.com/sad-spirit/pg-gateway/workflows/Static%20Analysis/badge.svg?branch=master)](https://github.com/sad-spirit/pg-gateway/actions?query=branch%3Amaster+workflow%3A%22Static+Analysis%22)

This is a [Table Data Gateway](https://martinfowler.com/eaaCatalog/tableDataGateway.html) implementation built upon
[pg_wrapper](https://github.com/sad-spirit/pg-wrapper) and [pg_builder](https://github.com/sad-spirit/pg-builder) packages.

Using those packages immediately allows
 * Transparent conversion of PHP types to Postgres types and back;
 * Writing parts of the query as SQL strings while later processing those parts as Nodes in the Abstract Syntax Tree.

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

we can set up default gateways to the above tables
```PHP
use sad_spirit\pg_gateway\{
    TableLocator,
    gateways\CompositePrimaryKeyTableGateway,
    gateways\PrimaryKeyTableGateway
};
use sad_spirit\pg_wrapper\Connection;

$connection = new Connection('...');
$locator    = new TableLocator($connection);

/** @var PrimaryKeyTableGateway $gwUsers */
$gwUsers    = $locator->get('example.users');
/** @var PrimaryKeyTableGateway $gwRoles */
$gwRoles    = $locator->get('example.roles');
/** @var CompositePrimaryKeyTableGateway $gwLink */
$gwLink     = $locator->get('example.users_roles');
```

and use these to perform a non-trivial query

```PHP
$adminRoles = $gwRoles->select([
    $gwRoles->outputColumns()
        ->except(['description'])
        ->replace('/^/', 'role_'),
    $gwRoles->operatorCondition('name', '~*', 'admin')
]);

$activeAdminRoles = $gwLink->select([
    $gwLink->outputColumns()
        ->only(['valid_from', 'valid_to']),
    $gwLink->join($adminRoles)
        ->onForeignKey(),
    $gwLink->sqlCondition("current_date between coalesce(self.valid_from, 'yesterday') and coalesce(self.valid_to, 'tomorrow')")
]);

$activeAdminUsers = $gwUsers->select([
    $gwUsers->outputColumns()
        ->except(['password_hash'])
        ->replace('/^/', 'user_'),
    $gwUsers->join($activeAdminRoles)
        ->onForeignKey()
]);

foreach ($activeAdminUsers as $row) {
    print_r($row);
}

echo $activeAdminUsers->createSelectStatement()->getSql();
```

where the last `echo` will output something similar to
```SQL
select gw_2.id as role_id, gw_2."name" as role_name, gw_1.valid_from, gw_1.valid_to, self.id as user_id,
    self.login as user_login
from example.users as self, example.users_roles as gw_1, example.roles as gw_2
where gw_2."name" ~* $1::"text"
    and gw_1.role_id = gw_2.id
    and current_date between coalesce(gw_1.valid_from, 'yesterday') and coalesce(gw_1.valid_to, 'tomorrow')
    and gw_1.user_id = self.id
```


## Documentation

* [Package overview](./docs/index.md)
* [`TableLocator` class](./docs/locator.md)
* [Working with table metadata](./docs/metadata.md)
* [`TableGateway` interface and its implementations](./docs/gateways.md)

## Requirements

`pg_gateway` requires at least PHP 7.4 with native [pgsql extension](https://php.net/manual/en/book.pgsql.php).

Minimum supported PostgreSQL version is 10.

It is highly recommended to use [PSR-6 compatible](https://www.php-fig.org/psr/psr-6/) cache in production,
both for metadata lookup and for generated queries.
