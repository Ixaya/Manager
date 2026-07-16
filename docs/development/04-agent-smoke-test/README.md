# Agent smoke test

A recurring release check: a **fresh coding agent** — empty context, no
prior knowledge of the framework beyond `vendor/ixaya/manager/README.md`
and its linked skills — sets up a project from scratch and builds a working
feature end to end. The product under test is the documentation and the
skills, not the agent: every place it stalls, guesses, or works around a
gap is a defect report for the docs.

Run it in an isolated sandbox per release (or after doc/skill overhauls).
The first run (2026-07) found two shipped fixes: no bootstrap path to a
first API key (now `manager/tools/claim_admin`) and undocumented
`-b`/`-m` bind-path resolution.

## Contamination rule

The agent's prompt must stay **vague on purpose**: name the goal (set up a
project, build a blog module with controller + migration + model, prove the
API works), never the mechanism. Do not mention framework class names,
skill names, CLI tools, or this document — each one named in the prompt is
one discovery the test no longer tests. Everything under "Evaluating a run"
is evaluator-only.

Example prompt shape (operator-prepped variant): *"Smoke test: you are in a
Docker sandbox. Since you don't have php or composer, I've already done the
first step — `composer require ixaya/manager` — as you can see from the
`composer.lock` and `vendor/` present. Read `vendor/ixaya/manager/README.md`
and follow it to set up your own project from there. Create a sample
controller in a blog module with its required migration and model; it should
have posts and categories. Stack: php+nginx+postgres+cli only; run all php
through docker. 100% API project, no interface. If you hit a big problem,
stop and ask instead of wasting tokens."*

**Pre-provided-vendor trap:** when the operator says `vendor/` and
`composer.lock` are already there, an agent may read that as "the whole setup
is done" and jump straight to writing a module — skipping scaffold, `.env`,
and the Docker instance entirely. Frame the handoff narrowly: `composer
require` is *the first step only*, and the README must be followed *from there*
to set up the project. If a run does this, the run is void — clear it and
restart; it isn't a doc finding.

## Vendor prep (the PHP/Composer question)

Sandboxes ship without PHP/Composer. Two variants — state in the run notes
which one was used:

- **Operator-prepped** — the agent emits the `composer` commands
  (pin `ixaya/manager:dev-master` when testing unreleased work), the
  operator produces `vendor/` outside the sandbox and hands it over. Skips
  testing the install step itself; fastest.
- **Self-install** — the agent installs a minimal PHP + Composer in the
  sandbox and runs the require itself. Exercises the README's install
  path for real; needs package-manager network access (see preflight).

## Run outline

1. Scaffold from `vendor/ixaya/manager/sample/`; configure `.env` /
   `.env.priv` and a Docker instance for one engine (Postgres keeps the
   stack smallest). No ws/cron containers.
2. Connectivity preflight from the sample docker doc — sandbox policies
   commonly block `pecl.php.net`; a blocked domain must be flagged to the
   operator, not retried for hours.
3. Build and run the stack; run migrations.
4. Build the blog module feature; authenticate and exercise the endpoints.
5. Clean up test data; deliver spec/handoff notes of every roadblock.

Scope guards: API-only (no web/UI), one DB engine per run, stop-and-ask on
real blockers. Build in a real module (the blog module), never the throwaway
`test/` module — its siblings are public no-auth probes, and agents pattern-
match on nearby files, so building there teaches the wrong conventions.

## Evaluating a run (evaluator-only)

A passing run, discovered by the agent without being told:

- Migration via `MGR_Migration_builder`, model via `MY_Model`, controller
  via `APP_Rest_Controller` with the response envelope — graded against
  the `ixaya-*` skills; note which skills the agent actually loaded.
- Baseline skill loading: confirm `ixaya-code-style` loaded, not just the
  topic skills — it's the one agents skip, pulled in only via the topic
  skills' prerequisite line. It's effort-gated: low-effort runs reliably
  miss it, medium+ load it. Test at medium or higher, and judge comment
  discipline (no narration, no doc pointers, short PHPDoc) even when it
  didn't load.
- First credential obtained via `manager/tools/claim_admin`, login through
  the normal auth endpoint, CRUD exercised with the issued `X-API-KEY`.
  Any invented bypass (raw DB inserts, disabled auth) is a finding, not a
  success.
- **Auth-verified means a request *succeeded* with a real key.** A call that
  returns `{"status": false, "error": "Invalid API key"}` is the framework
  *rejecting* an unauthenticated request — it is not proof "auth works." Watch
  for the agent claiming success off a rejection without ever running
  `claim_admin` → login → an authorized 2xx CRUD call. A run with no obtained
  key and no successful authorized call has not exercised auth at all.
- Model built via the dynamic model API (`APP_Model_Dyn` / `get_all_dynamic`
  with `build_list_params`), not hand-rolled `$this->db` — the first run
  reached for it unprompted, which is the target behavior.
- Triage roadblocks into: docs gap, skill gap, framework bug, or sandbox
  environment issue — and fix at that scope.
- The agent's workspace notes are transitory; distill durable findings
  into the matching skill, `docs/development/`, or a Decisions log, then
  discard.
