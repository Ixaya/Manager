# Framework homologation — decisions

Operator decisions with rationale, condensed from the workspace records.
Item numbers (03 #n, 04 #n, 05 #n) are the workspace objective numbering,
kept for traceability.

## The `BASEPATH` guard

- **2026-07-30 (03 #1/#2): the rule was corrected before it was enforced.**
  The written standard demanded a guard in every PHP file and 26 files
  disagreed with it. Rather than mass-apply, the categories were counted and
  the rule rewritten to match, then applied. This is the inverse of the usual
  "the rubric must already contain the rule": where a standard demands more
  than it should, correcting it first is the fix, and mass-applying a rule
  nobody has justified is the failure mode.
- **2026-07-30 (03 #1): decided on measured adoption per category, with the
  mechanism argument overriding the rate where they disagreed.** Scope for the
  count included `extras/` — for the count only, because it holds the largest
  body of real historical code. Result: **seeds and views are exempt**
  (0/1 and 10/54, and all ten view hits are unstripped CI3 stock error
  templates — no project-authored view ever carried one); every other category
  keeps the guard. Controllers (46.7%) and models (59.1%) were kept against
  their rate because they are directly routable and the low numbers are legacy
  debt. Migrations (46.7%) were kept on the operator's argument that they now
  live inside HMVC module trees, which are more exposed than a flat top-level
  folder, and the guard is zero-cost either way.
- **2026-07-30 (03 #1): the premise was partly falsified and the rule kept
  anyway.** The shipped nginx config already denies any stray `.php` outright,
  so the guard is not the sole defence it was assumed to be. It stays as
  defence-in-depth for deployments without that hardening — legacy Apache, or
  an nginx config predating the ruleset.
- **2026-07-31 (03 #3): the three boot-path files take the guard, verified by
  execution rather than reading.** A wrong guard there white-screens every
  consuming project on upgrade, so it was proven with an HTTP request, a CLI
  invocation and the full scaffold PHPUnit suite before being kept. The risk
  turned out narrower than assumed — `bin/cli_run.sh` execs the same
  `public/index.php` that HTTP uses, so the two entry points cannot define
  `BASEPATH` at different points — but it was confirmed, not assumed.
- **2026-07-31 (03 #5): the majority one-liner is canonical, and the 23
  files using the older block form convert on touch — no dedicated pass.**
  Recorded as standing policy in the skill so the next homologation pass
  treats it as an open mechanical item rather than re-litigating the style
  choice.
- **2026-07-31 (03 #2, second amendment): position is part of the rule** —
  within the first 3 lines, directly below `<?php`, never below a header
  comment. Added because a guard sitting past a header block is invisible to
  any line-limited check, which is exactly what produced the duplicate guards
  during the apply pass.
- **2026-07-31 (03 #2): the reasoning does not travel with the rule.**
  A first pass put the full defence-in-depth argument into both `AGENTS.md`
  and the skill, with the skill pointing outward "for the reasoning".
  Corrected: `mgr-code-style` is the baseline skill and its rules state
  themselves in a line each, and an outward pointer to `AGENTS.md` breaks
  hardest in a consuming project, which has its own unrelated `AGENTS.md`.
  Both now state categories and exemptions only.

## Model conventions

- **2026-07-31 (04 #1): `Rest_key_model` is re-parented to `MY_Model`, and the
  connection is wired in the same change.** Re-parenting alone would have
  re-parented *past* a live bug: the model issued every key through
  `$this->db` (always `'default'`) while `REST_Controller` validates every
  incoming key through `$this->rest->db`, bound to `rest_database_group`. Any
  project that moved that group off `'default'` wrote keys to one database and
  looked for them in another. `connection_name` now follows
  `rest_database_group`, with `$lazy_connect = true` so the property is
  honoured before anything connects — a property default, not a constructor
  assignment, which would fire too late.
- **2026-07-31 (04 #1): query bodies were left as builder chains.** The
  re-parenting moved them from `$this->db` to `$this->my_db` and added
  `check_connect()` per method, matching the framework's own idiom. Converting
  them to the model API was the wider option and was declined here; it is part
  of the standing rest-key-connection question instead.
- **2026-07-31 (04 #2/#3): `GREATEST()` is replaced by a `CASE WHEN`, and the
  hardcoded threshold reads the environment.** `GREATEST` was not a
  portability note but a broken endpoint — run against a real SQLite engine it
  returns `no such function`. `MgrFunctionType` was confirmed to have no
  portable equivalent (only `FromUnixtime`/`ToUnixtime`), so the expression is
  written out. The alias `remaining_attempts` is unchanged, so it stays
  orderable on every engine.
- **2026-07-31 (04 #3): the threshold reads `mgr_env_int()` directly, not
  `config_item()`.** A first pass loaded the `ion_auth` config in the model
  constructor and was reverted: `MGR_Loader::config_read()` exists precisely so
  `Ion_auth` can read that config *without* merging it into CI's shared config
  array, and `$this->load->config()` does the thing that pattern avoids. Since
  the config value is itself just `mgr_env_int('AUTH_MAX_LOGIN_ATTEMPTS', 3)`,
  reading the env directly reproduces it with no load dependency — the "is the
  config loaded in this context" trap is sidestepped rather than solved.
  Tradeoff accepted: a project that overrides the config key to disagree with
  its own env value would see enforcement and display diverge, which is a
  self-contradictory override to begin with.

## CLI controller shape

- **2026-08-01 (05 #1): one guard shape — `is_cli()` refused with
  `show_error()`.** Three shapes existed across three controllers; two used
  `$this->input->is_cli_request()` with a bare `exit()`. The stated risk was
  accepted: worst case, `MGR_Exceptions` misbehaving in a CLI context gives a
  body-less 500 rather than silence. In practice all three now refuse an HTTP
  request with the generic envelope.
- **2026-08-01 (05 #2): `Health_checks` correctly has no CLI guard.**
  Confirmed by tracing callers rather than accepting the doc claim: the
  scaffold's crontab invokes it hourly over CLI, and the separate `api/`
  variant is the deliberately unauthenticated REST endpoint for an external
  monitor. The controller is dual-use, so a CLI guard would break whichever
  caller is not CLI. Note the Docker healthchecks hit php-fpm's `/ping` and
  nginx's `/nginx-health` directly, so they do **not** corroborate the
  web-caller claim — the crontab plus the api-variant pairing does.
