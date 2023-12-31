-- Fixture for PrimaryKeyTest

create table public.haskey (
    id  integer not null,
    name text,

    constraint haskey_pkey primary key (id)
);

create schema pkey_test;

create sequence pkey_test.explicit_seq;

create table pkey_test.nokey (
    name text,
    surname text
);

create table pkey_test.explicit (
    e_id integer default nextval('pkey_test.explicit_seq'::regclass),
    e_name text,

    constraint explicit_pkey primary key (e_id)
);

create table pkey_test.serial (
    s_id serial,
    s_name text,

    constraint serial_pkey primary key (s_id)
);

create table pkey_test.standard (
    i_id integer not null generated by default as identity,
    i_name text,

    constraint standard_pkey primary key (i_id)
);
