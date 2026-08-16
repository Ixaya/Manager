# Database drivers — final state

## What the code does now

**`MgrDriver::fromCI(string $dbdriver, ?string $subdriver = null)`**
resolves a compound `pdo/<engine>` driver string to the real engine; a
no-op for a plain driver name like `mysqli` or `postgre`. Documented in the
`mgr-helpers-libraries` skill's `manager_db_driver` row.

**`sample/application/config/database.php`'s `mgr_apply_pdo_dsn()`** wraps
the `$db['default']` (and optionally a secondary connection group) array
literal, building the PDO `dsn` from `DB_HOST`/`DB_PORT`/`DB_NAME` and
setting `PDO::ATTR_EMULATE_PREPARES => true` when the subdriver is `pgsql`.
A caller-supplied `options` entry wins over the default. Sample-local by
design — see `decisions.md`.

**`MGR_Migration_builder::modify_field_timestamp()`** issues one
`$this->db->query()` per DDL statement instead of combining
`DROP TRIGGER IF EXISTS …; CREATE TRIGGER …` into one call.

**`MGR_Migration_builder::modify_column_cast()`** adds the `USING` cast a
cross-family `modify_column()` type change needs on PostgreSQL (the native
driver and the `pdo/pgsql` subdriver both omit it — separate vendor
classes, same bug). One column per call, applying that column's remaining
attributes itself, and casting to the unconstrained type so a value the new
type can't hold still fails the migration instead of being truncated.
Live-tested on `pdo/pgsql` and `pdo/mysql`; native `postgre`, MariaDB and
SQL Server not separately exercised.

**PDO is the default and the recommendation; the native drivers stay fully
supported.** `sample/.env.sample` ships `DB_DRIVER=pdo/mysql` — the
2026-08-09 ruling in `decisions.md`, superseding this initiative's original
native-first one — and `sample/docker/Dockerfile` installs `mysqli`,
`pgsql`, `pdo_mysql` and `pdo_pgsql`, so moving between them needs no image
change. Details in `sample/docs/development/database.md`'s "PDO is the
default" section and `docs/development/database.md`'s "Database extensions"
entry.

**Validated:** PostgreSQL and MySQL, native and `pdo/*`, migrate clean and
pass the sample's full PHPUnit suite (86 tests / 209 assertions) each.
Gates (`phpstan`, `php-cs-fixer`) green throughout.

**`MgrFieldType::Timestamp`** maps to PostgreSQL `TIMESTAMPTZ` / SQL Server
`DATETIMEOFFSET` (was a bare `TIMESTAMP` literal on every engine).
**`mgr_create_date_time()`** normalizes every return path to the
app-configured timezone. **`mgr_format_date_time_iso()`**
(`manager_time_helper.php`) formats a `Timestamp` column's raw read-back as
ISO-8601 for API output — optional, at controller-response time. `Text`,
`Float`, and the `UNSIGNED`-widening path on PostgreSQL/SQL Server got
schema-mapping corrections; `TinyInt`'s SQL-Server range and `Uuid`'s
case/sort divergence are documented, not code-fixed (no schema-level fix
exists for either). Full rationale: `decisions.md`'s "Schema type mapping"
section.

**Validated (schema type mapping):** PostgreSQL and MySQL native drivers,
live-tested through the real model `get()`/`select()` read path, not a
hand-rolled cast. SQL Server is code-only/unvalidated for all of it, pending
`pdo-dblib-vendor-gaps`. Gates green throughout.

## Remaining open work

Named by title — a promoted proposal moves out of `00-proposals/`, and a
path here would need editing back:

- **`pdo-driver-connect-error`** — a failed PDO connection crashes on
  `CI_DB_pdo_driver::error()` (`Call to a member function errorInfo() on
  false`) instead of reporting cleanly, confirmed on both `pdo_pgsql` and
  `pdo_mysql`. The file is `vendor/nielbuys/framework`'s CI3 core — a
  Composer dependency, — so a fix needs a Composer patch.
  Also carries a related defect in the same file: `pdo_mysql_driver.php`
  fatals with a confusing `Undefined constant` error instead of a clean
  "could not find driver" when the extension is absent.
- **`pdo-as-default-driver`** — a follow-on campaign proposal: what would
  have to be true (fetch-type migration path, `reconnect()`/`data_seek()`
  implemented, `DB_CHAR_SET` handling, full SQL Server/SQLite matrix) before
  the recommendation above could flip.
- **`database-docs-home`** — a small documentation move: driver/`db_debug`/
  fetch-type content currently lives inside the Docker docs pair because
  local dev happens to provision databases through Docker; proposes a
  dedicated `database.md` pair instead, since production uses an external,
  Docker-uninvolved database.
- **`timestamp-write-format-atom`** — whether
  `mgr_get_now_date_time_sql_format()` should write an offset-explicit
  literal instead of its current naive one; blocked on an unproven MySQL
  strict-mode literal-parsing question.
