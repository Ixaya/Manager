# AGENTS.md

> Scope: coding agents working on the **ixaya/manager package itself**. If
> you are working on an application that *consumes* this package, use that
> project's AGENTS.md instead — and never edit files under `vendor/`.

## What this is

An HMVC framework, superset of CodeIgniter 3, distributed **only via Composer**
(`composer require ixaya/manager`). Consuming projects bootstrap once from
`sample/` and afterwards receive framework updates through `composer update` —
framework code never lives inside a project.

## Commands

PHPStan and the CS fixer are the quality gates. Both run through Docker only
— never a bare host `composer`/`vendor/bin/...` — via the `tools` service,
which pins the exact PHP version and extensions the stack ships, so a host
run is not a valid test of a bug or of its absence. Day-to-day invocations:
`docs/development/framework-workflow.md`.

The framework has no suite of its own: it is tested through the bundled
`sample/`, whose PHPUnit suite ships to every consuming project. That is the
point of testing there rather than here — a project gets the suite for free,
and when it overrides a shim (`MY_Model`, `APP_Rest_Controller`) it can
verify its own subclass still drives the framework correctly.

## Repo map

```
system/
├── config/             # constants.php, hooks.php
├── core/               # MGR classes: Model, Controller, Loader, Router, Exceptions,
│   │                   #   MGR_Model_Dyn, MGR_Api_Model, MGR_Rest_Controller
│   └── MGR/
├── hooks/              # MGR_Bootstrap.php (framework bootstrap hook)
├── libraries/          # MGR_* implementations (upload, aws, jwt, mailing, async exec,
│   │                   #   websocket, migration builder/module lib)
│   └── MGR/            # Cache + Cache_redis, Migration runner
├── package/            # CI package path, autoloaded by consuming apps:
│   ├── config/         #   lib_*.php configs, manager.php, rest.php, migration.php…
│   ├── controllers/    #   Language.php
│   ├── helpers/        #   manager_*_helper.php (mgr_* functions, Mgr* enums)
│   ├── language/       #   english, japanese, spanish
│   ├── libraries/      #   unprefixed thin aliases (Async_exec_lib extends MGR_…)
│   ├── models/         #   Manager_option, Rest_key_model, Domain, Theme…
│   ├── modules/manager/ #  tools, health_checks, websockets + package migrations
│   └── views/          #   auth views
├── skills/             # Agent skills (SKILL.md format) — conventions source of truth
└── third_party/        # MX (HMVC), BE (Ion Auth fork), REST_Controller
sample/                 # project scaffold — copied ONCE into new projects
extras/                 # legacy CI3 example projects — `export-ignore`d, never ships
patches/                # composer patches for dependencies
```

**Reading a skill's paths in this repo.** The skills state placement in
project terms (`application/models/`, `application/modules/{module}/…`)
because that is where most of their readers work. Three trees here are
application-shaped and substitute for that root: `sample/application/` (the
scaffold — a real project), `system/package/` (the CI package path autoloaded
into every app; same subdirectories, and its models extend `MY_Model` like any
project model), and a consuming project's own `application/`. Read
`application/x/y` as `<root>/x/y` and pick the root from what you are
changing.

`system/core/` and `system/libraries/` are NOT such a root. Code there is the
base class or implementation the application-shaped trees extend, so "the same
file at a different root" does not apply — a change lands in every consuming
project at once. Adding a library is the one placement that is not a root swap
either: it is three files, under "Where a change goes" in
`docs/development/framework-workflow.md`.

## Conventions

The skills in `system/skills/mgr-*/SKILL.md` are the source of truth for how
code is written here and in consuming projects. Before writing or editing ANY
code, script, or config file (not just PHP), invoke the `mgr-code-style`
skill first — topic skills do not replace it. Then consult the topic skill
before touching its area (models, REST, auth, web controllers/theming,
migrations, libraries, cache/websockets, CLI/modules). Read `mgr-auth`
whenever end-to-end API testing is in scope, not only when writing auth code —
obtaining a first credential (`claim_admin`), logging in, and calling with a
real `X-API-KEY` live there. A request rejected with *"Invalid API key"* is the
framework refusing an unauthenticated call, not evidence that auth works.

Working on a skill itself — writing a new one, editing one, or validating a
set produced by another agent — is `docs/development/skill-authoring.md`.

**Testing framework code:** the development loop — quality gates, throwaway
probes versus the sample's PHPUnit suite, running the sample's `tools`
service against this checkout instead of its lagging vendor mirror, and the
cross-engine matrix — is documented under `docs/development/`. Read it before
verifying a change; the probe conventions themselves (authenticated-not-
bypassed, log channels) are in the `mgr-live-probes` skill. Test code never
goes anywhere else in `sample/` or `system/`.

## Documentation

All project documentation lives under `docs/`. The canonical documentation
standard — layout, categories, lifecycle, drift rules — is the **shipped**
`sample/docs/documentation.md` (it governs this repo too);
`docs/documentation.md` is the thin framework-only addendum on top of it.
Read both before creating or reorganizing any doc.

How the framework attaches to a project, boots, and resolves a class name to
a file (`MGRPATH` / the CI package path, the MX load chain, `MGR_*` →
package alias → `MY_`/`APP_`) is in `docs/architecture/`.

## Hard rules

- **PHP 8.2 floor, 8.4-era style.** No 8.3/8.4-only features (typed class
  constants, `#[\Override]`, property hooks, asymmetric visibility).
- **Cross-engine always.** Anything touching the database must work on
  MySQL/MariaDB, PostgreSQL, SQL Server, and SQLite — use `MgrDriver`,
  `MgrFieldType`, and `MgrFunctionType`; never emit engine-specific SQL
  without a driver `match`.
- **`sample/` is the canonical example source for every consuming project.**
  When a convention changes, update the sample in the same change — an
  outdated sample teaches the old pattern to every new project and agent.
- **Never break the alias chain.** `MGR_*` classes → unprefixed aliases in
  `system/package/libraries/` → app-level `MY_`/`APP_` subclasses. Renaming a
  public method or changing a signature is a breaking change for every
  consuming project.
- **Skills must move with the code.** If a change alters a documented
  convention, update the matching skill in `system/skills/` in the same change.
- **`system/third_party/` is upstream-tracked — leave it as-is.** MX, the BE
  Ion Auth fork, and REST_Controller are kept close to their upstreams so
  updates merge cleanly. No style sweeps, no refactors; surgical bug fixes
  only, and prefer fixing in the MGR_ subclass layer instead. The BE Ion Auth
  fork carries a documented set of deliberate edits and purposeful deviations
  — see `docs/development/auth-upstream.md` before/after any upstream merge.
- **`extras/` is legacy example code, not a pattern source.** It is kept so
  legacy CI3 projects can see the shapes they are upgrading from, and it is
  `export-ignore`d, so it never reaches a consuming project. Do not homologate
  it, do not fix its drift, and never cite it as an example of how this
  framework is written; a sweep that finds something there notes it and moves
  on. Counting it in a corpus survey is fine and occasionally useful — it is
  the largest body of real historical code — but a corpus *fix* stops at
  `system/` and `sample/`.
- **Comments are short and rare, not documentation.** Inline: 1–2 lines, 4
  max, only for a non-obvious constraint/gotcha the code can't express —
  never restate what the code does. PHPDoc: short, caller-facing only. Full
  policy (and all style rules) lives in the `mgr-code-style` skill — invoke
  it before writing code, but this cap holds even if you don't.
- **When the prompt is silent on a security- or safety-relevant choice**
  (auth mode, deletion, data exposure, permissions), take the documented safe
  default; a nearby file never justifies dropping below it (a sibling that
  matches or tightens the default is fine). State the assumption you made. Ask
  only when interactive and no safe default exists; an autonomous run picks the
  conservative option and says so.
- **`BASEPATH` guard required** in controllers, models, libraries, helpers,
  config, migrations, and language files. Exempt: seeds and views. Always
  directly below `<?php`, within the first 3 lines — never below a header
  comment.
- Formatting is PSR-12 with tabs (run the fixer before finishing).
- **Git operations are off-limits.** Agents must never perform git operations
  (commit, push, branch creation/deletion, rebase, merge, etc.) with the sole
  exception of adding `.gitignore`, `.gitattributes`, or `.gitkeep` files. All
  other git operations — even if they seem necessary — require explicit
  human authorization or belong in a human-run workflow step.

## Pending work

Each `docs/workspace/<task>/` directory contains a
`handoff.md` recording current state, blockers, and context for continuing that
specific investigation or initiative. When a workspace task is complete, its
handoff is distilled into permanent documentation (design/, architecture/,
development/, modules/) or deleted if inconsequential. The full methodology for
running a findings/fix campaign through the workspace (validation, baselines,
session planning) is `docs/development/spec-campaigns.md`. The
release check where a fresh agent sets up the framework from scratch is
`docs/development/agent-smoke-test.md`.
