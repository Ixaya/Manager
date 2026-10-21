# Migration builder and Forge — measurement and validation record

## How it was measured

PostgreSQL 18.6, MariaDB 12.3.3 and MySQL 8.4.11 (the bundled profiles), on
`pdo/pgsql` and `pdo/mysql` while the work ran, and on all six combinations at
the close: `pdo/pgsql` and `postgre`; `pdo/mysql` and `mysqli` on MariaDB and on
MySQL. SQL Server has no profile and cannot run on this hardware: every SQL
Server claim is derived from the code. SQLite was out of scope. `dbprefix` was
empty throughout.

- **Probes** were authenticated, gitignored probe-module controllers, one method
  per defect, each run keyless first (`403`) and then with a real API key. They
  forced `db_debug` off so a failure came back as a value, called raw `dbforge`
  and the builder side by side, and read the result back from the engine's own
  catalog (`pg_index`/`pg_constraint`/`pg_attribute`/`pg_sequence`,
  `information_schema.statistics`/`columns`), plus a duplicate or out-of-range
  insert where a constraint or a type range was the claim.
- **Every capture was stamped** with a content hash of the framework and vendor
  files it loaded, computed in the container and compared with the host, so a
  result names the tree it ran on.
- **Each engine ran on a fresh volume**, so the package migrations ran under the
  new code; the `system/` bind mount was confirmed in the container before each
  run.
- **Engine facts behind a ruling were checked with psql/mysql alone**, without
  framework code, in rolled-back transactions.

## Before and after

Before = the code before this work; after = the final tree.

| What | Before | After |
|---|---|---|
| PostgreSQL raw `unsigned` `SMALLINT` / `INT` / `BIGINT` | `smallint` / `integer` / `bigint` | `integer` / `bigint` / `numeric(20,0)` |
| PostgreSQL builder `BigInt` unsigned | unconstrained `numeric` | `numeric(20,0)`; 18446744073709551615 stored |
| PostgreSQL builder `Float` unsigned | `double precision` | `real` |
| PostgreSQL `field_id()` / raw `BIGINT` `auto_increment` | `SERIAL`, `integer`; `INSERT 3000000000` fails | `BIGSERIAL`, `bigint` column and sequence; accepted |
| PostgreSQL raw `modify_column()` `VARCHAR` → `INT` | fails, "cannot be cast automatically" | same; with `cast => true`, `USING "code"::INT` succeeds |
| PostgreSQL `modify_column(auto_increment)` | `TYPE SERIAL` fails after earlier fields ran | throws before any statement |
| `modify_column(unique)` twice, MySQL/MariaDB | `email`, then `email_2` | one `email` |
| `modify_column(unique)`, PostgreSQL | bare unique index | constraint `{t}_{c}_key`; a repeat sends nothing |
| PostgreSQL `add_key('a')`, then `add_index('a')` | `{t}_a`, then a duplicate `{t}_a_key` | `{t}_a_idx`; `add_index()` returns `false` |
| PostgreSQL derived `drop_index()` of an `add_key()` index | `false`, index stays | dropped |
| `add_index(a)`, then `add_index(a, unique)` | `false`; duplicate insert accepted | PostgreSQL: unique `_a_key` created, duplicate rejected; MySQL/MariaDB: throws |
| PostgreSQL `drop_index()` of an ADD-path unique | `true`, nothing dropped | `DROP CONSTRAINT`; duplicate then accepted |
| Builder DDL rejected by the engine, `db_debug` off | `true` | `RuntimeException` with the engine's error |
| PostgreSQL names past 63 bytes | hash cut; a second index or unique on a repeat | 9 of 9 cases equal the engine's own name |
| MySQL `add_key()` over an 89-character column list | `ERROR 1059` | accepted under the 64-character hash-cut name |
| PostgreSQL mixed-case table | names folded to lowercase; exact-case lookups miss | exact case on every path; `add_auto_increment()` works |

The "past 63" cases: an ADD-path unique where table and columns tie, a
CREATE-path unique on a short table, a multi-column unique, a three-column
plain index that stops at 64 bytes, a multibyte name, a mixed-case name, a
`SERIAL` sequence, an unnamed plain index, and a short control.

## Final matrix

| Configuration | Probes | PHPUnit (`sample/tests`) |
|---|---|---|
| PostgreSQL 18.6, `pdo/pgsql` and `postgre` | all `200`; on `postgre` the SQL Server derivation probe fails (probe scaffolding, below) | 161 tests, 450 assertions, 2 skipped (MySQL-only) |
| MariaDB 12.3.3, `pdo/mysql` and `mysqli` | `200`, PostgreSQL-only probes `400` by design; the same derivation-probe failure on `mysqli` | 161 tests, 392 assertions, 18 skipped (PostgreSQL-only) |
| MySQL 8.4.11, `pdo/mysql` and `mysqli` | as MariaDB | as MariaDB |

- **Native against PDO**: no behavioral difference on any engine. What differs is
  the driver identity, the engine error text, native PostgreSQL's `E_WARNING`
  on a failed `pg_query`, the class prefix in exception messages and numeric
  strings (`"1"` against `1`).
- **Package migrations**: a fresh `migrate` to latest on all three engines. A
  round trip (latest, down past `ion_auth_v2`, back up) on all three restores
  the identical index set: PostgreSQL `user_username_key` and `user_email_key`
  as constraints, dropped as constraints on the way down; MySQL/MariaDB one
  `email` and one `username`, no `email_2`.
- **Gates**: PHPStan level 5 no errors; php-cs-fixer 0 of 441; `sample/tests`
  0 of 31 under the project's rules on a temporary config, since it is outside
  both gates' paths.
- **Tests discriminate.** Against the released code, the widening and cast
  classes fail 4 of 11; mutated copies of the trait (no `CHAR` → `TEXT`, a
  leading-token base, `USING` on every change) are each caught by the test
  meant for them. The rename-with-unique throw test failed against the code
  before its guard (the duplicate was created silently).

## Engine behavior observed

- PostgreSQL tables and indexes share one namespace: `CREATE INDEX user_client`
  fails while a table `user_client` exists.
- PostgreSQL names an inline unique on a mixed-case table in the exact case,
  and an unquoted `CREATE INDEX MixedCase_a_key` stores `mixedcase_a_key`.
- `'name'::regclass` folds an unquoted name; the literal must carry the quoted
  identifier.
- `BIGSERIAL`, like `SERIAL`, is not a catalog type: `ALTER COLUMN … TYPE
  BIGSERIAL` fails. A `SERIAL` column widened with `modify_column(BIGINT)` keeps
  an `integer` sequence; an identity column's sequence follows.
- PostgreSQL 18 lists NOT NULL constraints in `pg_constraint` (`contype 'n'`).
- MySQL 8 returns `information_schema` column names in upper case unless
  aliased; MariaDB does not.
- On all three engines a column rename keeps its unique under the old name.
  MySQL/MariaDB repeat `MODIFY … UNIQUE` adds `email_2`, `email_3`, and so on.
- `numeric(20,0)` also accepts 18446744073709551616 (20 digits); 21 digits
  overflow. Widening is a range superset, as ruled.

## Derived only: SQL Server

The three SQL Server subclasses load against vendor CI with no abstract method
left, and derive `user_email` / `user_email_key`, limit 128, the `CREATE UNIQUE
INDEX` text, the unsigned matrix, `INT` unsigned `auto_increment` as `BIGINT
… IDENTITY(1,1)` and `BIGINT` unsigned `auto_increment` as `DECIMAL(20,0)` without
`IDENTITY`.
Never run: `index_uniqueness()` (`sys.indexes`, `OBJECT_ID(?)`), the guarded
unique on a column change, the throwing DDL sites, `add_index()`/`drop_index()`,
and the package migrations.

## Not observed

- A schema built by the old code, taken through the new `down()`/`up()`.
- The throwing DDL with `db_debug` on, beyond the first pass (it halts with CI's
  own query error); the FK, PK, MySQL `add_auto_increment()` and timestamp
  sites with `db_debug` off (same helper, statement text unchanged).
- How long the `USING` rewrite holds its exclusive lock.
- The package-migration round trip on the native drivers.
- A negative control for the reset-before-throw rule (shown by the forge being
  empty after the throw, not by reproducing the leak), and a mutation check of
  `UniqueConstraintTest` and `ModifyColumnUniqueTest`.
- A non-empty `dbprefix`, and SQLite.

The gitignored probes are kept for re-validation, with known staleness: the
SQL Server derivation probe fails on a non-PDO connection (it loads the PDO
subclass without the PDO base forge), one step name still says
`refused_by_constraint` for a call that now returns `false`, the
case-sensitive-defaults probe still checks `base_type`, and the probe module's
own migrations drop uniques without `unique: true`.
