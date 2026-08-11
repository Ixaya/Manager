# Database drivers — validation record

Condensed from the closing review of
`docs/workspace/19-database-pdo-integration` (2026-08-05). Full provenance,
per-finding detail, and the raw log evidence live in that campaign's
`review.md` while the workspace exists; this is the permanent summary.

## What was verified

Native `postgre`/`mysqli` and `pdo/pgsql`/`pdo/mysql`, each on a fresh
volume: `migrate` clean, the sample's full PHPUnit suite (86 tests / 209
assertions) green, gates (`phpstan` level 5, `php-cs-fixer`) clean. Verified
by grepping an edited symbol inside the running containers before trusting
any result — an absence is not evidence until the channel has produced a
positive.

## Findings

1. **A verification claim was false.** An earlier session recorded the full
   suite as green under `pdo/pgsql`; it had actually run on native
   `postgre` the whole time, because the suite reads its DB config from
   `sample/.env.testing.priv`, a different file than the one the campaign
   had switched (`sample/docker/env/local.env`). Corrected in place; the
   two-file split was already documented, but the agent-facing trap of
   confirming which driver a *test run* actually used — not just which env
   file was edited — is now in the `mgr-docker-ops` skill, alongside the
   sibling "confirm the bind actually took" check.
2. **`modify_field_timestamp()` silently created no trigger under
   `pdo/pgsql`** — CI's PDO driver rejects the combined
   `DROP TRIGGER IF EXISTS …; CREATE TRIGGER …` call that native `postgre`
   tolerated. Fixed by splitting into one statement per `query()` call (see
   `decisions.md`); a corpus sweep confirmed it was the only genuinely
   multi-statement emitter in `system/` and `sample/`.
3. **Integer, float, and (Postgres) boolean columns change PHP type under
   PDO** — a real, permanent divergence rather than a defect. Two model
   tests had hard-coded native `postgre`'s stringified representation into
   their expected values, which made the soft-delete regression they exist
   to catch pass vacuously on the other driver; fixed by normalizing both
   sides of the comparison. Full matrix: `driver-matrix-types.md`.
4. **The measured ~2× Postgres-over-PDO cost was a togglable default, not a
   property of PDO** — see `decisions.md`'s methodology-correction entry.
   Every doc that had published the wrong figure and mechanism was
   corrected in the same session.

## Verdict

The connection-layer work holds: `fromCI()`'s `subdriver` param,
`mgr_apply_pdo_dsn()`, and the migration-builder fix all verified across
the full engine × driver matrix that was run. The default-driver
recommendation stands, restated on non-performance grounds once the ~2×
argument was retracted (`decisions.md`).

## Schema type mapping — validation record (campaign 22)

Condensed from the closing review of
`docs/workspace/22-timestamp-timezone-mapping/` (2026-08-10). Full
provenance and per-objective evidence live in that campaign's three
`review.md`/`handoff.md` pairs while the workspace exists; this is the
permanent summary.

### What was verified

PostgreSQL and MySQL, native drivers, through the real model
`get()`/`select()` read path (not a hand-rolled cast) — the `Timestamp`
repoint, `mgr_create_date_time()`'s timezone normalization, the `Text`/
`Float`/`UNSIGNED`-widening schema fixes, and the new
`mgr_format_date_time_iso()` helper. MariaDB skipped as redundant with
MySQL for this batch (same wire protocol, no divergent case touched). SQL
Server is code-inspection-only throughout, blocked on `pdo-dblib-vendor-gaps`.
Gates (`phpstan` level 5, `php-cs-fixer`) clean throughout.

### Findings

1. **The `UNSIGNED` docblock row's specific claim was a false positive, but
   validating it surfaced a real bug.** The review claimed PostgreSQL/SQL
   Server "ignore" `unsigned: true`; reading the code showed CI3's own
   vendored `postgre_forge`/`sqlsrv_forge` `_attr_unsigned()` genuinely is a
   no-op on both engines — an upstream bug, live-confirmed, not a documentation
   gap. Fixed by re-dispatching to the next-widest `MgrFieldType` inside
   `MgrFieldBuilder` itself (`decisions.md`).
2. **The `Float`-defaults-to-double-precision claim rested on recollection,
   not a code read, and was live-verified before fixing** — PostgreSQL and
   SQL Server's bare `FLOAT` (no precision argument) do default to
   `FLOAT(53)`/double precision by each engine's own documented default,
   confirmed live rather than assumed.
3. **The engine read-back shapes for `Timestamp` and `Uuid` were captured
   through the framework's own model read path, not a hand-rolled
   `::text`-cast**, closing a gap the earlier objectives' own evidence had
   left (their logs captured schema types, not what a caller actually gets
   back). Full matrix: `driver-matrix-timestamp-uuid.md`.
4. **One deferred question was written up as its own proposal rather than
   left as workspace prose:** whether `mgr_get_now_date_time_sql_format()`
   should write an offset-explicit literal, blocked on an unproven MySQL
   strict-mode literal-parsing fact — `timestamp-write-format-atom`
   (`handoff.md`'s "Remaining open work").

### Verdict

The schema-mapping work holds on every item live-tested: no bare `TIMESTAMP`
fallthrough on PostgreSQL's `Timestamp` branch, no `DATETIME2` on SQL
Server's, no bare `TEXT` on SQL Server's plain `Text` branch, no bare
`FLOAT` shared between `Float` and `Double` on the same engine, and the
`UNSIGNED` widening matches the corrected docblock table exactly. SQL
Server's `DATETIMEOFFSET`/`NVARCHAR(MAX)`/`FLOAT(24)` mappings are
code-correct by inspection but remain unvalidated live, same standing gap
as the rest of this initiative's SQL-Server coverage.
