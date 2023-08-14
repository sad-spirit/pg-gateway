-- Fixture for composite primary key tests

create schema if not exists pkey_test;

create table pkey_test.composite (
    e_id integer,
    s_id integer,
    i_id integer,

    constraint composite_pkey primary key (e_id, s_id, i_id)
);

