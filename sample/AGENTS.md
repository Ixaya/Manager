# AGENTS.md

> Scope: coding agents (and new developers) working on this application,
> which is built on the `ixaya/manager` framework (a CodeIgniter 3 HMVC
> superset) consumed via Composer — framework code lives under `vendor/` and
> is never edited here. Adapt this paragraph when bootstrapping a new
> project: one sentence on what THIS application is and who uses it.

## Commands

```bash
# Docker is the only supported way to run these — never a bare host
# `composer`/`vendor/bin/...`. It pins the exact PHP version and extensions
# the stack ships, so a host run isn't a valid test of a bug (or its absence).
./docker_manage.sh -e <instance> run --rm tools composer install
./docker_manage.sh -e <instance> run --rm tools vendor/bin/phpstan analyse

# Docker: always via docker_manage.sh, never `docker compose` directly —
# it wires the per-instance env files and secrets the compose file needs.
# You pick the instance name: lowercase [a-z0-9_-], matching your files in
# docker/env/<instance>.*. Creating those files, picking a DB engine, server
# profiles, deploying, and troubleshooting: docs/development/docker.md.

# Day one, once your instance exists: build, start with a local DB, migrate.
# ws/cron are server-only — leave them off, rarely needed in dev.
./docker_manage.sh -e <instance> build
./docker_manage.sh -e <instance> --profile <mysql|mariadb|postgres> up -d
./docker_manage.sh -e <instance> run --rm cli -c "bash /var/www/html/bin/cli_run.sh manager/tools/migrate"

# CLI commands inside the running stack — always via bin/cli_run.sh, never bare `php`
./docker_manage.sh -e <instance> exec php bash /var/www/html/bin/cli_run.sh manager/health_checks
./docker_manage.sh -e <instance> logs -f php

# Every framework CLI command with its arguments — the authoritative list
./docker_manage.sh -e <instance> exec php bash /var/www/html/bin/cli_run.sh manager/tools/help
```

The suite in `tests/unit/` is an **integration** suite: it boots the whole
framework and hits the instance's normal dev DB with namespaced, self-cleaning
fixtures, not mocks. Run it through the docker `tools` service
(`vendor/bin/phpunit --testdox`) — the supported path regardless of what's
installed on the host. Authoring conventions and the
`CITestCase`/`AuthTestCase` bases are in `docs/development/testing.md`
(`tests/unit/auth/` is the reference suite); the testing environment's config
and the Docker recipe, including the schema-migration step for a fresh DB, are
in `docs/development/docker.md`. PHPStan is the static-analysis gate.

## Agent skills

Framework conventions live as skills in `.claude/skills/mgr-*/SKILL.md`
(open SKILL.md format — readable by any tool; canonical home is the
`ixaya/manager` package at `vendor/ixaya/manager/system/skills/`). The
`.claude/skills/` directory is not committed — it is created as a setup step
by symlinking the package skills (see the framework README's "Agent skills"
section for the loop; re-run it after major framework updates). If the
symlinks are missing, read the skills directly from the vendor path.

Where a skill or a framework doc names a bare `system/…` path, it means
`vendor/ixaya/manager/system/…` — read it there, never edit it.

Before writing or editing ANY code, script, or config file (not just PHP),
invoke the `mgr-code-style` skill first — the topic skills below do not
replace it. **Comments are short and rare, not documentation** — inline:
1–2 lines, 4 max, only for a non-obvious constraint/gotcha, never restating
what the code does; PHPDoc short and caller-facing only; this cap holds
even if you skip the skill. Full policy lives there. Then consult the
matching topic skill BEFORE writing code of that kind:

| Skill | Covers |
|---|---|
| `mgr-code-style` | Style baseline for ALL code and config (PHP, shell, YAML, env): typing, PHPDoc, named parameters, comments, where documentation lives |
| `mgr-models` | MY_Model / APP_Model_Dyn — any database access |
| `mgr-rest-controller` | API endpoints, auth, response envelope |
| `mgr-auth` | Login/session/API-key auth, account lockout, first-admin bootstrap (`claim_admin`) |
| `mgr-web-controllers` | Web page controllers, views, theming/layouts |
| `mgr-migrations` | Schema changes (MGR_Migration_builder) |
| `mgr-cli-modules` | CLI commands, crons, background exec, HMVC modules |
| `mgr-helpers-libraries` | Utility functions, packaged libraries, creating new libraries |
| `mgr-cache-websockets` | Caching, Redis, pub/sub, WebSocket notifications |
| `mgr-live-probes` | Live-testing changes against the running Docker stack: probe controllers, real auth |
| `mgr-docker-ops` | Running/debugging the stack itself: `docker_manage.sh` usage, bind flags, exec's default user, log channels |
| *(no skill — `docs/development/`)* | Tests: the suite is an **integration** suite, not mocks |

Read `mgr-auth` whenever end-to-end API testing is in scope, not only when
writing auth code: obtaining a first credential (`claim_admin`), logging in, and
calling an endpoint with a real `X-API-KEY` all live there. A request rejected
with *"Invalid API key"* is the framework refusing an unauthenticated call —
it is not evidence that auth works.

## Architecture

**Framework:** CodeIgniter 3 with HMVC (Hierarchical MVC — the loader lives
in `vendor/ixaya/manager/system/third_party/MX/`, not in `application/`). Two
vendor packages: `vendor/nielbuys/framework` (the CI3 base) and
`vendor/ixaya/manager` (the Manager superset this app is built on) — PHPStan
scans both.

**Entry point:** all HTTP and CLI requests route through `public/index.php`,
which boots the env layer before CodeIgniter and derives `ENVIRONMENT` from
`APP_ENV` — full resolution order in `docs/architecture/`.

**CLI execution:** framework and module commands run inside the Docker stack
through `bin/cli_run.sh`, never a bare host `php`. Day-to-day forms are in
"Commands" above; picking the instance and the service, and the full set of
invocation options, are covered in `docs/development/`.

### Application layout

The project's own code lives under `application/`:

- `core/` — `MY_Controller`, `APP_Rest_Controller`, `MY_Model`,
  `APP_Model_Dyn`: thin shims over their `MGR_` parents in the package. They
  are project code, so they can carry local overrides — check them when
  tracing behavior, don't assume they are empty.
- `modules/*/controllers/api/` — REST endpoints.
  `modules/auth/controllers/api/Login.php` is the login/registration flow.
- `modules/<module>/migrations/<connection>/` — new migrations. Older
  projects may also carry a root `database/migrations/` folder of legacy
  app-level migrations: frozen history, don't add new ones there.
- `database/seeds/` — seeds.

### Modules (`application/modules/`)

| Module | Purpose |
|---|---|
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

### Configuration

There are no per-environment config directories — every value in
`application/config/*.php` resolves from real environment variables through
the `mgr_env*()` helper family: `mgr_env()`, `mgr_env_int()` and
`mgr_env_bool()` for example, with required, array and JSON variants
alongside them. Check the family for the full set rather than casting by
hand. How values reach the process, and the full resolution order: see
`docs/architecture/`.

## Docker stack

Read `mgr-docker-ops` before running any `docker`/`docker compose` command
against this stack, not only when writing live probes — bringing the stack
up/down, `exec`-ing into a container, or running a `manager/tools` command
all belong there. Setup, deploy, rotation, tuning, and troubleshooting:
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
