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

**Native drivers are the default and the recommendation.** `mysqli` and
`postgre` ship in `sample/docker/Dockerfile`; `pdo/mysql` and `pdo/pgsql`
are fully supported at performance parity but require adding the subdriver
to `docker-php-ext-install` and rebuilding — documented in
`sample/docs/development/docker.md`'s "Running over PDO instead of the
native driver" and `docs/development/docker-decisions.md`'s "Database
extensions" entry.

**Validated:** PostgreSQL and MySQL, native and `pdo/*`, migrate clean and
pass the sample's full PHPUnit suite (86 tests / 209 assertions) each.
Gates (`phpstan`, `php-cs-fixer`) green throughout.

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
