# Auth hardening — final state

Initiative CLOSED 2026-07-14. All findings resolved (fixed, or KEEP with the
decision recorded in `decisions.md`); everything verified per `review.md`.

## What shipped

- `BE_` fork: bug-fix batch, lockout trio, lazy `use_sessions()`, logout
  code-clearing fix, builder-state hygiene, fail-soft accessors, flat lang
  keys, seam methods. Full inventory: `docs/development/auth-upstream.md`.
- Subclasses: `disable_session()`, PB1 wrappers, repaired `get_client_id()`,
  `client_id` write/purge mirror in `set_session()`,
  `recheck_session_unset_keys()` append, `reset_password_with_code()`,
  `mgr_is_sql_identifier()` guards.
- Config: identity+IP lockout default, admin hash params, argon2 alias
  comment, `userExpire` clarification; `identityCookieName` removed.
- Docs/skills: `system/skills/ixaya-auth/SKILL.md` (conventions +
  invariants), MIGRATION.md Ion Auth items 1-5 (consumer traps),
  rest-controller skill password-reset note, helpers skill
  `mgr_is_sql_identifier` entry.

## Verification assets (keep, reuse)

- REST probes: `sample/application/modules/test/controllers/api/
  {Auth_migration,Auth_security}.php` — gitignored, one endpoint per item,
  re-runnable in isolation.
- CLI suites: `sample/application/modules/test/controllers/
  {Auth_validate,Auth_lib_validate}.php` — API-focused regression baseline
  (71 + 32 asserts), idempotent via preclean.

## Open / deferred

- Nothing open in code. Standing per-release runbook items are listed at the
  end of `review.md` (upstream-merge re-verify, deploy schema gate, consumer
  greps, engine sweep).
- Follow-up DONE 2026-07-14 (same distillation session): the package
  subclass code moved to `system/libraries/MGR_Ion_auth.php` /
  `system/models/MGR_Ion_auth_model.php`; the package files are now empty
  alias shims (`Ion_auth extends MGR_Ion_auth`), matching the framework's
  alias chain. Skill and auth-upstream.md updated. Note the full alias chain in
  a consuming project is now `Ion_auth` (app copy, optional) ->
  `Ion_auth` (package shim) -> `MGR_Ion_auth` -> `BE_Ion_auth`.
