-- Fixture for ReferencesTest

create table public.employees (
    id   integer not null,
    name text not null,

    constraint employees_pkey primary key (id)
);

create schema fkey_test;

create table fkey_test.documents (
    id          integer not null,
    employee_id integer not null,
    boss_id     integer,
    parent_id   integer,
    contents    text not null,

    constraint documents_pkey primary key (id),
    constraint documents_author_fkey foreign key (employee_id)
        references public.employees (id),
    constraint documents_approval_fkey foreign key (boss_id)
        references public.employees (id),
    constraint documents_hierarchy_fkey foreign key (parent_id)
        references fkey_test.documents (id)
);

create table fkey_test.documents_tags (
    doc_id integer not null references fkey_test.documents (id),
    name text not null
);

create table fkey_test.documents_comments (
    doc_id integer not null,
    contents text not null,

    constraint documents_comments_fkey foreign key (doc_id)
        references fkey_test.documents (id)
);
