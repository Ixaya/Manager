# AGENTS.md

This file provides guidance to coding agents working on the **ixaya/manager
package itself**. If you are working on an application that *consumes* this
package, use that project's AGENTS.md instead — and never edit files under
`vendor/`.

## What this is

An HMVC framework, superset of CodeIgniter 3, distributed **only via Composer**
(`composer require ixaya/manager`). Consuming projects bootstrap once from
`sample/` and afterwards receive framework updates through `composer update` —
framework code never lives inside a project.

## Commands

```bash
composer install
vendor/bin/phpstan analyse          # uses this repo's phpstan.neon
vendor/bin/php-cs-fixer fix         # PSR-12, tabs — see .php-cs-fixer.php
```

There is no test suite; PHPStan and the CS fixer are the quality gates.

## Repo map

```
system/
├── config/             # constants.php, hooks.php
├── core/               # MGR classes: Model, Controller, Loader, Router, Exceptions,
│   │                   #   MGR_Model_Dyn, MGR_Api_Model, MGR_Rest_Controller
│   └── MGR/
├── hooks/              # MGR_Bootsrap.php (framework bootstrap hook)
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
patches/                # composer patches for dependencies
```

## Conventions

The skills in `system/skills/ixaya-*/SKILL.md` are the source of truth for how
code is written here and in consuming projects. `ixaya-code-style` applies to
every line in this repo; consult the topic skill before touching its area
(models, REST, auth, web controllers/theming, migrations, libraries,
cache/websockets, CLI/modules).

**Testing framework code:** write throwaway test/validation controllers in
`sample/application/modules/test/` — that module is gitignored
(`sample/application/modules/.gitignore`), exists only for framework
development, and never ships to consuming projects (the sample is copied
from a git checkout, where it's absent). Don't scatter test code anywhere
else in `sample/` or `system/`. The probe conventions (authenticated-not-
bypassed, Docker recipe, log channels) are in the `ixaya-live-probes` skill.

## Documentation

All project documentation lives under `docs/`. See `docs/documentation.md` for
the complete structure and lifecycle.

Key locations:

- **`docs/architecture/`** — long-lived architecture and system design
- **`docs/development/`** — operational/developer guides (local dev, Docker, deployment)
- **`docs/design/<initiative>/`** — permanent records of completed initiatives
  (each initiative has `spec.md`, `decisions.md`, `handoff.md`, `review.md`)
- **`docs/modules/`** — permanent documentation of modules and major components
- **`docs/workspace/<task>/`** — **temporary working area** for active investigations
  (never committed; after completion, knowledge is distilled into permanent docs
  or deleted if inconsequential)
- **`docs/generated/`** — automatically generated documentation (never edit manually)

The `workspace/` directory is already excluded from git via `docs/.gitignore`.
Use it as a staging ground for work-in-progress specs, reviews, handoffs, and
analysis that will later be consolidated into permanent documentation.

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
  — see `docs/development/02-auth/upstream.md` before/after any upstream merge.
- Every PHP file starts with the `BASEPATH` guard; formatting is PSR-12 with
  tabs (run the fixer before finishing).
- **Git operations are off-limits.** Agents must never perform git operations
  (commit, push, branch creation/deletion, rebase, merge, etc.) with the sole
  exception of adding `.gitignore`, `.gitattributes`, or `.gitkeep` files. All
  other git operations — even if they seem necessary — require explicit human
  authorization or belong in a human-run workflow step.

## Pending work

Each `docs/workspace/<task>/` directory contains a
`handoff.md` recording current state, blockers, and context for continuing that
specific investigation or initiative. When a workspace task is complete, its
handoff is distilled into permanent documentation (design/, architecture/,
development/, modules/) or deleted if inconsequential. The full methodology for
running a findings/fix campaign through the workspace (validation, baselines,
session planning) is `docs/development/03-spec-campaigns/README.md`.
