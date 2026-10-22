# Database engine & driver — setup & configuration

> Scope: picking a database engine and driver for this stack. For the rest
> of the Docker setup (build, deploy, tuning, troubleshooting), see
> `docker.md` (beside this file).

## Pick a database engine

Pick ONE engine and set its row in the "Docker deployment-dependent" block
of `<instance>.env`: `DB_HOST`, `DB_PORT`, `DB_DRIVER`, `DB_CHAR_SET`,
`DB_COLLATION`, `DB_COLLATION_CI` and `DB_LOCALE_CI` (`DB_NAME`/`DB_USER`
there can be any non-empty value). The templates ship PostgreSQL's row. The
block overrides the base `.env.<instance>`, so with a bundled database its
values are the ones that run, empty ones included; an instance on an
external database deletes the block and sets the same keys in the base:

| Engine | `--profile` | `DB_HOST` | `DB_PORT` | `DB_DRIVER` | native equivalent | `DB_CHAR_SET` / `DB_COLLATION` | `DB_COLLATION_CI` / `DB_LOCALE_CI` |
|---|---|---|---|---|---|---|---|
| PostgreSQL 18+ | `postgres` | `postgres` | `5432` | `pdo/pgsql` | `postgre` | `UTF8` / *(leave empty)* | *(leave empty)* / `und-u-ks-level2` |
| MySQL 8.4+ | `mysql` | `mysql` | `3306` | `pdo/mysql` | `mysqli` | `utf8mb4` / `utf8mb4_0900_as_cs` | `utf8mb4_0900_as_ci` / *(leave empty)* |
| MariaDB 10.11+ | `mariadb` | `mariadb` | `3306` | `pdo/mysql` | `mysqli` | `utf8mb4` / `utf8mb4_uca1400_nopad_as_cs` | `utf8mb4_uca1400_nopad_as_ci` / *(leave empty)* |

`DB_CHAR_SET` has no default — set it. The native drivers refuse to connect
without one; the PDO drivers accept an empty value and take whatever the
database itself is configured for, which is fine until the database is not
what you assumed. On MySQL/MariaDB `DB_COLLATION` is the table collation:
CI3 appends it to every `CREATE TABLE` (and `CREATE DATABASE`), so every new
table compares under it. It is not sent on connect, so the connection keeps
the client's default collation, which is case-insensitive on both engines
and decides literal-to-literal comparisons in raw SQL. Leave it empty on
PostgreSQL. `DB_COLLATION_CI` / `DB_LOCALE_CI` serve case-insensitive
columns and searches (below).

The minimums are recommendations, except PostgreSQL 18: plain `LIKE` on a
case-insensitive column errors on 17 and earlier. MySQL 8.4 and MariaDB
10.11 are the supported LTS lines; the collation names above exist from
MySQL 8.0 and MariaDB 10.10. The values were verified on PostgreSQL 18.6,
MySQL 8.4.11 and MariaDB 12.3.3.

### Case sensitivity

New databases compare text exactly — case- and accent-sensitive — on every
engine. The exact values above are linguistic, not binary: they sort like a
dictionary (`alice Alice ALICE angel Ángel bob Bob`), where `utf8mb4_bin`
sorts by code point. MySQL/MariaDB `as_cs` also treats canonical equivalents
as equal (`é` and `e` + U+0301), which PostgreSQL's deterministic default
keeps distinct.

Case-insensitivity is always explicit, in one of two places:

- **A column declared `case_insensitive: true`** in its migration (see the
  mgr-migrations skill) compares case-insensitively in every query — `=`,
  `LIKE`, `GROUP BY`, `DISTINCT`, unique keys. The framework's own
  `user.username`, `user.email` and `login_attempt.login` are declared that
  way. The collation comes from `DB_COLLATION_CI` (MySQL/MariaDB) or
  `DB_LOCALE_CI` (PostgreSQL: the ICU locale of a nondeterministic collation
  the framework creates as `manager_case_insensitive`). An empty key stops
  the migration with an error naming it.
- **A case-insensitive search on an exact column** uses `get_all_like_ci()`
  / `LIKE_CI` (see the mgr-models skill). A CI-declared column is searched
  with plain `like()`; `like_ci()` on one errors on PostgreSQL, where `ILIKE`
  rejects a nondeterministic collation.

The recommended values are case-insensitive and accent-sensitive: `admin`
matches `ADMIN`, `jose` does not match `José`.

**Indexes.** `%x%` never uses an index, and neither does `like_ci()`. A
case-insensitive prefix search (`x%`) seeks only on MySQL/MariaDB, as plain
`LIKE` on a CI-declared column with its own index. On PostgreSQL a
CI-declared column's index serves `=` but no `LIKE`. Admin screens that scan
are fine; a large table that needs fast case-insensitive search needs its own
design.

**Mixing collations.** On MySQL/MariaDB, `=` between two columns of different
non-binary collations fails with ERROR 1267 *"Illegal mix of collations"* —
for example a join from one of your own exact columns to `user.email`.
Declare both columns alike, or add `COLLATE` to one side. PostgreSQL does not
error; the comparison runs under the case-insensitive side. A column compared
with a literal never errors on any engine.

**Choosing other values.** The values are recommendations; the project owns
the choice. Where one language dominates, pick that language's collation and
move the exact and CI values together — MariaDB
`utf8mb4_uca1400_spanish_nopad_as_cs` / `utf8mb4_uca1400_spanish_nopad_as_ci`;
PostgreSQL the database locale and `DB_LOCALE_CI` (`es-u-ks-level2`). MySQL
8.4 has no Spanish `as_ci`, so there only the exact value can move. An
accent-insensitive strength (`und-u-ks-level1`, `…_ai_ci`) works too, but
makes `jose` and `José` the same identity.

**PostgreSQL.** On a case-insensitive (nondeterministic) column:

- These error: regular expressions (`~`, `~*`, `SIMILAR TO`, `regexp_*`,
  `substring(col FROM pattern)`), `ILIKE`, `starts_with()` and `^@`. An
  explicit deterministic collation on the operand works around it
  (`col COLLATE "C" ~ '…'`, an exact match).
- These work case-insensitively: `=`, `IN`, `LIKE` (18+), `strpos()`,
  `replace()` (unlike MySQL's `REPLACE()`, which is case-sensitive),
  `DISTINCT`, `GROUP BY`, `ORDER BY`, unique keys.
- A string foreign key needs the identical collation on both sides.
- Columns bind to `manager_case_insensitive` by OID. After `DB_LOCALE_CI`
  changes, the next case-insensitive field a migration builds fails with a
  "different definition" error, and existing columns keep the old locale.
  There is no in-place change; convert in one transaction (PostgreSQL DDL
  rolls back on failure):

  ```sql
  BEGIN;
  CREATE COLLATION "manager_case_insensitive_new" (provider = icu, locale = '<new locale>', deterministic = false);
  -- string FKs between collated columns: drop, convert both sides, re-add
  ALTER TABLE <t> ALTER COLUMN <c> TYPE <same type> COLLATE "manager_case_insensitive_new";  -- every collated column
  DROP COLLATION "manager_case_insensitive";
  ALTER COLLATION "manager_case_insensitive_new" RENAME TO "manager_case_insensitive";
  COMMIT;
  -- then set DB_LOCALE_CI to the new value
  ```

  A stricter locale can make a foreign key or a former match fail; a looser
  one can merge values a unique key then rejects, so scan for duplicates
  under the new locale first. Each `ALTER … TYPE` takes an ACCESS EXCLUSIVE
  lock and rebuilds the column's indexes; plan a window on large tables.
- An ICU library upgrade (a new image) can change collation versions.
  PostgreSQL then warns, and the expected fix is `REINDEX` on the affected
  indexes plus `ALTER COLLATION … REFRESH VERSION`. Not verified here.
- The bundled PostgreSQL cluster initializes new volumes with an ICU `und`
  default, so its exact default sorts linguistically as well (`docker.md`).

**SQLite** declares case-insensitive columns `COLLATE NOCASE`, which folds
ASCII only (`josé` and `JOSÉ` stay distinct), and its plain `LIKE` is already
ASCII-case-insensitive. **SQL Server** refuses `case_insensitive: true`, and
`like_ci()` falls back to `LOWER()` on both sides; neither is verified, and a
typical SQL Server instance default is case-insensitive, so exact-by-default
does not hold there.

### PDO is the default; the native drivers are the compatibility choice

`DB_DRIVER` defaults to `pdo/pgsql` for a new project, and `pdo/mysql` is
the MySQL/MariaDB equivalent. Both measure at performance parity with their
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
uncomment the `'options' => [PDO::ATTR_STRINGIFY_FETCHES => true]` line in
your own `application/config/database.php`'s `$db['default']` array. Every
column that would otherwise convert comes back a string again, on all three
engines. One value — not type — still differs: a Postgres `Bool` reads
`'1'` where the native driver said `'t'`. Treat the flag as temporary and
delete it once your clients accept native types.

The image ships `pdo_mysql` and `pdo_pgsql`, so nothing needs building for
MySQL, MariaDB or PostgreSQL — set `DB_DRIVER` and go.
`mgr_apply_pdo_dsn()`, included into `application/config/database.php` from
the framework's own `config/database.php` base, builds the `dsn` from the
same `DB_HOST`/`DB_PORT`/`DB_NAME`. Other engines are a different
story: SQLite works over `pdo/sqlite` (`DB_NAME` is the file path), and SQL
Server has a compose profile but no driver in the image — the available
Alpine subdriver has unresolved problems severe enough that shipping it
would be misleading.

### Adding a connection group

A second database is a second group in `application/config/database.php`.
Copy `$db['default']`, name the group for what it holds (`$db['finance']`),
and give it its own env prefix (`DB_FINANCE_HOST`, `DB_FINANCE_DRIVER`, …,
with `DB_FINANCE_PASS` in `.env.priv`). Keep `mgr_env_required()` on its
user and database, and take its charset and collation keys from the engine
table above for that group's engine. A model selects it with
`$connection_name = 'finance'`, and its migrations go under
`migrations/finance/`.

### Cross-engine quirks worth knowing before you switch engines

A few behaviors differ by engine regardless of which driver (native or PDO)
you run — worth knowing before assuming a schema or query is portable
between them.

- **The session time zone needs `INTERVAL` on Postgres.** A bare
  `SET TIME ZONE '<offset>'` there parses as a POSIX-style spec, which
  inverts the sign versus the ISO offset the framework passes — so the
  handle cache issues `SET TIME ZONE INTERVAL '<offset>' HOUR TO MINUTE` on
  Postgres instead, and the plain `SET SESSION time_zone = '<offset>'`
  form on MySQL/MariaDB. SQLite and SQL Server have no session time zone
  concept, so nothing is sent there.
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
- **`dbforge->add_key()` and the builder's index helpers derive one name.**
  The framework's forge subclass names both, per engine: PostgreSQL
  `{table}_{cols}_idx` plain and `{table}_{cols}_key` unique, SQL Server
  `{table}_{cols}` and `{table}_{cols}_key`, MySQL/MariaDB `{cols}` for both.
  An index created before that, or by hand, can carry a name the builder no
  longer derives, so `drop_index()`/`drop_foreign_key()` check the engine's
  own catalog and return `bool` (found-and-dropped vs. not found); pass the
  real name via the `name` parameter for anything this builder didn't
  create — the mgr-migrations skill covers the call shape.
- **`add_index()`/`add_foreign_key()` are idempotent no-ops on a re-run,
  the same as their drop counterparts.** A raw `CREATE INDEX`/
  `ALTER TABLE ADD {INDEX|CONSTRAINT}` throws a duplicate-name error the
  second time a migration runs against a table that already has it — the
  failure a consuming project hit directly. Both now check the same
  catalog `drop_index()`/`drop_foreign_key()` already used and skip
  silently on a name match — except `add_index(unique: true)` over a plain
  index of that name, which throws — returning `bool` (`true` = created,
  `false` = already existed) instead of `void`. `add_primary_key()` is the one
  exception: see below.
- **A primary key is only named on some engines, so both
  `add_primary_key()`/`drop_primary_key()` ask the catalog rather than
  guessing.** MySQL/MariaDB rename every primary key to the literal
  `PRIMARY`; PostgreSQL and SQL Server need the constraint's real name,
  which is whatever created it — the framework's `pk_{table}`, or the
  engine's own auto-name for a key it created itself (`{table}_pkey` on
  Postgres, `PK__table__<hash>` on SQL Server). Both read
  `information_schema.table_constraints` for the live name (a shared
  `_existing_pk_name()` helper), so they work on tables the framework
  didn't create. `drop_primary_key()` returns `bool` — `false`, not a
  throw, if the table has no primary key to drop, the same contract as
  `drop_index()`/`drop_foreign_key()`. `add_primary_key()` does NOT follow
  the no-op convention above, though: a table has only one primary-key
  slot, so an existing PK on different columns means the caller's
  drop-first intent wasn't met, not a harmless retry — it throws
  `RuntimeException` instead. **SQLite throws** on both, same reason as
  foreign keys.
- **An AUTO_INCREMENT column must stay keyed on MySQL/MariaDB — no other
  engine cares.** Moving the primary key off such a column fails there with
  *"there can be only one auto column and it must be defined as a key"*
  unless the column gets an index of its own first. PostgreSQL and SQL
  Server attach the sequence/IDENTITY to the column independently of any
  key, so nothing is needed. The same rule is why `add_column()` cannot add
  an AUTO_INCREMENT column to an existing table on MySQL/MariaDB at all,
  while an identity (Postgres) and `IDENTITY` (SQL Server) do it in one
  statement — the mgr-migrations skill carries the portable recipe for both
  directions.
- **Numbering existing rows is done by the framework on every engine.**
  When `add_auto_increment()` applies the attribute, it numbers every row
  holding `0` or `NULL` past the highest existing value, then positions the
  counter. MySQL/MariaDB would otherwise number those rows in scan order
  from a running counter, so a `0` ahead of a real id can take its value,
  and PostgreSQL would leave every row at `0` and fail the next unique key.
  Rows already holding a real value are untouched.
- **A cross-family `modify_column()` type change fails on PostgreSQL —
  native driver and `pdo/pgsql` alike.** A bare `ALTER COLUMN ... TYPE ...`
  has no automatic cast between families (string to numeric is the common
  case) and fails with *"column ... cannot be cast automatically"*.
  `modify_column_cast()` supplies the missing `USING` clause, and applies
  the column's remaining attributes itself. Its cast targets the
  unconstrained type, so a value the new type can't hold — an overlong
  string, an out-of-range number — still fails the migration rather than
  being truncated into place. Values that don't parse as the new type at all
  (text labels becoming numbers) are normalized by an `UPDATE` before the
  type change, which is what every engine needs; the mgr-migrations skill
  carries both shapes.
- **Writing `CURRENT_TIMESTAMP`/`GETDATE()` into a `DATETIMEOFFSET` column
  silently mislabels local server time as UTC, on SQL Server only.**
  `Timestamp` maps to `DATETIMEOFFSET` there; both of those functions return
  a plain `datetime` with no offset, and SQL Server's implicit conversion to
  `DATETIMEOFFSET` stamps the missing offset as `+00:00` rather than the
  server's real one. `SYSDATETIMEOFFSET()` carries the actual offset and is
  the correct function for any raw SQL writing "now" into a `Timestamp`
  column there. Postgres/MySQL/MariaDB/SQLite are unaffected — none of them
  has this offset-vs-naive distinction for the value itself.
- **Only PostgreSQL rolls back DDL.** A multi-step migration brackets its
  steps with `start_ddl_transaction()` / `end_ddl_transaction()`: a
  transaction on PostgreSQL, a no-op elsewhere, and the runner rolls an open
  transaction back when a migration throws. MySQL/MariaDB commit each DDL
  statement implicitly, so a failure there leaves the earlier steps applied;
  steps are written idempotent and `migrate` is simply re-run after the
  cause is fixed.
- **`unique: true` inside `modify_column()` becomes each engine's own unique
  object, once.** PostgreSQL gets a unique constraint `{table}_{column}_key`,
  the object `add_column()`/`create_table()` make; MySQL/MariaDB get the
  `MODIFY` followed by a separate `ADD UNIQUE INDEX`, so a duplicate row fails
  that second statement after the column change has already run, and nothing
  rolls it back; SQL Server's `CREATE UNIQUE INDEX` after the `ALTER COLUMN`
  is unverified. A repeat call adds nothing, and a plain index already under
  the name throws. `add_index(unique: true)` makes a unique index on every
  engine — on PostgreSQL a bare index, not a constraint; `drop_index()`
  removes either.
- **A column restated in `modify_column()` is only as NOT NULL as the call
  says.** MySQL/MariaDB `MODIFY` redefines the whole column, so a restate
  that leaves nullability out makes it nullable; PostgreSQL alters the pieces
  separately and keeps it. The builder states nullability on every field
  (NOT NULL unless `nullable: true`), so all engines agree. Tightening a
  column on MySQL/MariaDB with strict mode off does not fail on existing
  NULLs — it rewrites them to `''`/`0` — so check first with
  `assert_no_nulls()`.
- **CI3's `table_exists()` can report a rolled-back table as present.** It
  reads a cached table list that `create_table()` appends to and a rollback
  does not clear; inside a PostgreSQL DDL transaction that failed, query the
  catalog instead.
