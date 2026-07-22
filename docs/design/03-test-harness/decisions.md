# Test harness — decisions

Design decisions with rationale. The current state is in `spec.md`; how it was
validated is in `review.md`.

## Integration tests against a real database

CI3 / Ion Auth (global super-object + query builder) is not practically
mockable, and cross-engine correctness needs a real engine. Tests hit the
instance's normal dev DB; namespaced, self-cleaning fixtures keep that safe. A
dedicated `_test` database was considered and parked — revisit only if a test
ever needs truncation-level isolation.

## Environment split

Committed `.env.testing` carries the profile-independent config (`APP_ENV`,
`APP_TIMEZONE`, `CF_LOG_THRESHOLD`); gitignored `.env.testing.priv` carries the
full DB block (`DB_HOST`/`DB_PORT`/`DB_DRIVER`/`DB_NAME`/`DB_USER`/`DB_PASS`/
`DB_CHAR_SET`/`DB_COLLATION`) — not just `DB_PASS`, because `DB_HOST` and
`DB_DRIVER` differ per local DB profile (mysql/mariadb/postgres/sqlite) and
switching profiles shouldn't mean editing a tracked file. `.env.sample.testing.priv`
is the committed template showing the profile blocks. The bootstrap forces
`CI_ENV=testing`, so `Env_lib` loads `.env.testing` instead of `.env`; process
env still outranks both for one-off host-side overrides.

`.env.testing` sets `APP_ENV=development` (not `testing`/`production`). File
selection keys on `CI_ENV`, but `ENVIRONMENT` — and therefore error visibility
— keys on `APP_ENV` first (`public/index.php`). `development` is chosen so PHP
errors and a broken CI3 boot surface in the phpunit output instead of failing
silently under `display_errors=0`; it is a separate channel from
`CF_LOG_THRESHOLD`, which independently keeps CI's own log files quiet. Flip to
`testing` to silence errors.

## The `MGR_Config` / `MGR_Lang` seam

PHPUnit includes the bootstrap inside a function, so CodeIgniter's "globals"
(`$CFG`, `$LANG`) are function-scoped and MX's mid-boot `$CFG = new
MX_Config()` swap never reaches the `load_class` cache — `CI::$APP->config`
stayed a plain `CI_Config` and module config reads fataled. Subclassing
(`MY_Config` → `MGR_Config` → `MX_Config`, same for Lang) makes `load_class()`
cache an MX-capable instance from the first call, so the swap becomes a no-op in
normal boots and correct under PHPUnit. Consuming projects must add both shims
to adopt the harness; recorded in `MIGRATION.md`.

## One clean example

The pre-harness example tests extended `PHPUnit\Framework\TestCase` directly,
re-implemented `__get`, and asserted nothing — teaching the pattern the harness
replaced, in the sample every project copies. Collapsed to a single DB-free
`CITestCase` example that doubles as the harness smoke check; the `Auth` suite
is the DB-backed reference.

## smoke vs probes boundary

Committed, generic, read-only wiring checks are the **smoke** module (baked into
local images via `INCLUDE_SMOKE_MODULE`); disposable, campaign-specific,
gitignored checks are **probes** (framework-repo only, never shipped). A probe
graduates to smoke only if it proves generic.

## Deferred / follow-up

- **`ixaya-testing` skill — deferred.** The authoring guide
  (`sample/docs/development/testing.md`) ships now; a skill waits until the
  pattern has been exercised in a real consuming project, so an immature
  convention does not propagate.
- **`Auth_validate` CLI probe retained.** The 586-line probe was converted to
  the `Auth` suite but kept in `modules/probes/` for a side-by-side read before
  removal; its fate is an open operator decision.
