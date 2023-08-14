-- Cleanup after ReferencesTest

drop schema if exists fkey_test cascade;

drop table if exists public.employees cascade;
