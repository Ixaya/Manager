# Migration builder and Forge — decisions

All rulings are the operator's, 2026-10-19 to 2026-10-21. "Fresh install"
means a schema built by the new code; "deployed" means one built before it.

## Fresh-install correctness over the current shape; deployed schemas stay (2026-10-19)

A derived name, a widened type or an emitted clause may change for fresh
installs when the current output is wrong or inconsistent. Deployed schemas
are left as they are: no corrective migrations, and no cleanup of what the old
behavior left behind (the duplicate MySQL/MariaDB unique indexes, for one).
Fresh and upgraded installs may diverge; each divergence is an upgrade note.
Re-running an already-run migration's `down()` under a changed rule is kept
working only where that is cheap; each item below records its cost.

Public signatures do not change (the alias-chain rule). One optional trailing
parameter was approved as an exception: `drop_index(…, bool $unique = false)`.

## A builder workaround moves into the Forge once verified (2026-10-19, read 2026-10-20)

A builder workaround is removed only after its Forge fix is verified on every
engine it covers. "Verified" is read through the engine bar: live on
PostgreSQL, MariaDB and MySQL, derived and recorded as unverified on SQL
Server. The framework has never run on SQL Server, so the builder's SQL Server
code was exactly as unverified as the Forge's; moving it lowers no assurance.

The `unsigned` widening block was deleted in the same pass as its Forge fix,
before the live run. Uncommitted work under review keeps `git diff` as the
fallback the rule was protecting, so finishing the work first and validating
at the end of the batch cost nothing. The rule itself stands.

## One naming rule, owned by the Forge (2026-10-19, amended 2026-10-20)

Each family trait carries one public `index_name(table, columns, unique)`. An
engine that deviates overrides that one method; the Forge's `add_key()` and
the builder both call it, and truncation happens inside it, so the two agree
past the limit too. The builder's copy of the rule and the pgsql trait's
second copy (`_unique_index_name()`, kept in step by comment) are gone.

| Engine | plain | unique |
|---|---|---|
| PostgreSQL | `{t}_{cols}_idx` | `{t}_{cols}_key` |
| SQL Server | `{t}_{cols}` | `{t}_{cols}_key` |
| MySQL / MariaDB | `{cols}` | `{cols}` |

- **PostgreSQL `_key`** is the engine's own name for a column-level unique
  constraint, so an `add_index(unique)` after an ADD-path unique finds it.
  **`_idx`** is its name for an unnamed index, and keeps a plain index off the
  unique's name. A table prefix is needed because tables and indexes share one
  namespace (observed: `CREATE INDEX user_client ON "user" (client)` fails
  while a table `user_client` exists).
- **MySQL/MariaDB** keep the engine's own naming, plain and unique alike; telling
  the two apart is the "Plain and unique under one name" decision below.
- **Exact case on PostgreSQL** (amended). First ruled as lowercase, which
  would match what unquoted DDL had folded. Reversed: the engine names an
  inline unique on a mixed-case table in the exact case
  (`MixedCase_Email_key`), so a lowercased derivation would add a second
  unique and miss the first on drop. No PostgreSQL project is deployed, so no
  folded names need matching. Every PostgreSQL statement that names an index
  or constraint quotes it.
- **PostgreSQL copies the engine's truncation** (amended). Past 63 bytes
  PostgreSQL's own names for inline uniques, multi-column uniques, unnamed
  indexes and `SERIAL` sequences differ from a hash cut, which would have
  broken every "equals the engine's name" property on a long name.
  `pg_object_name()` mirrors `makeObjectName()` (shorten the longer of table and
  columns, the columns on a tie, clip at a UTF-8 boundary, keep the label) and
  the column part mirrors `ChooseIndexNameAddition()` (stop once it reaches
  64 bytes). PostgreSQL `index_name()` and the builder's `_pg_sequence_name()`
  use it. MySQL/MariaDB, SQL Server and the builder's FK/PK names (which no
  engine generates) keep the hash cut, one copy, in `truncate_identifier()`.
- **The clash suffix is not mirrored.** When another relation already holds the
  name, PostgreSQL appends a digit (`…_key1`). The derived name then points at
  that other relation, the table-filtered lookups see nothing, and the
  statement fails on the name. That failure is loud from the builder and under
  the migration runner, and silent through `dbforge->create_table()` with
  `db_debug` off; see "Accepted known issues".
- **MySQL family `add_key()`** goes through `index_name()` as well: the vendor's
  inline-`KEY` loop is overridden with the name taken from it, because the
  vendor's uncut name past 64 characters is rejected by MySQL 8.4.
- **Deployed names are not looked up.** `drop_index()` has no fallback to the
  pre-rule names. A fallback to PostgreSQL's old plain `{t}_{c}_key` would
  collide with the live name of a unique, so it needs a non-unique guard, a
  kept copy of the old rule and its own probe, for a failure that only leaves
  an extra index. `drop_index(name: …)` is the escape hatch.

## Forge layout: a common trait and three family traits (2026-10-19)

A common trait holds the name default, the hash cut, the plain-index loop and
the column-change skeleton (strip the inline `unique` from a copy of the
fields, run the vendor `_alter_table()`, then per unique field look the name
up and append the engine's statement if absent). Family traits override only
the engine pieces, and are shared across drivers the way the original pgsql
trait was: PostgreSQL (`postgre`, `pdo/pgsql`), MySQL/MariaDB (`mysqli`,
`pdo/mysql`), SQL Server (`sqlsrv`, `pdo/sqlsrv`, `pdo/dblib`). The vendor
`_alter_table()` is byte-identical within each family. Rejected: three
independent traits, which repeat the skeleton three times.

- The inline `unique` is blanked on every engine rather than per family: the
  PostgreSQL vendor never reads it, so blanking there is a no-op.
- PostgreSQL's own `_alter_table()` was folded into the skeleton through a
  `_prepare_change()` hook. Rejected: keeping it and aliasing the common one,
  which is harder to read.
- **Reset before throwing.** `modify_column()` calls `_reset()` only after
  `_alter_table()` returns, and the dbforge is shared, so every throw inside
  `_alter_table()` resets first; otherwise the next `dbforge` call in the same
  process carries the failed call's fields.

## `unsigned` widening (2026-10-19, final 2026-10-20)

Widen an integer only when its unsigned maximum exceeds the signed maximum.
Float, Double and Decimal are not widened: their unsigned range is the
positive half of the signed one. Widening is a range change, not a
constraint: a widened column still accepts negatives.

- `BigInt` unsigned is `(20,0)` on PostgreSQL and SQL Server: twenty digits hold
  18446744073709551615. Rejected: bare `numeric` (unbounded, accepts
  fractions) and capping SQL Server at `BIGINT` (loses the upper half).
- No Float→Double widening: the signed type already holds the range, and a
  flag silently changing what a type name means, per engine, is worse than
  asking for `Double`. No shipped column was unsigned `Float`.
- The Forge owns the matrix, so raw `dbforge` callers get it too. It sets
  `CONSTRAINT` rather than the length, because the Forge builds the length from
  it after `_attr_unsigned()` runs.
- PostgreSQL and SQL Server each carry their own copy of the matrix: the common
  trait is also used by the MySQL family, whose vendor `_attr_unsigned()` works.
- The builder clears `unsigned` for a PostgreSQL `TinyInt`, which it maps to the
  type string `SMALLINT`; the Forge cannot tell it from a `SmallInt` and would
  widen it to `INTEGER`.
- SQL Server `TINYINT` is natively 0–255 and stays.

## `auto_increment` as `BIGSERIAL` (2026-10-20)

The PostgreSQL family overrides `_attr_auto_increment()`: `BIGINT`, `INT8` and
`NUMERIC` (compared case-insensitively) become `BIGSERIAL`, everything else
`SERIAL`. In the `BIGSERIAL` branch `CONSTRAINT` is unset, or a widened
`NUMERIC(20,0)` becomes the invalid `BIGSERIAL(20,0)`. It covers raw `dbforge`
callers and the builder, on CREATE and ADD; `field_id()` now gets a `bigint`
column and sequence.

Rejected: the builder passing `BIGSERIAL` as the type (covers `field()` callers
only), and identity columns (rewrite every fresh auto-increment column, and
`add_auto_increment()` would still make a sequence with a default). Identity
was parked then and adopted later; see "PostgreSQL `auto_increment` as an
identity" below. A 4-byte `SERIAL` widened later by hand keeps an `integer`
sequence.

## `USING` on a PostgreSQL type change: opt-in (2026-10-20)

Observed on a 1,000-row table (`pg_class.relfilenode`): `varchar(100)` →
`varchar(120)` without `USING` does not rewrite the table; the same change with
`USING v::varchar` or `USING v::text` does. Emitting `USING` on every change
would turn a free widening into a rewrite under an exclusive lock.

- The Forge emits `USING` only for a field that sets `cast => true`;
  `modify_column_cast()` sets it and delegates. Rejected: always (the rewrites
  above) and only when needed (a catalog lookup plus PostgreSQL's coercion
  rules, and an unneeded cast still rewrites).
- The cast base is the type with a trailing `COLLATE …` and any parenthesized
  length removed, `CHAR`/`CHARACTER` mapped to `TEXT` (a bare `CHAR` is
  `CHAR(1)`). An unconstrained cast lets the `TYPE` clause reject an overlong
  value instead of truncating it. Multi-word types (`DOUBLE PRECISION`) survive;
  a leading-token rule would not.
- The text is appended to the field's length before the vendor call, which
  concatenates type and length, so the vendor method is not copied.
- The `cast` key is read lowercase only.
- `field()` no longer emits `base_type`: the Forge derives the base, and
  nothing else read it.

## `auto_increment` on a PostgreSQL column change throws (2026-10-20)

Before any statement is built, a column change whose processed type is
`SERIAL` or `BIGSERIAL` throws, pointing at `add_auto_increment()`. A caller
passing either as the type lands there too. Rejected: delegating to the
sequence path inside the Forge (two copies of the builder helper's
data-dependent logic) and returning `false` (the vendor convention for an
unsupported change, but silent with `db_debug` off).

## `unique` on a column change (2026-10-19, amended 2026-10-20)

- **PostgreSQL makes a unique constraint**, the same object `add_column` and
  `create_table` already make, named by `index_name(unique)`. Rejected:
  stripping the inline `UNIQUE` from the ADD and CREATE paths so everything is
  a bare index, which rewrites released behavior on every fresh install.
- **MySQL/MariaDB**: the `MODIFY` loses its `UNIQUE`, then `ADD UNIQUE INDEX`
  under the engine's own name runs if nothing holds it. The two are separate
  statements, so duplicate rows fail the second after the column change has
  run; the skill says `assert_no_duplicates` first.
- **SQL Server**: `CREATE UNIQUE INDEX {t}_{c}_key`, guarded the same way.
  Rejected: `ADD CONSTRAINT`, which would need a `DROP CONSTRAINT` arm in
  `drop_index()` on an engine nothing can run. The vendor's SQL Server change
  never renames, so the index goes on the existing column name.
- **Idempotent by a pre-select**, the same `index_uniqueness()` lookup the
  builder uses; a plain index under the name throws (a deployed schema can
  carry a legacy plain `{t}_{c}_key`).
- **`unique: false`, or omitted, is a no-op.** `field()` sets the key only on
  `true`, so the Forge cannot tell false from omitted. Removal is always
  `drop_index()`.
- **A rename with `unique` over a column that already holds a unique throws**
  (amended). A rename keeps the unique under its old name, the lookup of the new
  name finds nothing, and the add leaves two. The Forge refuses and gives the
  drop instruction: a `modify_column()` must not drop anything implicitly, and
  a unique left under a name the lookups no longer derive is not a pattern to
  teach. A plain index under the old name does not throw (MySQL shares one name
  for both kinds). A unique already stranded by an earlier rename is not seen.
- SQL Server's inline `DEFAULT` on a column change is the same vendor defect
  and was left out: parked as "`modify_column()` with a `default` emits invalid
  SQL on SQL Server".

## Plain and unique under one name (2026-10-19)

Under the naming rule, plain and unique share a name only on MySQL/MariaDB,
and on deployed PostgreSQL schemas with a legacy plain `{t}_{c}_key`.

- `add_index(unique)` over a plain index of the same name throws. Rejected:
  upgrading it in place (destroys an index its author may have shaped, such as
  prefix lengths) and a second name (a redundant plain index, and breaks the
  MySQL naming).
- A plain request over an existing unique returns `false`: the unique serves
  it. Where the two names differ, a plain request also looks under the unique
  name.
- The lookup lives on the Forge (`index_uniqueness()`), so the builder and the
  column-change path share one copy of the catalog SQL.
- Columns are not compared: a derived name encodes them, and the only mismatch
  is a caller passing `name:` for other columns.

## `drop_index()` (2026-10-19, 2026-10-20)

- **A refused drop throws**, carrying the engine's error; `false` keeps one
  meaning, "no such index".
- **PostgreSQL detects a constraint by a pre-select** on `pg_constraint`,
  `contype IN ('p', 'u', 'x')`. A unique constraint is dropped with
  `DROP CONSTRAINT`; a primary-key or exclusion constraint throws, pointing at
  `drop_primary_key()`. The type filter keeps a foreign key or check
  constraint that shares a plain index's name from reading as index-backing.
  Rejected: try `DROP INDEX` and fall back on the error (fragile under
  `db_debug`, swallows real errors). PostgreSQL only: MySQL/MariaDB uniques are
  plain indexes, and SQL Server's column-change unique is an index.
- **`unique:` picks the derived name.** Where plain and unique names differ,
  nothing said which one a derived drop targets; the caller states it, as with
  `add_index()`. Appended after `name`, so positional callers keep working;
  ignored when `name:` is given. Rejected: deriving both and throwing when
  both exist (inference), plain-then-unique, a guard when only the unique
  exists (breaks a "drop whichever is set" cleanup), and throwing on `false`
  (breaks an idempotent re-run of a half-applied `down()`). The trap, a
  MySQL-tested `down()` without `unique: true` leaving the unique on
  PostgreSQL, is documented in the skill rather than guarded.
- **`add_index(unique: true)` stays a bare unique index on PostgreSQL.** The
  caller asked for an index, the method is released, and `prefix_lengths`
  forces an index anyway. So a field's `unique` makes a constraint and
  `add_index(unique)` an index; both share the name and `drop_index()` drops
  either.
- **`ion_auth_v4` declares its uniques on the fields.** Without the column-change
  unique on every engine and the constraint-aware drop, its `down()` failed on
  PostgreSQL, MySQL added `email_2`, and SQL Server rejected the inline
  `UNIQUE`. No tag contains v4, so no released install changes.

## The builder's DDL throws on failure (2026-10-20)

- `_ddl(string $sql): object|true` runs the statement and returns `query()`'s
  result, so a `match` arm stays an expression; on `false` it calls
  `_ddl_failed(string $sql): never`, which throws a class-prefixed
  `RuntimeException` with the engine's message and the start of the statement.
  `modify_column_cast()`'s `dbforge->modify_column()` call checks for `false`
  and calls `_ddl_failed()` with `last_query()`, since a delegation exposes no
  statement.
- With `db_debug` on, CI halts at the failed statement before `_ddl()` sees a
  result, so nothing changes under the migration runner, which forces it on.
  Inside a PostgreSQL DDL transaction the throw reaches the runner, which rolls
  the migration back.
- Moved: every load-bearing statement of `add_index()`, `drop_index()`, the FK
  and PK helpers, `add_auto_increment()` (both engines) and
  `modify_field_timestamp()`. Not moved: the `IF EXISTS` cleanups (optional by
  design), the catalog lookups (a failed one is already fatal) and the
  collation `CREATE` (has its own throw).
- A migration's own `dbforge` calls keep CI's `false` convention: they are
  outside the builder, and the runner forces `db_debug` on for them.

## SQLite: no subclass (2026-10-19, 2026-10-20)

SQLite was taken out of scope at the start, pending a decision on whether the
framework keeps supporting it. No SQLite subclass was added, so
`MGR_Loader::dbforge()` returns the vendor forge, which has no `index_name()`,
`index_uniqueness()` or `truncate_identifier()`. The builder calls them
unconditionally, so `add_index()` and `drop_index()` die with a PHP `Error` on
SQLite (the FK, PK and auto-increment helpers already threw there before
naming anything), and the package migrations that use them cannot run there.
Accepted: if SQLite stays, adding the subclass is easier than removing a
throw. `drop_index()`'s SQLite arm runs its existing statement through
`_ddl()`; nothing SQLite-specific was added. Parked as "SQLite: drop it from
the support list or repair it".

## Accepted known issues (2026-10-20, 2026-10-21)

- **A derived-name clash through `dbforge->create_table()` is silent with
  `db_debug` off.** The vendor `create_table()` ignores the result of its
  follow-up `CREATE INDEX`. Accepted as is: the migration tooling forces
  `db_debug` on, and a caller who runs it otherwise owns the result. A fix
  would mean overriding `create_table()`, with a far larger blast radius than
  the defect. The PostgreSQL clash suffix reaches it the same way.
- **MySQL/MariaDB `modify_column(unique)` is two statements** (above).
- **Deployed MySQL/MariaDB duplicates stay**: databases that ran the old
  column-change path keep their `email_2`.
- **Upgraded schemas were never exercised**: every live run was a fresh
  install.
- **The builder needs the framework forge on every engine.** The index, key
  and sequence helpers call Forge methods, so a project whose `MY_Loader` does
  not extend `MGR_Loader` gets a PHP `Error` from them (by reading). The
  scaffold's `MY_Loader` extends it; documented as an upgrade note rather than
  guarded.
- **SQL Server, by reading:** a column change with `new_name` does not rename,
  and a repeat `ALTER COLUMN` on a column that already has a unique index may
  be refused. Both wait on a live run, in "SQL Server Forge family: first live
  run", together with an unsigned `BigInt` with `auto_increment`, whose
  `DECIMAL(20,0)` fails the vendor's `int` test and loses its `IDENTITY`.

## PostgreSQL `auto_increment` as an identity (2026-10-21)

Supersedes the `BIGSERIAL` entry above, whose identity alternative is now
adopted. `_attr_auto_increment()` emits `GENERATED BY DEFAULT AS IDENTITY` on
`BIGINT` (for `BIGINT`, `INT8`, `NUMERIC`) or `INTEGER`, the same type mapping
`BIGSERIAL`/`SERIAL` had; `ALWAYS` rejects explicit ids and is not used. A
CHANGE adds the identity through `_change_statements()` (`SET NOT NULL`, `ADD
GENERATED`, `setval()` past `MAX`), and skips it when a catalog read finds the
column already an identity, which is the widening call. `add_auto_increment()`
on PostgreSQL drops a placeholder default first, keeps a `nextval()` default so
a `SERIAL` column is refused, and numbers `0`/`NULL` rows past the highest
existing value. Existing `SERIAL` columns are not converted.

`add_auto_increment()` numbers those rows itself on MySQL and MariaDB too: the
engine's `MODIFY … AUTO_INCREMENT` numbers them in scan order from a running
counter, so a `0` ahead of a real id can take its value (`0, 1` became `1, 1`,
`1, 0, 2` became `1, 2, 2`, observed on MariaDB 12.3). The builder sets a user
variable to `MAX` and numbers the rows from it first; a user variable because
the table has no row identifier to join on, deprecated but accepted on MySQL
8.4. This replaces the 2026-08-15 premise that those engines already number
past the highest value. Checked on MariaDB 12.3.3, MySQL 8.4.11 and
PostgreSQL 18.6.
