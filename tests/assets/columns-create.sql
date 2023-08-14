-- Fixture for ColumnsTest

create table public.cols (
    id integer not null,
    name text
);

create schema cols_test;

create domain cols_test.shorttext as text check ( length(value) < 10 );

create sequence cols_test.notatable;

create table cols_test.zerocolumns();

create table cols_test.simple(
    id integer not null,
    name text
);

create table cols_test.hasdropped(
    foo text,
    bar text
);

alter table cols_test.hasdropped
    drop column bar;

create table cols_test.hasdomain(
    foo cols_test.shorttext
);
