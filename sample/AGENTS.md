# AGENTS.md

This file provides guidance to coding agents working with code in this repository.

## Commands

```bash
# Install dependencies
composer install

# Static analysis (level 5)
vendor/bin/phpstan analyse

# Docker: always via docker_manage.sh, never `docker compose` directly —
# it wires the per-instance env files and secrets the compose file needs.
# Instance names are per docker/env/<instance>.*; "local" ships out of the box.

# ── Local development: full stack incl. a local DB, on this machine ────────
# ws/cron are server-only — leave them off, rarely needed in dev. Set up
# your personal instance and pick a DB engine first — see docs/development/docker.md.
./docker_manage.sh -e <you> build
./docker_manage.sh -e <you> --profile <mysql|mariadb|postgres> up -d
./docker_manage.sh -e <you> run --rm cli -c "bash /var/www/html/bin/cli_run.sh manager/tools/migrate"

# ── Server / deployment mode: ws + cron enabled, DB is external/managed ────
./docker_manage.sh -e <instance> build
./docker_manage.sh -e <instance> --profile ws --profile cron up -d

# CLI commands inside the running stack — always via bin/cli_run.sh, never bare `php`
./docker_manage.sh -e <instance> exec php bash /var/www/html/bin/cli_run.sh manager/health_checks
./docker_manage.sh -e <instance> logs -f php
```

A PHPUnit test suite is configured (`phpunit.xml`, PHPUnit 12): tests live in
`application/tests/unit/`, bootstrapped by `application/tests/Bootstrap.php`
with `.env.testing` as the environment. Run with `phpunit` (or
`vendor/bin/phpunit` if installed locally). Add new unit tests under
`application/tests/unit/`. PHPStan is the static-analysis gate.

## Agent skills

Framework conventions live as skills in `.claude/skills/ixaya-*/SKILL.md`
(open SKILL.md format — readable by any tool; canonical home is the
`ixaya/manager` package at `vendor/ixaya/manager/system/skills/`). The
`.claude/skills/` directory is not committed — it is created as a setup step
by symlinking the package skills (see the framework README's "Agent skills"
section for the loop; re-run it after major framework updates). If the
symlinks are missing, read the skills directly from the vendor path.
Consult the matching skill BEFORE writing code of that kind:

| Skill | Covers |
|---|---|
| `ixaya-code-style` | Style baseline for ALL PHP: typing, PHPDoc, named parameters, comments, where documentation lives |
| `ixaya-models` | MY_Model / APP_Model_Dyn — any database access |
| `ixaya-rest-controller` | API endpoints, auth, response envelope |
| `ixaya-web-controllers` | Web page controllers, views, theming/layouts |
| `ixaya-migrations` | Schema changes (MGR_Migration_builder) |
| `ixaya-cli-modules` | CLI commands, crons, background exec, HMVC modules |
| `ixaya-helpers-libraries` | Utility functions, packaged libraries, creating new libraries |
| `ixaya-cache-websockets` | Caching, Redis, pub/sub, websocket notifications |

## Architecture

**Framework:** CodeIgniter 3 with HMVC (Hierarchical MVC — the loader lives in `vendor/ixaya/manager/system/third_party/MX/`, not in `application/`). Two vendor packages: `vendor/nielbuys/framework` (the CI3 base) and `vendor/ixaya/manager` (the Manager superset this app is built on) — PHPStan scans both (see `phpstan.neon`).

**Entry point:** all HTTP and CLI requests route through `public/index.php`, which boots the env layer (`.env`/`.env.priv` files, process env wins) before CodeIgniter and derives `ENVIRONMENT` from `APP_ENV` — full resolution order in `docs/architecture/environment.md`.

**CLI execution:** `php public/index.php module/controller/method [args]`

### Controller hierarchy

```
CI_Controller
└── MY_Controller extends MGR_Controller   (theming/layout resolution, domain
    │                                       detection, language, view loading)
    └── APP_Rest_Controller extends MGR_Rest_Controller
                                           (API key auth, permission + group
                                            validation — logic lives in
                                            MGR_Rest_Controller::_remap())
```

Both `application/core/` classes are thin shims over their `MGR_` parents —
project-level overrides go there. Legacy base controllers
(`Admin_Controller`, `Site_Controller`, `Private_Controller`) are NOT part
of this scaffold — see the `ixaya-web-controllers` skill for where to port
them from if needed.

### Modules (`application/modules/`)

| Module | Purpose |
|--------|---------|
| `admin` | Admin REST API — dashboard, system users (`controllers/api/`) |
| `auth` | Login / registration REST API (Ion Auth, `controllers/api/`) |
| `cron` | Scheduled background jobs (example controller) |

Keep this table current as the project adds modules — it is the inventory
agents rely on.

The `manager` module (CLI tools, migrations runner, seeds, health checks,
websockets) is NOT in `application/modules/` — it ships inside the package
(`vendor/ixaya/manager/system/package/modules/manager/`) and is routed via
the CI package path.

Modules contain `controllers/` and optionally `models/`, `migrations/`,
`views/`, `helpers/`, `language/`, `config/`, `libraries/` — create
subdirectories as needed; MX resolves them by convention.

### MY_Model (`application/core/MY_Model.php`)

A thin project-owned subclass of `MGR_Model` (`vendor/ixaya/manager/system/core/MGR/Model.php`) — that's where the actual ORM implementation lives. `MY_Model` itself is usually an empty shim, but since it's project code (not vendor), it can carry local overrides — check it too when tracing model behavior, don't assume it's always empty.

Model properties, defaults, and CRUD conventions: see the `ixaya-models` skill.

### Authentication

**Web sessions:** Ion Auth, shipped by the package
(`vendor/ixaya/manager/system/package/libraries/Ion_auth.php`), loaded via
`$this->load->library('ion_auth')` — see
`application/modules/auth/controllers/api/Login.php` for the login/register
flow.

**REST API:** API key in request header, validated in
`MGR_Rest_Controller::_remap()` (inherited by `APP_Rest_Controller`) before
any action runs, including per-method level/group checks.

### Configuration

There are no per-environment config directories — every value in `application/config/*.php` resolves via `mgr_env()`/`mgr_env_int()`/`mgr_env_bool()` from real environment variables. How values reach the process (Docker `env_file:`, root `.env`/`.env.priv`, `.env.testing`): see `docs/architecture/environment.md`.

### Views & Theming

Theming is controller-based: each controller (or a shared base controller)
sets `MGR_Controller` properties and loads views via
`$this->load_view($page, $data)` — see the `ixaya-web-controllers` skill for
the layout resolution, theming properties, and domain-driven theming.

### REST endpoints

API routes are under `application/modules/*/controllers/api/` (or `*/controllers/*/api/`). Extend `APP_Rest_Controller` and implement `index_get()`, `index_post()`, etc. The `_remap()` method handles auth automatically.

### Migrations & Seeds

New migrations live inside their module
(`application/modules/{module}/migrations/{connection}/`); the root
`application/database/migrations/` folder holds legacy/app-level migrations
only — don't add new ones there. Seeds live in
`application/database/seeds/`. Authoring conventions: see the
`ixaya-migrations` skill.

Run via `bin/cli_run.sh`, never plain `php`:
```bash
./docker_manage.sh -e <instance> exec php bash /var/www/html/bin/cli_run.sh manager/tools/migrate
./docker_manage.sh -e <instance> exec php bash /var/www/html/bin/cli_run.sh manager/tools/seed
```

## Docker stack

First-time instance setup (env files, secrets, DB engine matrix):
`docs/development/docker.md`. Day-to-day commands are in "Commands" above.

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

The `workspace/` directory is excluded from git (add to `.gitignore`). Use it as
a staging ground for work-in-progress specs, reviews, handoffs, and analysis
that will later be consolidated into permanent documentation.
