# Database drivers — what was built and why

Sparked by a real deployment constraint: an old Ubuntu 20.04 server whose
frozen PHP 8.4 package repo has no `pgsql` extension, but does have
`pdo_pgsql` via PECL. That target runs a pre-2.0 Manager project with no
`MgrDriver`/cross-engine layer, needing only a secondary connection over PDO.
The current-framework work (`docs/workspace/19-database-pdo-integration`,
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
