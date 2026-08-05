# Driver matrix — native vs PDO performance

**Source of truth for what a PDO driver costs relative to its native
counterpart.** Every figure here comes from the same probe, measured through
the framework's own Model layer. Raw-driver microbenchmarks are deliberately
excluded: they measure the client library rather than this framework, and a
mixed table invites a reader to quote a number that was never true of the
Model layer.

Any figure elsewhere in `docs/` that disagrees with this file is stale and
should be corrected to match it, not averaged with it.

## What produces these rows

`probes/pdo_benchmark/run` — a gitignored CLI probe. It opens **both** a native
and a PDO connection to the current engine in one process, from the live
connection's own credentials, then drives an identical workload through
`MY_Model` over each. Because both variants run in one process against one host,
a ratio is meaningful even when absolute timings are not comparable between
sessions.

```bash
# in sample/. 51 interleaved rounds + 300 contiguous per variant
./docker_manage.sh -e local exec php bash /var/www/html/bin/cli_run.sh probes/pdo_benchmark/run 51 300

# 4th argument forces PDO::ATTR_EMULATE_PREPARES => false (the pre-fix control)
./docker_manage.sh -e local exec php bash /var/www/html/bin/cli_run.sh probes/pdo_benchmark/run 51 300 0 1
```

Method, and why each choice is there:

- **Interleaved rounds give the ratios.** One round of each variant per
  iteration, so host-load drift lands on both variants rather than only the
  second. Medians over the rounds, never a single sample.
- **A contiguous load phase gives resource attribution.** Long enough for a
  host-side sampler to attribute container CPU to one variant, which the
  interleaved phase deliberately makes impossible.
- **Everything goes through `MY_Model`**, never raw `$this->db` — what matters
  is the framework's actual usage, including the query builder's own cost.
- **`save_queries` is off for both.** CI3 otherwise accumulates every query
  string and its timing, showing up as memory the driver never allocated.
- **The PDO config is built by `mgr_apply_pdo_dsn()`**, the shipped helper, so
  the run measures real behaviour rather than a local copy of the DSN logic.
- The workload per round: `count_all` · `get_all` (200-row list) · 25× `get()`
  · `get_all_in` (50 ids) · `get_all_like` · 10× `insert` · 10× `update_where`
  · `delete_where`. The delete restores the row count, so neither variant ever
  queries a table the other grew.

Backing files, in the gitignored `sample/application/modules/probes/`:
`controllers/Pdo_benchmark.php`, `models/Probe_bench.php`,
`migrations/default/20260804140000_Probe_bench.php`.

## The one configuration fact that governs these numbers

`mgr_apply_pdo_dsn()` sets `PDO::ATTR_EMULATE_PREPARES => true` for the `pgsql`
subdriver. Without it, PDO_PGSQL prepares every statement server-side — an
extra round trip per statement, since CI rebuilds the SQL string on every call
and so never reuses a prepared statement. It is scoped to `pgsql` because
`pdo_sqlite` throws on the attribute and `pdo_mysql` already defaults it on.

There is no security cost: CI binds no parameters on any driver — it escapes
values and splices them into the SQL string, and `pg_query_params`/`bindValue`/
`bindParam` appear nowhere in its database layer — so the server-side prepare
was guarding nothing. Verified at protocol level: with `log_statement='all'`,
a Model-layer read under the shipped config logs as `LOG: statement: …` (simple
protocol), identical in shape to the native driver's.

The **emulation-off** rows below are therefore a control, not a supported
configuration. They exist so the shipped setting's effect is attributable.

## The matrix

Measured 2026-08-05, 51 interleaved rounds + 300 contiguous per variant. PHP
8.4.24; PostgreSQL 18.4, MySQL 8.4.11, MariaDB 12.3.2. Ratios are
`pdo / native`; below 1.00 would mean PDO was faster.

| engine | emulation | count | list | get()×25 | in(50) | like | insert×10 | update×10 | delete | **total** | load phase |
|---|---|---|---|---|---|---|---|---|---|---|---|
| PostgreSQL | on *(shipped)* | 1.06 | 0.88 | 0.96 | 0.94 | 0.90 | 1.03 | 1.01 | 0.99 | **1.03** | 1.05 |
| PostgreSQL | off *(control)* | 1.10 | 1.11 | 1.71 | 1.51 | 1.26 | 2.07 | 1.60 | 1.60 | **1.65** | 1.99 |
| MySQL | on *(shipped)* | 1.01 | 1.06 | 1.11 | 1.00 | 1.01 | 1.03 | 1.06 | 0.95 | **1.04** | 0.98 |
| MySQL | off *(control)* | 1.10 | 1.25 | 1.83 | 1.29 | 1.12 | 1.09 | 1.10 | 1.08 | **1.22** | 1.22 |
| MariaDB | on *(shipped)* | 1.00 | 1.03 | 1.01 | 1.01 | 1.01 | 1.12 | 1.12 | 1.13 | **1.08** | 1.40 |
| MariaDB | off *(control)* | 1.09 | 1.19 | 1.81 | 1.37 | 1.12 | 1.28 | 1.27 | 1.22 | **1.35** | 1.42 |

**As shipped, every PDO driver is at parity with its native counterpart** —
totals 1.03-1.08×, i.e. within a few percent, which is inside this probe's
run-to-run variation. The honest reading is "no meaningful difference", not
"faster" or "slower": a repeat of the PostgreSQL emulation-on row in a separate
session read 0.95×, so single-run deviations of ±5% in either direction are
noise, not signal. Do not quote a sub-1.00 figure as PDO being faster.

**Emulated prepares are what decides this, on all three engines.** Forcing them
off moves the total from ~1.03-1.08× to 1.22-1.65×, and the cost concentrates
in the same places every time: `get()×25`, `insert×10`, `update×10` and the
bulk load phase — operations that issue many small statements. Bulk
single-statement reads (`count`, `list`, `like`) barely move. That is the
signature of a per-statement round trip, not per-row overhead.

PostgreSQL pays the largest emulation-off penalty (1.65× total, 1.99× load
phase) and is the only engine where the framework has to set the option
explicitly — `pdo_mysql` already defaults it on, which is why MySQL and MariaDB
are at parity out of the box and only degrade when the control forces the
option off.

## Caveats that must travel with any citation

- **Unpooled, same host.** Every figure is a direct connection from the app
  container to a database container on one machine. Whether the picture holds
  behind PgBouncer or a managed proxy is unvalidated. Do not cite these
  numbers as rationale for a pooled deployment.
- **Ratios, not absolutes.** The millisecond values depend on host load and the
  container's CPU allocation; only the within-session ratio is portable.
- **Per statement, not per row.** Where a gap appears it scales with query
  count, not result size — bulk single-statement reads and loops of small
  statements behave differently, which is why the per-operation columns are
  kept rather than only a total.

## Adding an engine or driver

1. Set `local.env`'s DB block for the engine and bring up its profile; a fresh
   volume (`down -v`) is needed when changing engine.
2. Add the engine's PDO subdriver to `sample/docker/Dockerfile`'s
   `docker-php-ext-install` list and rebuild — the shipped image carries none on
   purpose. Rebuild the `tools` image too if you also intend to run PHPUnit
   (`--profile tools build tools`); it is a separate image and a plain `build`
   misses it. Revert the Dockerfile afterwards.
3. Run migrations, then the two probe invocations above, and paste both rows.
   Record the engine and server version from the probe's own header.
