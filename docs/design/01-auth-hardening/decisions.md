# Auth hardening — decisions

Operator decisions with their rationale, condensed from the workspace
records. Item ids (C10, F4, LB3...) are the workspace numbering, kept for
traceability to commits and probe names. The resulting rules live in
`system/skills/ixaya-auth/SKILL.md`; the `BE_` edit inventory in
`docs/development/02-auth/upstream.md`.

## Structural

- **2026-07-12: `BE_` files may be edited, as one-commit documented
  exceptions.** The subclass-preference rule stands, but genuine defects in
  the fork with no clean subclass seam are fixed directly in the `BE_`
  files, batched in single coherent commits, recorded in `upstream.md`, and
  re-verified after any upstream merge. Scattering them across unrelated
  commits is forbidden.
- **2026-07-12: lang keys are FLAT (CI3 style).** The half-finished upstream
  `IonAuth.` prefix migration was completed in the flat direction because
  the production legacy lang files are flat (LA8 evidence). Two orphan keys
  remapped to existing ion_auth keys rather than adding new lang entries.
- **2026-07-13: sessions are re-checked at call time, but never
  force-loaded.** `use_sessions()` replaces the constructor-frozen flag
  (fixes the load-order fragility) yet deliberately does NOT do legacy's
  `load->library('session')` — force-loading would permanently flip the
  intentional sessionless/API mode into session mode. The model owns the
  check; the library delegates (single source of truth).
- **2026-07-13: the legacy `login()` 4th arg is replaced by
  `disable_session()`, not restored.** An explicit opt-out on the model
  (reached via the library `__call`) keeps the `BE_` signature untouched and
  makes REST endpoints declare intent instead of relying on a positional
  flag.

## Parity calls (vs upstream CI4)

- **PB1: restore the id-less convenience as LIBRARY wrappers.**
  `get_users_groups()`/`add_to_group()` keep required-id model signatures;
  the subclass library adds session-fallback wrappers. `get_users_groups()`
  coerces a missing id to 0 (chainable empty result, never a bare array) so
  no caller's `->result()` chain breaks.
- **PB3/F4/C10 (decided once, as a trio): lockout does ZERO hashing and runs
  BEFORE the credentials query; every other failed login costs exactly one
  KDF op.** Lockout state is already disclosed by its distinct error, so
  hashing there only creates a CPU-amplification vector; the dummy hash
  moved into the absent-user branch to equalize timing with
  `verify_password()`. Deliberate deviation from upstream (which hashes on
  lockout) — do not restore parity.
- **PB6: `is_admin()` keeps no `logged_in()` gate** — coherent with
  sessionless/API mode; the method answers "is X in the admin group", and
  callers wanting caller-authorization combine it with `logged_in()`.
- **PB7: `cacheUserInGroup` reverted to `protected`.** The `public` was a
  CI3-era leftover (library-side `in_group()` needed it); `in_group()` now
  lives in the model. Operator swept external projects: no consumer.
- **PA4: Postgres is fully supported with bare `insert_id()`** — CI3's
  postgre driver implements it as `SELECT LASTVAL()`; the sequence-name arg
  is a CI4-driver requirement only. Runtime-proven; no MySQL-only
  commitment.
- **C13: the ungrouped `or_where` in `clear_login_attempts()` is
  deliberate** (one delete clears the identity's attempts AND purges expired
  rows table-wide) — documented with an inline comment instead of "fixing"
  the precedence.

## Legacy calls (vs the CI3 production code)

- **LB2/F1 (the `client_id` knot, fixed as a pair, LB2 first):**
  `get_client_id()` was repaired (dead `allow_session` guard replaced with
  `use_sessions()`) rather than deleted, because external legacy projects
  migrating to 2.0 use it. F1 then closed the lifecycle: cleared on
  deactivation via the `recheck_session_unset_keys()` seam, written/purged
  on login via a `set_session()` mirror (`property_exists` semantics: a
  selected-but-empty column purges stale values; an unselected column leaves
  project-written keys alone). A full session-teardown override was
  REJECTED as too invasive for a mid-request recheck.
- **LB9: `'argon2'` maps to ARGON2ID (legacy semantics), with a new explicit
  `'argon2i'` case** — upstream's argon2-to-ARGON2I mapping would silently
  downgrade migrating projects' hashes.
- **LB11: `register()` no longer hardcodes `'username' => $identity`.**
  Merge order let the CI4 line clobber an explicitly-passed
  `additionalData['username']`; operator's email-identity projects pass
  non-email usernames there. Only the configured identity column is
  framework-owned. Deliberate deviation from CI4.
- **LB5/LB7/LB8/LB10: KEEP the new signatures/formats** (object returns,
  template rendering, strict types, remember-only cookie cleanup). Loud
  failures (TypeError, `__call` throw) are acceptable migration surfaces;
  silent ones got MIGRATION.md entries (items 2b, 2c) or code fixes.
- **C3/C4: dead code deleted, not preserved** — unreachable `__call`
  aliases; the never-written identity-cookie auto-login block plus its
  config key. Operator grep across external projects found zero callers.

## Security calls

- **F2 (option i): add the missing admin hash config keys** so enabling
  `useRoleBasedHashing` cannot fatal — but their env keys stay OUT of the
  env samples (the framework disables role-based hashing on purpose; the
  config keys are a guard, not a feature to advertise).
- **F3 (option c): `trackLoginIpAddress = true` by default.** Validated
  first: the flag scopes lockout to the identity+IP PAIR, never IP alone,
  so it strictly narrows lockout. Accepted tradeoffs: IPs stored in
  `login_attempt` (retention consideration), per-IP attempts for rotating
  attackers.
- **F6 (option B + doc note): atomic `reset_password_with_code()` in the
  subclass library.** Identity comes from the code's user row by
  construction. No explicit clear on success — single-use already holds via
  `set_password_db()` nulling the code; the lingering selector is a
  deliberate trace (cleared on next successful login). Raw
  `reset_password()` kept for BC, gated-usage rule documented in the
  rest-controller and auth skills.
- **F7: allowlist-and-throw, NOT CI escaping.** `escape_identifiers()`
  returns values containing parens/quotes unmodified, so it decorates but
  never rejects. Shared as `mgr_is_sql_identifier()` (bool helper; callers
  throw their own exceptions), used by both Ion Auth select builders and
  `MGR_Model_Dyn`.
- **F8: `userExpire = 1` is deliberate** ("semi-disabled": 0 means max
  lifetime, not off) — fixed by comment, value kept.
- **F9: neutral registration failure message.** Accepted residual: success
  still auto-activates and logs in, so completing a registration reveals
  prior non-existence by construction — a sample cannot dictate an
  email-activation flow.
- **Do-not-regress invariants** (uniform hash cost, selector/validator
  design, session regeneration, one-KDF-op failure symmetry) are recorded
  in the ixaya-auth skill as permanent constraints on future cleanups.
