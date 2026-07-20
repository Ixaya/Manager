# Test harness — what was built

A committed PHPUnit test suite for the sample scaffold, plus the framework-core
seam and directory layout it needed. Before this, the framework had no test
suite; auth behavior was checked only by throwaway CLI probes.

## Delivered

- **Bootstrap + base classes** (`sample/tests/`): `Bootstrap.php` boots the
  whole framework once (env pin, output-buffered default CLI route, support
  bases). `CITestCase` exposes the CI super-object through `$this->`;
  `AuthTestCase` adds the Ion Auth model and namespaced fixture helpers.
- **Two suites** wired in `phpunit.xml`: `Example` (DB-free harness smoke
  check) and `Auth` (12 DB-backed integration classes converted from the
  legacy `Auth_validate` CLI probe).
- **Config/Lang core seam** (`system/core/MGR/{Config,Lang}.php` +
  `sample/application/core/MY_{Config,Lang}.php`): makes module-aware config
  and language survive a function-scoped PHPUnit boot. Consuming projects must
  add the two shims to adopt the harness — see `MIGRATION.md`.
- **Directory renames** so the three test-related concerns stop colliding by
  name: `application/tests/` → `tests/`; `application/modules/test/` →
  `modules/probes/`; `docker/php/tests/` → `docker/php/smoke/` (baked as
  `modules/smoke/`), with `INCLUDE_TEST_MODULE` → `INCLUDE_SMOKE_MODULE`.

## Why integration, not mocks

CI3 / Ion Auth is built on the global super-object and the query builder, which
are not practically mockable, and cross-engine correctness (MySQL/MariaDB,
PostgreSQL, SQL Server, SQLite) can only be shown against a real engine. The
suite therefore runs against a real database with namespaced, self-cleaning
fixtures rather than mocks.

## Documentation

- Authoring guide (how to write/extend tests): `sample/docs/development/testing.md`.
- Running the suite (tools service, testing env): `sample/docs/development/docker.md`.
- Consumer adoption (copy `tests/`, add the shims): `MIGRATION.md`.
