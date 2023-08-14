# sad_spirit/pg_gateway

[![Build Status](https://github.com/sad-spirit/pg-gateway/workflows/Continuous%20Integration/badge.svg?branch=master)](https://github.com/sad-spirit/pg-gateway/actions?query=branch%3Amaster+workflow%3A%22Continuous+Integration%22)

[![Static Analysis](https://github.com/sad-spirit/pg-gateway/workflows/Static%20Analysis/badge.svg?branch=master)](https://github.com/sad-spirit/pg-gateway/actions?query=branch%3Amaster+workflow%3A%22Static+Analysis%22)

This is a [Table Data Gateway](https://martinfowler.com/eaaCatalog/tableDataGateway.html) implementation built upon
[pg_wrapper](https://github.com/sad-spirit/pg-wrapper) and [pg_builder](https://github.com/sad-spirit/pg-builder) packages.

Using those packages immediately allows
 * Transparent conversion of PHP types to Postgres types and back;
 * Writing parts of the query as SQL strings while later processing those parts as Nodes in the Abstract Syntax Tree.

## Design goals

 * Code generation should not be necessary, default gateway implementations should be useful as-is.
 * Gateways should be aware of the table properties: columns, primary key, foreign keys.
 * It should be possible to cache the generated SQL, skipping the whole parsing/building process.
 * Therefore, API should encourage building parametrized queries.
 * It should be possible to combine queries built by several Gateways via joins / `EXISTS()`

## Usage example

TBD

## Documentation

TBD

