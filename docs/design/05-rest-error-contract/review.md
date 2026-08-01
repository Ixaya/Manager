# REST error contract — validation record

Every claim about what a client receives or what an engine accepts was
**observed**, not deduced. The initiative inverted its own premise twice under
reading and then under a probe, so a verdict table that cannot distinguish
"observed" from "deduced" was treated as worth less than none.

## Method

Throwaway probe controller `probes/api/error_boundary` (gitignored), one
method per trigger — 15 by the end: `warning`, `exception`, `error`,
`constructor?boom=1`, `sql`, `sql_builder`, `sql_all`, `sql_update`,
`sql_insert`, `sql_debug_on`, `sql_bad_credentials`, `not_found`,
`hard_error`, `fatal`, `fatal_call`; the five `sql*` methods also take
`?db_debug=1`/`?db_debug=0`.

Stack bound to the working tree, **both binds confirmed in-container** by
grepping the edited symbols before trusting any result. Real `X-API-KEY` from
`claim_admin` → login on every run, never bypassed; a keyless call hit `403`
every time. `chown -R www-data:www-data /var/log/manager` before every log
read. Final capture 2026-08-01: all four combinations of
`{postgres, mysql} × {production, development}`, fresh DB per engine.

Gates at close: PHPStan level 5 `[OK] No errors`; `php-cs-fixer check --diff`
`0 of 299`.

## Production — identical on both engines except one row

| Probe | HTTP | Bytes | Body |
|---|---|---|---|
| `warning` | 200 | 59 | gated, no leak |
| `exception` | 500 | 54 | generic envelope |
| `error` (TypeError) | 500 | 54 | generic envelope |
| `sql`, `sql_update`, `sql_insert` | 200 | 56 | `data: false` |
| `sql_all` | 200 | 55 | `data: null` |
| `sql_builder` | 200 | 80 | `result_type: "bool"`, `num_rows: null` |
| `sql_debug_on` | 500 | 54 | generic envelope — the SQL leak stays closed |
| any `sql*` with `?db_debug=1` | 500 | 54 | generic envelope, no SQL |
| `not_found` | 404 | 103 | full 404 envelope — 4xx never suppressed |
| `hard_error` (`show_error()` 5xx) | 500 | 54 | generic envelope |
| `fatal_call` | 500 | 54 | generic envelope — PHP 8 raises `Error`, so the dispatch hook takes it |
| `constructor?boom=1` | 500 | **0** | accepted gap |
| `fatal` (OOM) | 500 | **0** | accepted gap |

The one engine difference: `sql_bad_credentials` is `500`/54/generic on
Postgres (the connect failure raises) and `200`/69/success on MySQL under mode
(ii).

`status` is `0` on every failure row and `1` only on 2xx.

## Development — the regression guard

Every row `500` with full detail in the nested `error` shape, engine-
independent rows identical on both engines: `warning` 169 B, `exception`
175 B, `error` 341 B, `hard_error` 105 B, `fatal_call` 203 B,
`constructor?boom=1` 178 B, `not_found` 404/103 B. The six DB rows differ only
in the driver's own text and every one names the failing SQL in `error.query`
(Postgres 258–350 B with `errno: null`, MySQL 252–369 B with a populated
`errno`).

The single inverted row: **`fatal` (OOM) answers HTTP 200 with a 792-byte HTML
dump** — worse than production. Accepted, documented.

Two things this establishes. **The Postgres/SQLite warning gate holds and
MySQL is the control** — all six Postgres rows render CI's `error_db` envelope
naming the SQL, while MySQL's rows are unchanged from the pre-fix capture
apart from the nesting. And **the gate is genuinely narrow**:
`sql_bad_credentials` still renders each driver's own connect message, which a
gate scoped to the whole database directory would have replaced with CI's
generic "Unable to connect…".

## `db_debug` forced off in development — mode (ii) as contracted

Both engines, all five DB probes answer their production shapes: `200` with
`data: null` / `data: false` / `result_type: "bool"`. Recorded so it is not
re-raised — the envelope still reads `status: 1` because the probe reports
what the model returned without checking it, which is the contract being
exercised, not a defect.

## Logging — one line per suppressed path, no double-logging

Marker counts across `{postgres, mysql} × {production, development}`:

| Marker | pg/prod | pg/dev | my/dev | my/prod |
|---|---|---|---|---|
| in-action exception | 1 | 1 | 1 | 1 |
| constructor throw | 1 | 1 | 1 | 1 |
| TypeError | 1 | 1 | 1 | 1 |
| `show_error()` 5xx | 1 | 1 | 1 | 1 |
| `Query error` (driver) | 11 | 11 | 13 | 12 |
| `Allowed memory size` (OOM) | 1 | **0** | **0** | 1 |
| 404 | 0 | 0 | 0 | 0 |

The in-action exception row is the headline: its baseline was **0 in both
environments** — the originating defect. The OOM zeros in development are the
known inversion; the 404 zeros are deliberate.

`pg_query` appears 11 times in the Postgres development log while rendering
none of them, which confirms the display gate and the log path are
independent.

**Verified organically as well as by probe.** A single env typo,
`DB_DRIVER=bogus`, reaches a bare `show_error('Invalid DB driver')` on a plain
login: production answered `500` with the generic envelope and wrote exactly
one line. Before the fix that request logged nothing at all — a mistyped
driver was completely silent in production.

## Not verifiable live, and why

- **`Dashboard::index_get`'s catch branch** — forcing `count_all()` to throw
  needs DB-level fault injection. Verified by code read; a key/HTTP-code
  change with no regression risk.
- **`clear_login_attempts()` and `delete_user()` not-found branches** — both
  return `true` on a zero-row query, so the branch is unreachable. That is the
  model-failure-signals proposal, not a defect in this work.
- **The controller-side `data === null` guards** — a migration downgrade was
  attempted to force a real query failure and abandoned on evidence: dropping
  `user`/`user_group` fails the auth check in `MGR_Rest_Controller`'s
  constructor, so both endpoints returned a body-less 500 before their action
  ever ran. The underlying mechanism was already proven live at
  `execute_list()` (a missing column returning `null` across both engines and
  both environments); the controller check is a deterministic conditional on
  top of it.

## Confounds that nearly produced false results

- **A root-owned log file silently disabled all logging.** A CLI command run
  via `docker exec` as root created the day's log file `root:root 0644`, and
  php-fpm runs as `www-data`; CI opens the log with a silenced `fopen()` and
  discards the result, so every write was dropped with no symptom. The first
  sweep found nothing for *any* path and would have "confirmed" a far broader
  finding than the real one. This is why `manager/tools/log_check` exists and
  why `mgr-live-probes` now states that an absence is not evidence until the
  channel has produced a positive.
- **Switching engines needs `DB_HOST` and `DB_PORT`, not just `DB_DRIVER`.**
  Changing only the driver produced no error: the CLI migrate exited `0` with
  no output on any channel. `manager/tools/env_check` is what identified it.
  That silent exit is itself now the cli-silent-failure proposal.
- **Probe file line numbers are volatile** — adding a method shifted a
  reported line by six between captures. Never assert on them.
