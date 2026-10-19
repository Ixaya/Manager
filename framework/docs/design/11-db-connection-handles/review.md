# Database connection handles — measurement and validation record

## How it was measured

PostgreSQL 18.6, MySQL 8.4.11 and MariaDB 12.3.3 (the bundled profiles), six
combinations: `postgre` and `pdo/pgsql`; `pdo/mysql` and `mysqli` on MySQL and
on MariaDB. `pdo/pgsql` was the control for `postgre`, which already shared one
link per DSN. SQLite and SQL Server were out of scope (tier 3): code changes
went through `MgrDriver`, with no live run.

- **Server sessions, not PHP objects.** A sampler outside the request read
  `pg_stat_activity` / `information_schema.processlist` every ~0.2 s, filtered
  to the app role. Exact opened/closed counts came from the server's connection
  log: PostgreSQL `log_connections`/`log_disconnections`, MySQL family
  `general_log` as a table.
- **Attribution.** Probes reported `pg_backend_pid()` / `CONNECTION_ID()` with
  the object ids of each DB object and its link. Shipped endpoints and
  `manager/tools` commands carried no probe code and were attributed by timing.
- **Probes** were authenticated, gitignored probe-module controllers, plus a
  probe subclass of `MGR_Migration_module_lib` that held after the lookup
  returned. The time-zone probes read the session offset back from the server,
  and counted `SET` statements twice: on the handle (`save_queries`) and in the
  server log.
- **Every change ran at the end of its batch** on all six combinations, on
  fresh volumes, with `db_debug` off (`APP_ENV=production`) and PHP on
  `America/Mexico_City` (−06:00).

The MySQL-family `general_log` stamps a session's `Quit` row in that session's
own `time_zone`, read back as UTC, so every app session's close time read 6 h
early once the session ran `SET time_zone`. Counts are unaffected; the
summarizer shifted those rows.

## Session counts

Opened sessions per case, from the start of the action. Opened equals closed
and none remained afterwards in every case, on every combination. Before =
released code.

| Case | `pdo/pgsql` | `postgre` | MySQL and MariaDB × `pdo/mysql` and `mysqli` |
|---|---|---|---|
| REST probe | 2 → 1 | 1 → 1 | 2 → 1 |
| `auth/api/profile` | 2 → 1 | 1 → 1 | 2 → 1 |
| REST probe ×100 | 200 (max 2) → 100 (max 1) | 100 → 100 | 200 → 100 |
| `profile` ×100 | 200 → 100 | 100 → 100 | 200 → 100 |
| Web request + `Domain` model | 1 → 1 | 1 → 1 | 1 → 1 |
| Model naming `'default'` + `Rest_key_model` | 3 → 2 | 1 → 1 | 3 → 2 |
| Login | 3 → 1 | 1 → 1 | 3 → 1 |
| `migrate` (every target) | 1 → 1 | 1 → 1 | 1 → 1 |
| `migrate <version> <module_key>` | 2 → 1 | 1 → 1 | 2 → 1 |
| `plan` | 1 → 1 | 1 → 1 | 1 → 1 |
| `plan` ×10 processes | 10 → 10 | 10 → 10 | 10 → 10 |
| `plan` ×50 in one process | 50 → 1 | 1 → 1 | 50 → 1 |
| `migrate <version> <key>` ×10 | 20 → 10 | 10 → 10 | 20 → 10 |

The `'default'` case stays at 2 by design: a name is its own handle.

## Engine behavior observed

- **One session per uncached handle** on `pdo/pgsql`, `pdo/mysql` and `mysqli`.
  `mysqli` does not share a link for an identical DSN; only `postgre` does.
- **Two handles on one group** (released code): on the non-`postgre` drivers,
  the second handle misses the first's uncommitted row, and its DDL fails
  after the lock timeout (`55P03` on PostgreSQL, error 1205 on the MySQL
  family). On `postgre`, the second handle sees the row, and its DDL runs inside
  the first's transaction and is rolled back with it. That `55P03` caused the
  exact-version migrate hang fixed just before this campaign is inferred: the
  mechanism was reproduced, the original hang was not re-run.
- **Release on drop.** On every driver but `postgre`, dropping a DB object
  closes its session at once. On `postgre`, a dropped link with the same DSN
  as a live one stays open until the process exits and is reused. A link to a
  different DSN closed when it was replaced; why ext/pgsql keeps only some
  links is inferred.
- **`lock_wait_timeout`** defaults to 31536000 s on MySQL 8.4.11 and 86400 s on
  MariaDB 12.3.3.
- **Session-offset limits.** MySQL 8.4.11 accepts −13:59 … +14:00. MariaDB
  12.3.3 accepts −12:59 … +13:00 only, and rejects +14:00 and +13:45 with error
  1298, keeping the previous value. PostgreSQL 18.6 (`SET TIME ZONE INTERVAL`)
  accepted −15:00 … +16:00. Real offsets in PHP's tzdb (2026–2027): −11:00 …
  +14:00, with `Pacific/Chatham` at +13:45 from late September to early April.
- **Reconnect per driver.** `close()` nulls `conn_id` on every driver. After a
  server-side kill, `reconnect()` behaves differently on each:
  - on `pdo/*` it is a no-op, and the link stays dead;
  - on `mysqli` it nulls `conn_id` when `ping()` fails;
  - on native `postgre`, `pg_ping()` kept the same link object on a new backend
    that ran on the server default time zone.

## Time-zone verification (all six unless noted)

| Check | Result |
|---|---|
| `rest->db`, main, `ion_auth_model` and a REST model are one object; session offset = PHP's | ✔ (4 env variants, including PHP changing zone after the REST handle opened) |
| Synced access sends nothing; a raw `date_default_timezone_set()` is corrected at the next access | ✔ on `''` and a named entry; `SET` counts on the handle equal to the server's |
| `close()` + access, and lazy reconnect + access, re-sync | ✔ |
| Native `postgre` `reconnect()` to a new backend, then an access, re-syncs | reproduced before the fix, ✔ after; controls unchanged |
| PostgreSQL: rollback, and a change and change back inside one transaction, end matching | both reproduced before the fix, ✔ after on both drivers; MySQL family keeps the offset across a rollback |
| DSN-string Model: offset = PHP's, untracked | ✔ |
| Killed session: logs, records nothing, recovers after `close()` | ✔ |
| Offset rejected on a working link: `RuntimeException` | ✔ on both MariaDB drivers; MySQL and PostgreSQL take the offset |
| `set_database_time_zone()`: `''`, PHP's offset or zone → no-op; other → `LogicException`; session unchanged | ✔ |
| Offset-valued `MGR_REST_TIME_ZONE` logs at `error` | ✔ |
| Migration runner: V1, V2a, V2b with two databases | ✔ (released code: V2a/V2b corrupted the second database) |
| `plan` writes nothing: tables and version rows identical before and after | ✔ with one and with two groups |

Sample PHPUnit: 138 tests, OK on every combination (369 assertions on
PostgreSQL, 360 on the MySQL family, where the PostgreSQL-only cases skip).
PHPStan level 5 and php-cs-fixer: clean.

## Never run, or by code only

- SQLite and SQL Server: the `set_database_time_zone()` no-op is by code.
- `database_cache('')` never following a replaced `$CI->db`: by code; no
  probe or test called it after a replacement.
- Forced `db_debug` reaching only `manager/tools` handles: by code.
- A sync over more than one entry whose server rejects the offset.
- The two known issues in `decisions.md`.
- The session counts on the final tree. The last correction to the
  PostgreSQL transaction guard re-ran the time-zone probes and PHPUnit on both
  PostgreSQL drivers, and the probes on MySQL `pdo/mysql`, but not the counts.
  That branch runs only on a PostgreSQL send inside a CI transaction, which no
  count case makes.

No PHPUnit test asserts the contract. The proposal "Durable regression tests
for the database handle contract" lists which of the checks above can be
made deterministic.

## Found and fixed along the way

- The migration runner, revisiting a connection, migrated into the previous
  connection's database and overwrote its version rows, silently (V2).
- `setup_model()` passed a `null` `rest_time_zone` to a `string` parameter: a
  `TypeError` with `MGR_REST_TIME_ZONE` unset.
- An offset-valued `rest_time_zone` was ignored silently.
- The Auth tests read Ion Auth's tables through `$CI->db`, the wrong connection
  once `AUTH_DB_CONNECTION` is set. Three non-auth tests relied on an earlier
  suite having loaded main before `dbforge()`.
