-- Fixture for InsertTest

create table insert_test (
    id integer not null generated by default as identity,
    title text default 'Some default title',
    added timestamp with time zone default now()
);

create table source_test (
    id integer,
    title text,

    constraint source_test_pkey primary key (id)
);

insert into source_test values (-1, 'Minus first title');
insert into source_test values (-2, 'Minus second title');