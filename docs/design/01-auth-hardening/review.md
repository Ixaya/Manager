# Auth hardening — validation record

How the findings and fixes were verified. All runs used the `local` Docker
instance with `-b -m` binds (live tree, no baked image), real API keys from
the normal login flow (keyless probe hits must 403 — auth never bypassed),
and teardown with volumes afterwards.

## Findings validation (2026-07-11)

Every finding (~102 across the campaign) was checked against the actual code
before any fix: existence check, behavior check, verbatim baseline quote,
verdict classification. Results:

- Zero hallucinated references across all auth items.
- Several severity corrections: C2's blast radius narrower than described;
  C11's "null deref" is a PHP 8 warning, not a fatal; PC-7 drifted (one of
  three sites); LB3 had a live in-repo hit (the sample login controller's
  discarded 4th arg).
- External-source caveat: the CI4 original and the legacy production code
  are not in this repo; cross-repo halves of those claims were verified
  backport-side only.

## Runtime verification

- **REST probes** (gitignored, repeatable):
  `sample/application/modules/test/controllers/api/Auth_migration.php`
  (`/test/api/auth_migration/{b8,b2b3,lb1,lb3,pb1,c9,pb2,c2,c10,c5c6,all}`)
  and `Auth_security.php` (`/test/api/auth_security/{f1,f6,all}`). Notable
  catches during probe runs: the postgres burned-`LASTVAL()` trap (forced
  the C2 fix to guard the INSERT return, not the id), the
  `login()`-clears-forgotten-code lifecycle fact (F6), and the
  model-instance aliasing trap (probe state must be set and read on the
  library's own model instance).
- **KDF timing symmetry (F4):** absent-user vs wrong-password within noise
  (was ~2x); locked-out answers in ~0.1 ms with no KDF and no users query.
- **CLI validation suites** (`Auth_validate.php` model layer,
  `Auth_lib_validate.php` library layer, run via
  `bin/cli_run.sh test/auth_validate/run`): rebaselined API-focused
  (session/cookie asserts removed), then extended with five probe-gap
  regression tests (register insert-failure, lockout branch, update_group
  edges, remember-me DB side, result-accessor guards). Final: model 71/71,
  library 32/32.
- **Engine matrix (2026-07-14):** both CLI suites AND the full REST probe
  sweep green with zero divergence on PostgreSQL 18.4 (`postgre`),
  MySQL 8.4 and MariaDB 12.3 (`mysqli`). The register-failure guard is
  proven on both failure semantics (mysqli `insert_id() = 0` vs postgres
  burned `LASTVAL()`).

## Closing review (2026-07-14)

Independent session re-diffed every fixed item against its recorded baseline
quote: all fixed-verified, none regressed; the do-not-regress security
properties hold verbatim; the consolidated call-site greps and the
zero-`IonAuth.`-prefix grep are clean (remaining hits are the probe module
calling the current API); every `set_error`/`set_message` key resolves in
all three lang files. phpstan and php-cs-fixer clean (operator-run).

## Standing runbook items (per release / per deploy)

- Re-verify the `BE_` edit inventory after any upstream Ion Auth merge —
  procedure in `docs/development/auth-upstream.md`.
- Pre-deploy schema gate on the target DB: three `*_selector` columns
  present, `salt` absent — note the physical table is `user`, singular
  (migration `20260213175009_Ion_auth_v2.php`).
- Run MIGRATION.md's consumer greps on the deploy target's app tree before
  cutover; compare the target's legacy `identity_extra_columns` against its
  instance env (`AUTH_IDENTITY_EXTRA_COLUMNS`).
- Pre-release engine sweep (CLI suites + probe `all` on postgres, mysql,
  mariadb) — driver quirks proved to matter.
