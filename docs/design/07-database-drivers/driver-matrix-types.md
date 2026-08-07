# Driver matrix — fetch types

**Source of truth for what PHP type a column comes back as, per driver.**
Every figure here was produced the same way, through the same probe. Nothing in
this file comes from a raw `new PDO` / `pg_connect` / `mysqli` script — those
measure the client library, not this framework, and mixing the two is how a
reader draws a conclusion the framework does not support.

## What produces these rows

`probes/pdo_types/run` — a gitignored CLI probe. It inserts one row and reads it
back **through `MY_Model`'s own read path on the default connection**, so the
result reflects the shipped config (including whatever `mgr_apply_pdo_dsn()`
applies) rather than a hand-built handle.

```bash
# in sample/, with DB_DRIVER set to the row you want
./docker_manage.sh -e local exec php bash /var/www/html/bin/cli_run.sh probes/pdo_types/run
```

Backing files, all in the gitignored `sample/application/modules/probes/`:

| file | role |
|---|---|
| `migrations/default/20260805000000_Probe_types.php` | the `probe_types` table — one column per `MgrFieldType` whose PHP type could differ, built with `MGR_Migration_builder` so the shape is created correctly on every engine |
| `models/Probe_types.php` | a bare `MY_Model` subclass — no custom read logic to skew the result |
| `controllers/Pdo_types.php` | inserts the sample row, reads it back via `get()`, prints `get_debug_type()` per column |

The probe reports `dbdriver`, `subdriver`, `handle`, server version and
`emulate_prep` in its header, so a pasted result is always attributable to a
driver without trusting the surrounding notes.

## The matrix

Measured 2026-08-05. PHP 8.4.24; PostgreSQL 18.4, MySQL 8.4.11, MariaDB
12.3.2. Every PDO column reported `emulate_prep: true`.

| column (`MgrFieldType`) | pg native | pg PDO | my native | my PDO | maria native | maria PDO |
|---|---|---|---|---|---|---|
| `TinyInt` | string | **int** | string | **int** | string | **int** |
| `SmallInt` | string | **int** | string | **int** | string | **int** |
| `Int` | string | **int** | string | **int** | string | **int** |
| `BigInt` | string | **int** | string | **int** | string | **int** |
| `Decimal` | string | string | string | string | string | string |
| `Float` | string | **float** | string | **float** | string | **float** |
| `Double` | string | **float** | string | **float** | string | **float** |
| `Bool` | string `'t'` | **bool** | string `'1'` | **int** | string `'1'` | **int** |
| `Char` | string | string | string | string | string | string |
| `VarChar` | string | string | string | string | string | string |
| `Text` | string | string | string | string | string | string |
| `Date` | string | string | string | string | string | string |
| `DateTime` | string | string | string | string | string | string |
| `Timestamp` | string | string | string | string | string | string |
| `Json` | string | string | string | string | string | string |
| `Uuid` | string | string | string | string | string | string |
| `id` (PK) | string | **int** | string | **int** | string | **int** |

**The native drivers stringify everything, on all three engines.** Every
divergence is PDO returning a native PHP type.

Three details that a summary would lose:

- **`Decimal` stays a string under PDO on every engine.** Deliberate on PDO's
  part — converting to `float` would lose precision. So "PDO returns numbers as
  numbers" is wrong as stated: integers and floats convert, decimals do not.
- **`Bool` is not portable even under PDO.** Postgres has a real `BOOLEAN`, so
  PDO returns `bool`; MySQL/MariaDB map `Bool` to `TINYINT(1)`, so PDO returns
  `int`. Combined with native returning `'t'` on Postgres and `'1'` on
  MySQL/MariaDB, this column has **four** different representations across the
  matrix. This is why the `mgr-migrations` skill directs `SmallInt` rather than
  `Bool` for `0`/`1` flags; the guidance holds under PDO too.
- **The `'f'` trap is native-Postgres-only, and it is real.** Native `postgre`
  returns the *string* `'f'` for false, and `'f'` is truthy in PHP, so a plain
  `if ($row['flag'])` is wrong on a false value there. PDO returns real `false`.
  Any consuming project using `MgrFieldType::Bool` on Postgres has this bug
  today on the default driver.

Two server-side differences showed up that are **not** driver-related — they are
identical across native and PDO within each engine, so do not attribute them to
a driver choice:

- `Char(8)` is blank-padded to `'abcd    '` on PostgreSQL, returned as `'abcd'`
  on MySQL/MariaDB.
- `Json` is normalized to `'{"a": 1}'` by MySQL and stored verbatim as
  `'{"a":1}'` by MariaDB.

**Twin note:** `sample/docs/development/database.md` carries a curated,
PostgreSQL/MySQL-only prose extract of this matrix (per-`MgrFieldType`
fetch-type conversion, plus the `Char` padding difference above) for the
shipped audience, which has no access to this file. Update that doc too if
the underlying facts here change.

## Why this matters to a consuming project

The types are part of an API's observable contract, not an internal detail: a
column that returns `int` instead of `string` changes the JSON a REST endpoint
emits (`"id":11` versus `"id":"11"`), which client code can be parsing
strictly. It also changes the result of `===` comparisons and `is_string()`
checks against fetched values.

The framework's own position: **native PHP types are the better behaviour and
are not to be "fixed" back to strings** (`PDO::ATTR_STRINGIFY_FETCHES` would
do it and is deliberately not set). Normalize at the comparison instead — cast
the value that came back, which is what the model test suite already does
everywhere except the two assertions corrected in `review.md`.

## Adding an engine or driver

1. Bring up the engine's profile and set `local.env`'s DB block for it
   (`sample/docs/development/docker.md` has the per-engine values).
2. A `pdo/*` row additionally needs its subdriver added to
   `sample/docker/Dockerfile`'s `docker-php-ext-install` list and the image
   rebuilt — the shipped image carries no `pdo_*` extension on purpose. Revert
   the Dockerfile afterwards.
3. Run migrations, run the probe, paste the column types into a new pair of
   columns above. Record the probe's `emulate_prep` value with it.
