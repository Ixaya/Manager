# Database engine & driver — setup & configuration

> Scope: picking a database engine and driver for this stack. For the rest
> of the Docker setup (build, deploy, tuning, troubleshooting), see
> `docker.md` (beside this file).

## Pick a database engine

Pick ONE engine and edit `<instance>.env` to match — the base "Package"
section's `DB_DRIVER`/`DB_CHAR_SET`/`DB_COLLATION`, and the "Docker
deployment-dependent" block's `DB_HOST`/`DB_PORT` (`DB_NAME`/`DB_USER` there
can be any non-empty value):

| Engine | `--profile` | `DB_HOST` | `DB_PORT` | `DB_DRIVER` | native equivalent | `DB_CHAR_SET` / `DB_COLLATION` |
|---|---|---|---|---|---|---|
| PostgreSQL | `postgres` | `postgres` | `5432` | `pdo/pgsql` | `postgre` | `UTF8` / *(leave empty)* |
| MySQL 8 | `mysql` | `mysql` | `3306` | `pdo/mysql` | `mysqli` | `utf8mb4` / `utf8mb4_0900_ai_ci` |
| MariaDB | `mariadb` | `mariadb` | `3306` | `pdo/mysql` | `mysqli` | `utf8mb4` / `utf8mb4_uca1400_ai_ci` |

`DB_CHAR_SET` has no default — set it. The native drivers refuse to connect
without one; the PDO drivers accept an empty value and take whatever the
database itself is configured for, which is fine until the database is not
what you assumed. `DB_COLLATION` is only read when creating a database, so
it can stay empty.

### PDO is the default; the native drivers are the compatibility choice

`DB_DRIVER` defaults to `pdo/mysql` for a new project, and `pdo/pgsql` is
the Postgres equivalent. Both measure at performance parity with their
native counterpart through this framework's own Model-layer benchmark —
the already-tuned defaults in `mgr_apply_pdo_dsn()` are what get you there,
and you don't need to change anything for it.

`mysqli` and `postgre` remain fully supported. Two reasons to stay on them —
and holding your API's string contract is not one of them, since the
compatibility option below does that on PDO:

- **Your host doesn't have the PDO subdriver.** Some servers ship only the
  native extension. That is a deployment fact, not a preference.
- **You want the exact behavior you already run.** Native and PDO are two
  client stacks, each pre-initializing its own defaults, and this framework
  equalizes the differences it has found — `PDO::ATTR_EMULATE_PREPARES` on
  `pgsql` was one, and it surfaced from benchmarking, not from reading
  documentation. Others may exist that nobody has hit yet. If you need
  certainty rather than a list of known differences, the driver you have
  been running in production is the one that gives it.

Doing nothing is also safe: `composer update` never rewrites your `.env` or
`application/config/database.php`, so a project keeps whatever `DB_DRIVER`
it already has until you change it deliberately.

The two paths differ in kind, not in speed:

- **Fetch types.** Native drivers stringify every column. Under PDO — both
  `pdo/mysql` and `pdo/pgsql` — `TinyInt`, `SmallInt`, `Int`, `BigInt`, and
  the PK `id` column come back as native `int`; `Float`/`Double` come back
  as native `float`. `Decimal` stays a string on every driver (avoids
  precision loss), as do `Char`, `VarChar`, `Text`, `Date`, `DateTime`,
  `Timestamp`, `Json`, and `Uuid` — CI has no better native PHP
  representation for them. `Bool` is the one outlier with real divergence,
  covered separately below. Switching an existing project changes the JSON
  type your API emits for the converting columns (`"id":11` vs
  `"id":"11"`) — decide whether that's a fix or a breaking change for your
  clients before switching a project already in production.
- **`Bool` has four representations across engine × driver** — native
  Postgres returns the string `'t'`/`'f'` (and `'f'` is truthy in PHP, so a
  plain `if ($row['flag'])` is wrong there on a false value), native
  MySQL/MariaDB return `'1'`/`'0'` strings, `pdo/pgsql` returns real
  `bool`, `pdo/mysql` returns `int`. This is why the migrations skill
  directs `SmallInt` over `Bool` for `0`/`1` flags regardless of driver.
- **A primary key returned by a write is typed the same way.** `insert()`,
  `upsert()` and friends re-derive the new id through a real query, so it
  matches what `get()` would return for that column — `int` under PDO,
  `string` under a native driver. Don't compare one against a literal of
  the other type.

**Moving an existing project onto PDO without changing its API contract:**
uncomment the `PDO::ATTR_STRINGIFY_FETCHES` line in
`application/config/database.php`'s `mgr_apply_pdo_dsn()`. Every column
that would otherwise convert comes back a string again, on all three
engines. One value — not type — still differs: a Postgres `Bool` reads
`'1'` where the native driver said `'t'`. Treat the flag as temporary and
delete it once your clients accept native types.

The image ships `pdo_mysql` and `pdo_pgsql`, so nothing needs building for
MySQL, MariaDB or PostgreSQL — set `DB_DRIVER` and go.
`mgr_apply_pdo_dsn()` in `application/config/database.php` builds the `dsn`
from the same `DB_HOST`/`DB_PORT`/`DB_NAME`. Other engines are a different
story: SQLite works over `pdo/sqlite` (`DB_NAME` is the file path), and SQL
Server has a compose profile but no driver in the image — the available
Alpine subdriver has unresolved problems severe enough that shipping it
would be misleading.

### Cross-engine quirks worth knowing before you switch engines

A few behaviors differ by engine regardless of which driver (native or PDO)
you run — worth knowing before assuming a schema or query is portable
between them.

- **`set_database_time_zone()` needs `INTERVAL` on Postgres.** A bare
  `SET TIME ZONE '<offset>'` there parses as a POSIX-style spec, which
  inverts the sign versus the ISO offset the framework passes — so the
  model issues `SET TIME ZONE INTERVAL '<offset>' HOUR TO MINUTE` on
  Postgres instead, and the plain `SET SESSION time_zone = '<offset>'`
  form on MySQL/MariaDB. SQLite and SQL Server have no session time zone
  concept, so the call is a no-op there.
- **`Char` is blank-padded on PostgreSQL, not on MySQL/MariaDB.** A
  `Char(8)` column holding `'abcd'` reads back as `'abcd    '` on Postgres
  but `'abcd'` on MySQL — identical under native and PDO alike, so this is
  a storage-engine behavior, not a driver choice.
- **`Json` storage normalizes differently on all three engines.** MySQL
  rewrites the text it's given (`'{"a": 1}'`); MariaDB stores it verbatim
  (`'{"a":1}'`). PostgreSQL doesn't store text at all —
  `MGR_Migration_builder` maps `Json` to `JSONB` there (binary, indexed) —
  and JSONB goes further than either text engine: an out-of-order object
  with a duplicate key comes back alphabetized and deduped, keeping the
  last value (verified live: `'{"b": 2, "a": 1, "a": 3}'` reads back as
  `'{"a": 3, "b": 2}'`). None of this is round-trip safe if a client cares
  about key order, formatting, or duplicate keys.
- **A key-prefix-length index (`add_index()`'s `prefix_lengths`) translates
  per engine, not literally.** MySQL/MariaDB emit the prefix directly in the
  column list (InnoDB's key-size limit requires one on `TEXT`/`MEDIUMTEXT`
  columns — a bare index on one is rejected outright there). PostgreSQL has
  no prefix syntax, so the framework builds an equivalent expression index
  (`left(col, n)`) instead, matching the same first-N-characters semantics.
  SQLite ignores the parameter — it enforces no comparable key-size limit.
  **SQL Server throws** — `NVARCHAR(MAX)` (what `Text`/`MediumText`/
  `LongText` map to there) cannot be an index key column at all, prefix or
  not; a persisted computed column is the only real fix, and outside what
  this helper builds.
- **Foreign keys (`add_foreign_key()`/`drop_foreign_key()`) are always a
  post-create `ALTER TABLE`, never declared at `CREATE TABLE` time.** Works
  as a straightforward `ADD`/`DROP CONSTRAINT` on MySQL/MariaDB/PostgreSQL/
  SQL Server (`RESTRICT` maps to SQL Server's `NO ACTION`, its nearest
  equivalent — SQL Server has no `RESTRICT` keyword). **SQLite throws on
  both** — it has no `ALTER TABLE ADD`/`DROP CONSTRAINT` for foreign keys at
  all; a FK there can only be declared inline in the original
  `CREATE TABLE` statement, and retrofitting one onto an existing table
  needs a full table-recreate, which these helpers don't build.
- **A primary key is only named on some engines, so
  `drop_primary_key()` asks the catalog rather than guessing.**
  MySQL/MariaDB rename every primary key to the literal `PRIMARY` and drop
  it by keyword; PostgreSQL and SQL Server need the constraint's real name,
  which is whatever created it — the framework's `pk_{table}`, or the
  engine's own auto-name for a key it created itself (`{table}_pkey` on
  Postgres, `PK__table__<hash>` on SQL Server). The helper reads
  `information_schema.table_constraints` for the live name, so it works on
  tables the framework didn't create. **SQLite throws**, same reason as
  foreign keys.
- **An AUTO_INCREMENT column must stay keyed on MySQL/MariaDB — no other
  engine cares.** Moving the primary key off such a column fails there with
  *"there can be only one auto column and it must be defined as a key"*
  unless the column gets an index of its own first. PostgreSQL and SQL
  Server attach the sequence/IDENTITY to the column independently of any
  key, so nothing is needed. The same rule is why `add_column()` cannot add
  an AUTO_INCREMENT column to an existing table on MySQL/MariaDB at all,
  while `serial` (Postgres) and `IDENTITY` (SQL Server) do it in one
  statement — the mgr-migrations skill carries the portable recipe for both
  directions.
- **Numbering existing rows is MySQL behavior the framework homologates.**
  When `add_auto_increment()` applies the attribute, MySQL/MariaDB
  renumber every row holding `0` or `NULL` and position the counter past the
  highest surviving value. PostgreSQL does neither on its own, so the
  framework issues the equivalent `UPDATE` and `setval()` explicitly —
  without them the same migration would leave every Postgres row at `0` and
  fail the next unique key. Rows already holding a real value are untouched
  on both.
