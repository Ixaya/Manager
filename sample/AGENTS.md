# AGENTS.md

This file guides coding agents (and new developers) working on this
application, which is built on the `ixaya/manager` framework (a CodeIgniter 3
HMVC superset) consumed via Composer — framework code lives under `vendor/`
and is never edited here. Adapt this paragraph when bootstrapping a new
project: one sentence on what THIS application is and who uses it.

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

A PHPUnit test suite is configured (`phpunit.xml`, PHPUnit 13): tests live in
`tests/unit/`, bootstrapped by `tests/Bootstrap.php`, which boots the full
framework once per run with `.env.testing` (non-secret config, committed) plus
`.env.testing.priv` (`DB_PASS`, gitignored). These are integration tests — they
hit the instance's normal dev DB with namespaced, self-cleaning fixtures, not
mocks. Writing and extending tests — the `CITestCase`/`AuthTestCase` bases,
fixtures, DB-free vs DB-backed — is covered in `docs/development/testing.md`;
`tests/unit/auth/` is the reference suite. Run with `vendor/bin/phpunit
--testdox` (a `require-dev` dependency; without host PHP use the docker `tools`
service — see `docs/development/docker.md`, including the schema-migration step
for a fresh DB). PHPStan is the static-analysis gate.

## Agent skills

Framework conventions live as skills in `.claude/skills/ixaya-*/SKILL.md`
(open SKILL.md format — readable by any tool; canonical home is the
`ixaya/manager` package at `vendor/ixaya/manager/system/skills/`). The
`.claude/skills/` directory is not committed — it is created as a setup step
by symlinking the package skills (see the framework README's "Agent skills"
section for the loop; re-run it after major framework updates). If the
symlinks are missing, read the skills directly from the vendor path.
Before writing or editing ANY code, script, or config file (not just PHP),
invoke the `ixaya-code-style` skill first — the topic skills below do not
replace it. **Comments are never documentation** — the comments policy lives
in that skill. Then consult the matching topic skill BEFORE writing code of
that kind:

| Skill | Covers |
|---|---|
| `ixaya-code-style` | Style baseline for ALL code and config (PHP, shell, YAML, env): typing, PHPDoc, named parameters, comments, where documentation lives |
| `ixaya-models` | MY_Model / APP_Model_Dyn — any database access |
| `ixaya-rest-controller` | API endpoints, auth, response envelope |
| `ixaya-auth` | Login/session/API-key auth, account lockout, first-admin bootstrap (`claim_admin`) |
| `ixaya-web-controllers` | Web page controllers, views, theming/layouts |
| `ixaya-migrations` | Schema changes (MGR_Migration_builder) |
| `ixaya-cli-modules` | CLI commands, crons, background exec, HMVC modules |
| `ixaya-helpers-libraries` | Utility functions, packaged libraries, creating new libraries |
| `ixaya-cache-websockets` | Caching, Redis, pub/sub, websocket notifications |
| `ixaya-live-probes` | Live-testing changes against the running Docker stack: probe controllers, real auth, log channels |

Read `ixaya-auth` whenever end-to-end API testing is in scope, not only when
writing auth code: obtaining a first credential (`claim_admin`), logging in, and
calling an endpoint with a real `X-API-KEY` all live there. A request rejected
with *"Invalid API key"* is the framework refusing an unauthenticated call — it
is not evidence that auth works.

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
(`application/modules/{module}/migrations/{connection}/`); older projects may
also carry a root `application/database/migrations/` folder of legacy
app-level migrations — frozen history, don't add new ones there. Seeds live in
`application/database/seeds/`. Authoring conventions: see the
`ixaya-migrations` skill.

Run via `bin/cli_run.sh`, never plain `php`:
```bash
./docker_manage.sh -e <instance> exec php bash /var/www/html/bin/cli_run.sh manager/tools/migrate
./docker_manage.sh -e <instance> exec php bash /var/www/html/bin/cli_run.sh manager/tools/seed
```

## Docker stack

Setup, deploy, rotation, tuning, and troubleshooting:
`docs/development/docker.md`. Editing the files under `docker/` themselves
(hard rules, env-var placement, build gotchas):
`docs/development/docker-internals.md`. Day-to-day commands are in
"Commands" above.

## Documentation

All project documentation lives under `docs/`. The layout, categories,
lifecycle, and drift rules are defined in `docs/documentation.md` — read it
before creating or reorganizing any doc.

## Hard rules

- **PHP 8.2 floor, 8.4-era style.** No 8.3/8.4-only features (typed class
  constants, `#[\Override]`, property hooks, asymmetric visibility).
- **Never edit anything under `vendor/`.** Framework fixes go in this
  project's extension seams (`application/core/` subclasses, config
  overrides); framework changes belong upstream in the `ixaya/manager`
  package.
- **When the prompt is silent on a security- or safety-relevant choice**
  (auth mode, deletion, data exposure, permissions), take the documented
  safe default; a nearby file never justifies dropping below it (a sibling
  that matches or tightens the default is fine). State the assumption you
  made. Ask only when interactive and no safe default exists; an autonomous
  run picks the conservative option and says so.
- **Git: agents never commit** — the operator reviews and commits. (Adapt
  to your team's policy when bootstrapping a new project.)
