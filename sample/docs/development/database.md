# Database engine & driver — setup & configuration

> Scope: picking a database engine and driver for this stack. For the rest
> of the Docker setup (build, deploy, tuning, troubleshooting), see
> `docker.md` (beside this file).

## Pick a database engine

Pick ONE engine and edit `<instance>.env` to match — the base "Package"
section's `DB_DRIVER`/`DB_CHAR_SET`/`DB_COLLATION`, and the "Docker
deployment-dependent" block's `DB_HOST`/`DB_PORT` (`DB_NAME`/`DB_USER` there
can be any non-empty value):

| Engine | `--profile` | `DB_HOST` | `DB_PORT` | `DB_DRIVER` | `DB_CHAR_SET` / `DB_COLLATION` |
|---|---|---|---|---|---|
| PostgreSQL | `postgres` | `postgres` | `5432` | `postgre` | `UTF8` / *(leave empty)* |
| MySQL 8 | `mysql` | `mysql` | `3306` | `mysqli` | `utf8mb4` / `utf8mb4_0900_ai_ci` |
| MariaDB | `mariadb` | `mariadb` | `3306` | `mysqli` | `utf8mb4` / `utf8mb4_uca1400_ai_ci` |

### Running over PDO instead of the native driver

Which driver a project runs is a project choice, not just a fallback for a
host missing an extension. `DB_DRIVER` also accepts `pdo/mysql` and
`pdo/pgsql`, both measured at performance parity with their native
counterpart through this framework's own Model-layer benchmark —
already-tuned defaults in `mgr_apply_pdo_dsn()` are what get you there;
you don't need to change anything for it.

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
  Postgres returns the string `'t'`/`'f'` (and `'f'` is truthy in PHP — a
  real trap on the default driver), native MySQL/MariaDB return `'1'`/`'0'`
  strings, `pdo/pgsql` returns real `bool`, `pdo/mysql` returns `int`. This
  is why the migrations skill directs `SmallInt` over `Bool` for `0`/`1`
  flags regardless of driver.
- **`DB_CHAR_SET` is silently ignored under `pdo/pgsql`** today — set the
  database's own encoding to match, or pass it through
  `mgr_apply_pdo_dsn()`'s `options` directly.
- **`reconnect()`/`data_seek()` aren't implemented on the PDO path yet** —
  inert today (nothing in the framework calls either), worth knowing only
  if you're driving a long-running CLI worker or the websocket loop
  directly against the connection.

This image ships neither PDO extension by default. Add the one you need to
`docker/Dockerfile`'s `docker-php-ext-install` list (e.g. `pdo_pgsql \`),
`./docker_manage.sh -e <instance> --profile <db> build`, then set
`DB_DRIVER` — `mgr_apply_pdo_dsn()` in `application/config/database.php`
builds the `dsn` from the same `DB_HOST`/`DB_PORT`/`DB_NAME`.

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
