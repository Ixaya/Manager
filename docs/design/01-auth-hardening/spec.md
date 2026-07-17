# Auth hardening (2026-07) — scope

Initiative over the Ion Auth stack: the `BE_` backport
(`system/third_party/BE/Ion_auth.php`, `Ion_auth_model.php`), the package
subclasses (now `system/libraries/MGR_Ion_auth.php`,
`system/models/MGR_Ion_auth_model.php` behind package alias shims —
moved there at distillation time), and
`system/package/config/ion_auth.php`. Ran 2026-07-09 through 2026-07-14 in
the (gitignored, since deleted) `docs/workspace/03-auth-migration/` and
`05-auth-security/` sections.

## What was reviewed

Four independent review passes, ~60 auth findings total:

1. **Backport correctness** — CI3-vs-CI4 conversion pass over the `BE_`
   files: version leftovers, trivial defects, and behavior items needing
   confirmation (workspace items V1-V2, B1-B10, C1-C14).
2. **Parity vs the CI4 original** — method-by-method diff against the actual
   Ion Auth 4 sources (pasted into the reviewing agent's context; not in this
   repo). Classified deviations KEEP vs FIX and, critically, identified which
   apparent port bugs are upstream-inherited (PA1-PA5, PB1-PB7, PC-1..8, PD).
3. **Legacy regression vs the CI3 production code** — diff against the
   ion_auth v2-era legacy actually running in production (also external).
   Surfaced the deploy-gating items: silent argument traps, schema cutover,
   session load-order (LA1-LA8, LB1-LB11, LC).
4. **Security review** — F1-F9: stale tenant `client_id`, missing admin hash
   config, lockout posture, KDF timing oracle, unguarded password reset,
   SQL identifier injection surface, enumeration surfaces.

## Goals

- Fix real defects at the right layer (subclass/config preferred; `BE_`
  edits only as documented, re-verifiable exceptions).
- Make every KEEP-vs-FIX call explicitly instead of by omission, and record
  the decision.
- Leave migrating legacy consumers a safe path (MIGRATION.md guidance,
  library wrappers, loud failures over silent ones).
- Establish repeatable runtime verification (REST probes + CLI suites)
  across postgres, mysql, and mariadb.

## Where the knowledge lives now

- Conventions and invariants: `system/skills/ixaya-auth/SKILL.md`.
- `BE_` edit inventory and upstream-merge procedure:
  `docs/development/auth-upstream.md`.
- Consumer migration guidance: `MIGRATION.md` (Ion Auth items 1-5).
- Decisions with rationale: `decisions.md` here.
- Validation results and final state: `review.md` / `handoff.md` here.
