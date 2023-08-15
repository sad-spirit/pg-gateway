-- Fixture for SetClauseFragmentTest / UpdateTest

create table update_test (
    id integer not null,
    title text default 'A string',
    added timestamp with time zone default now()
);

insert into update_test (id, title) values (1, 'One');
insert into update_test (id, title) values (2, 'Two');
insert into update_test (id, title, added) values (3, 'Many', '2020-01-01');
insert into update_test (id, title) values (4, 'Too many');

create table unconditional (
    id integer not null,
    title text
);

insert into unconditional
values (1, 'First one'),
       (2, 'Second one');
