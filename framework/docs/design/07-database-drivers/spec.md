# Database drivers — what was built and why

Sparked by a real deployment constraint: an old Ubuntu 20.04 server whose
frozen PHP 8.4 package repo has no `pgsql` extension, but does have
`pdo_pgsql` via PECL. That target runs a pre-2.0 Manager project with no
`MgrDriver`/cross-engine layer, needing only a secondary connection over PDO.
The current-framework work (`framework/docs/workspace/19-database-pdo-integration`,
2026-08) built and validated first-class `pdo/*` support instead of a
one-off shim, so any future PHP-side database driver work has a real
foundation and a permanent record to extend.

## What shipped

- `MgrDriver::fromCI()` gained a `subdriver` parameter that resolves a
  compound `pdo/<engine>` driver string to the real engine — a no-op for a
  plain driver name.
- `sample/application/config/database.php` exposes `DB_DRIVER=pdo/<engine>`
  via `mgr_apply_pdo_dsn()`, a sample-local helper (deliberately not
  promoted to a skill — see `decisions.md`) that builds the PDO `dsn` from
  the same `DB_HOST`/`DB_PORT`/`DB_NAME` config keys and sets
  `PDO::ATTR_EMULATE_PREPARES` for the `pgsql` subdriver only.
- `MGR_Migration_builder::modify_field_timestamp()` now issues one
  `$this->db->query()` call per statement — CI's PDO driver rejects
  multiple commands in one call, which native `postgre` silently tolerated.
- Full engine × driver validation: PostgreSQL and MySQL, both native and
  `pdo/*`, migrate clean and pass the sample's full PHPUnit suite.
  MariaDB has the benchmark and fetch-type matrix but not a full suite run
  under `pdo/mysql`; SQL Server is unrun on any driver — both closed as
  sufficient given the ruling below.

## The recommendation

Native drivers (`mysqli`/`postgre`) ship as the default; `pdo/mysql` and
`pdo/pgsql` are fully supported, opt-in alternatives at performance parity.
Full rationale in `decisions.md`; measured data in
`driver-matrix-benchmark.md` and `driver-matrix-types.md` beside this file.

## Explicitly out of scope

- The file `pdo`/`pdo_pgsql` is in `vendor/nielbuys/framework`'s CI3 core
  a Composer dependency, so a fix woulld need to be Composer patch. The
  driver files there already work as shipped; two defects were found *in*
  them (`review.md`) but a third (`error()` crashing on a failed connection)
  is upstream-tracked and parked as its own proposal rather than patched
  here.
- Schema/migration content fixes on the real pre-2.0 project that motivated
  this — that project's own concern, not this repo's.

## Schema type mapping (MgrFieldType), campaign 22

Absorbed at distillation from `framework/docs/workspace/22-timestamp-timezone-mapping/`
— sparked by checking whether `MgrFieldType::Timestamp`'s PostgreSQL mapping
actually re-derives under a session-timezone change (it didn't), which grew
into a broader sweep of every other `MgrFieldType` case for the same class of
defect, plus an optional read-side normalization helper for the divergence
that sweep couldn't fix at the schema level.

### What shipped

- `MgrFieldType::Timestamp` repointed: PostgreSQL `TIMESTAMP` → `TIMESTAMPTZ`,
  SQL Server → `DATETIMEOFFSET` (was a bare `TIMESTAMP` literal, SQL Server's
  own `TIMESTAMP` keyword being a `ROWVERSION` synonym, not a datetime type).
  `mgr_create_date_time()` now normalizes every return path to the
  app-configured timezone, the prerequisite for reading the repointed column
  back safely.
- Three field-type corrections: `Text` gained SQL Server's `NVARCHAR(MAX)`
  branch (was falling through to a bare, deprecated `TEXT` literal); `Float`
  gained PostgreSQL `REAL` / SQL Server `FLOAT(24)` (was defaulting to
  8-byte double precision on both); `TinyInt`'s SQL-Server unsigned-only
  range documented (no schema-level fix exists).
  `Uuid`'s PostgreSQL-lowercases/SQL-Server-mixed-endian-sorts divergence
  documented the same way — no schema-level fix, normalize at the caller.
- A real vendor bug surfaced mid-sweep: CI3's own vendored
  `postgre_forge`/`sqlsrv_forge` `_attr_unsigned()` is a no-op on both
  engines, so `unsigned: true` silently did nothing there. Fixed inside
  `MgrFieldBuilder::_resolveColumn()` by re-dispatching to the next-widest
  `MgrFieldType` before resolving the column — no Composer patch needed.
- A new helper, `mgr_format_date_time_iso()`
  (`system/package/helpers/manager_time_helper.php`), formats a `Timestamp`
  column's raw driver value as ISO-8601 so API output reads identically
  across engines — optional, at controller-response time, never automatic.
  No equivalent helper was built for `Uuid` (see `decisions.md`): the entire
  fix is `strtolower()`, no framework knowledge worth hiding.
- Full per-engine live-test evidence (PostgreSQL/MySQL native drivers, plus
  code-inspection-only SQL Server): `driver-matrix-timestamp-uuid.md`.

### Explicitly out of scope

- SQL Server validation for any of the above — code-only this entire
  campaign, blocked on the open `pdo-dblib-vendor-gaps` proposal.
- Switching `mgr_get_now_date_time_sql_format()`'s write format to an
  offset-explicit literal — parked as its own proposal
  (`framework/docs/workspace/00-proposals/timestamp-write-format-atom/spec.md`),
  blocked on an unproven MySQL strict-mode literal-parsing question.
