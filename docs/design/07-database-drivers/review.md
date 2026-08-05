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
