# Migration builder and Forge: one rule per engine for names, types and column changes

The framework's Forge subclasses now own the per-engine DDL rules that the
migration builder used to compensate for: index and constraint names,
`unsigned` widening, `auto_increment`, the `USING` cast and `unique` on a
column change. Every supported driver has a subclass, the builder asks the
Forge for every name and catalog lookup, and a raw `dbforge` call and the
builder now produce the same objects under the same names.

## The problem

The builder had been written around Forge defects, while raw `dbforge`
callers, `add_key()` included, still got the vendor's output. Read from the
code and the vendored CI 3.1.23 forges, then observed on PostgreSQL 18.6,
MariaDB 12.3.3 and MySQL 8.4.11:

- **`unsigned` was a no-op on PostgreSQL and SQL Server.** The vendor's
  string-keyed `$_unsigned` branch assigns the type it already had. The
  builder widened its own `field()` columns; a raw `unsigned` `INT` stayed
  `integer`.
- **A type change across families failed on PostgreSQL**: the vendor emits
  `ALTER COLUMN … TYPE` with no `USING`. `modify_column_cast()` ran its own raw
  `ALTER` to get around it.
- **`auto_increment` on a column change emitted `TYPE SERIAL`**, which
  PostgreSQL rejects (`type "serial" does not exist`), after the earlier
  fields' statements in the same call had already run.
- **`auto_increment` on CREATE/ADD gave `SERIAL` to every type but
  `NUMERIC`.** Every `field_id()` primary key on PostgreSQL was an `integer`
  with a 2,147,483,647 ceiling, below MySQL's `int unsigned`.
- **`unique` on a column change** added a second unique index (`email_2`) on
  every repeat call on MySQL/MariaDB, emitted invalid SQL on SQL Server, and on
  PostgreSQL made a bare unique index under the name PostgreSQL gives the
  unique constraint that `add_column(unique)` creates.
- **`add_key()` and `add_index()` named one index differently** on PostgreSQL
  (`{t}_{c}` against `{t}_{c}_key`), so `add_index()` over an `add_key()` index
  created a duplicate and a derived `drop_index()` missed it.
- **A plain and a unique index over the same columns derived one name.**
  `add_index(unique)` over a plain index returned `false` and no unique
  existed; a duplicate insert was accepted.
- **`drop_index()` returned `true` when PostgreSQL refused the drop** of an
  index backing a unique constraint.
- **The builder's DDL ignored failed statements.** With `db_debug` off,
  `add_index()`, the key helpers, `add_auto_increment()` and the timestamp
  helper returned `true` after the engine rejected the statement.
- **PostgreSQL index names were unquoted**, so a mixed-case name folded to
  lowercase and the builder's exact-case lookup missed it.
- **Past 63 characters the builder's names differed from PostgreSQL's own**
  (its hash cut against the engine's truncation), for indexes, constraints and
  `SERIAL` sequences. The cut existed in two copies kept in step by comment.
- **MySQL rejected `add_key()` past 64 characters** (`ERROR 1059`): the
  vendor's inline `KEY` name is never cut.

## What was built

- **Forge layout** (`system/core/MGR/Forge/`): one common trait, three family
  traits (PostgreSQL, MySQL/MariaDB, SQL Server) and a thin subclass for each
  of the seven supported drivers. Wiring and what each trait owns:
  `framework/docs/architecture/framework-wiring.md`.
- **Names.** One public `index_name(table, columns, unique)` per family, used by
  the Forge's own `add_key()` and by the builder. PostgreSQL derives the names
  the engine gives an unnamed index and a column's unique constraint, in the
  exact case given and cut the way the engine cuts them; every PostgreSQL
  statement that names an index or constraint quotes it. The MySQL family also
  names its inline `KEY` through it. `truncate_identifier()` holds the one copy
  of the hash cut, and the builder uses it for FK and PK names.
- **Catalog lookup.** `index_uniqueness(table, name)` per family (`null` absent,
  `true` unique, `false` plain), read by the builder's `_index_exists()`,
  `add_index()` and the Forge's column-change path.
- **Types.** The PostgreSQL and SQL Server families widen `unsigned` integers
  (table under "Final state") and the builder passes `unsigned` through.
  PostgreSQL `auto_increment` is `BIGSERIAL` for `BIGINT`, `INT8` and
  `NUMERIC`, `SERIAL` otherwise.
- **Column changes (`modify_column()`).**
  - A field with `cast => true` gets `USING "col"::BASE` on PostgreSQL;
    `modify_column_cast()` is now that one call.
  - `auto_increment` on a PostgreSQL column change throws before any statement,
    pointing at `add_auto_increment()`.
  - A field's `unique` is honored on every engine: the inline `UNIQUE` is
    removed from the vendor statement, and one guarded unique statement is
    appended unless a unique already holds the derived name. A plain index
    under that name throws, and so does a rename over a column whose old name
    still holds a unique.
- **Builder.** `drop_index()` gains a trailing `bool $unique = false` and drops
  a PostgreSQL unique constraint as a constraint. `add_index(unique)` over a
  plain index of the same name throws, and a plain request over an existing
  unique returns `false`. Every load-bearing statement it runs, and
  `modify_column_cast()`'s `dbforge` call, throws `RuntimeException` on
  failure through `_ddl()`. The
  `add_auto_increment()` regclass literals carry the quoted identifier.
- **Package migrations.** `ion_auth_v3` and `ion_auth_v4` `down()` drop their
  uniques with `unique: true`; `ion_auth_v4` declares the `username` and
  `email` uniques on its fields instead of two `add_index()` calls.
- **Tests** (`sample/tests/unit/migrations/`): `UnsignedWideningTest`,
  `ModifyColumnCastTest`, `UniqueConstraintTest` and `ModifyColumnUniqueTest`
  are new; `CaseInsensitiveColumnsTest` and `MigrationBuilderHarness` follow
  the changes.

## Final state

Fresh installs. Deployed schemas keep the names and types they were built
with. SQL Server is derived from the code and has never run.

| | PostgreSQL | MySQL / MariaDB | SQL Server |
|---|---|---|---|
| Plain index name | `{t}_{cols}_idx` | `{cols}` | `{t}_{cols}` |
| Unique name | `{t}_{cols}_key` | `{cols}` | `{t}_{cols}_key` |
| Past the limit | the engine's own cut, 63 bytes | hash cut, 64 | hash cut, 128 |
| Field `unique` on CREATE/ADD | constraint, named as derived | unique index | inline constraint, engine-random name |
| Field `unique` on CHANGE | `ADD CONSTRAINT` | `MODIFY`, then `ADD UNIQUE INDEX` | `ALTER COLUMN`, then `CREATE UNIQUE INDEX` |
| `add_index(unique)` | bare unique index | unique index | unique index |
| `unsigned` SmallInt / Int / BigInt | `INTEGER` / `BIGINT` / `NUMERIC(20,0)` | native `UNSIGNED` | `INT` / `BIGINT` / `DECIMAL(20,0)` |
| `unsigned` TinyInt, Float, Double, Decimal | as signed | native `UNSIGNED` | as signed |
| `auto_increment` on CREATE/ADD | `SERIAL`, `BIGSERIAL` for 8-byte | `AUTO_INCREMENT` | `IDENTITY(1,1)` on an `int` type |
| `auto_increment` on CHANGE | throws; `add_auto_increment()` | `AUTO_INCREMENT` | vendor, unchanged |

`field_id()` primary keys are `bigint` on PostgreSQL, with a `bigint` owned
sequence, and match their `Int` unsigned foreign-key columns.

Superseded for PostgreSQL `auto_increment`: it is later made an identity on
CREATE, ADD and CHANGE, so the `SERIAL`/`BIGSERIAL` and "throws" statements
above no longer hold (see decisions.md, "PostgreSQL `auto_increment` as an
identity").

## Open work

Parked as proposals, by title:

- "SQL Server Forge family: first live run" — everything SQL Server in this
  record, including the two known defects a live run has to confirm.
- "SQLite: drop it from the support list or repair it" — the SQLite drivers
  got no subclass, so the builder's `add_index()` and `drop_index()` fail there.
- "`modify_column()` with a `default` emits invalid SQL on SQL Server".
- "Migration builder DDL and Forge-derived names diverge under `dbprefix`" —
  every result here was produced with an empty `dbprefix`.

Where it lives for a reader: `system/docs/upgrading/next.md` (upgrade path);
the `mgr-migrations` skill and its key-surgery reference (the API);
`framework/docs/architecture/framework-wiring.md` (the Forge layout). Choices
and their reasons: `decisions.md`. Measurement and validation: `review.md`.
