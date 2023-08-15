-- Fixture for DeleteTest

create table victim (
    id integer not null
);

insert into victim
values (1),
       (2),
       (3),
       (10);

create table foo (
    id      integer     not null,
    name    text        not null,
    constraint foo_pkey primary key (id)
);

insert into foo values (1, 'one');
insert into foo values (2, 'two');
insert into foo values (3, 'many');


create table bar (
    id      integer     not null,
    foo_id  integer     null,
    name    text        not null,
    constraint bar_pkey primary key (id),
    constraint foo_fkey foreign key (foo_id)
        references foo (id)
        on delete restrict
);

insert into bar values (1, null, 'some stuff');
insert into bar values (2, 2, 'a pair of something');
insert into bar values (3, 2, 'a third one');
