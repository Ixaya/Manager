# Docker stack — setup & operations

> Scope: running and operating this stack — setup, deploy, rotation, tuning,
> troubleshooting. For editing the files under `docker/` themselves, see
> `docker-internals.md`.

All operations go through the wrapper — never `docker compose` directly:

```bash
./docker_manage.sh -e <instance> [-b|--bind] [-m|--manager-bind] <docker compose args...>
```

`<instance>` selects the env files, secret files, project name, and published
ports, so multiple instances coexist without cross-talk. Instance names are
per `docker/env/<instance>.*`; `local` ships out of the box.

## Your own instance

Every instance needs its own non-committed env files and secrets.
`docker/env/` and `docker/secrets/` already ignore everything except the
`sample.*` templates, so anything you create below is never committed.
Pick an instance name (`<you>` — your username, or `local`):

```bash
cp docker/env/sample.env         docker/env/<you>.env
cp docker/env/sample.docker.env  docker/env/<you>.docker.env
cp docker/env/sample.secrets.env docker/env/<you>.priv.env
chmod 600 docker/env/<you>.priv.env

openssl rand -hex 24 > docker/secrets/<you>.valkey_password
openssl rand -hex 24 > docker/secrets/<you>.db_password
openssl rand -hex 24 > docker/secrets/<you>.db_root_password   # only needed for --profile mysql/mariadb
chmod 600 docker/secrets/<you>.*
```

Copy those same values into `<you>.priv.env`: the valkey password goes in
`LIB_REDIS_PASSWORD` and the `auth=` param of `CF_SESS_SAVE_PATH`; the DB
password goes in `DB_PASS`.

> Write secret files with `printf`/`openssl` (no trailing newline). The
> consumers strip a trailing newline anyway, but keeping them clean avoids
> surprises.

### The three env files

Split by who consumes each variable — not by "secret vs non-secret" alone:

| File | Who reads it | Injected into containers? | Visible in `docker inspect`? |
|------|---------------|---------------------------|-------------------------------|
| `<i>.docker.env` | compose interpolation and `docker_manage.sh` only (ports, image tags, build args, `mem_limit`/`cpus`, bind-mount source paths) | **No** | N/A (never enters a container) |
| `<i>.env` | The PHP app (`mgr_env`/`getenv`) and `docker/php/entrypoint.sh` | **Yes** — the only file loaded via `env_file:` | Yes (non-secret by design) |
| `<i>.priv.env` | The PHP app, via the bind-mounted `/var/www/html/.env.priv` **file** (not process env) | Mounted as a file | **No** |

`docker_manage.sh` requires all of them to exist and aborts loudly if any is
missing. Before adding a new variable to any of these files, read the
"Env var placement" decision tree in `docker-internals.md`.

## Pick a database engine

Pick ONE engine and edit `<you>.env` to match — the base "Package" section's
`DB_DRIVER`/`DB_CHAR_SET`/`DB_COLLATION`, and the "Docker
deployment-dependent" block's `DB_HOST`/`DB_PORT` (`DB_NAME`/`DB_USER` there
can be any non-empty value):

| Engine | `--profile` | `DB_HOST` | `DB_PORT` | `DB_DRIVER` | `DB_CHAR_SET` / `DB_COLLATION` |
|---|---|---|---|---|---|
| PostgreSQL | `postgres` | `postgres` | `5432` | `postgre` | `UTF8` / *(leave empty)* |
| MySQL 8 | `mysql` | `mysql` | `3306` | `mysqli` | `utf8mb4` / `utf8mb4_0900_ai_ci` |
| MariaDB | `mariadb` | `mariadb` | `3306` | `mysqli` | `utf8mb4` / `utf8mb4_uca1400_ai_ci` |

### Profile matrix

| Profile | Service | Purpose | Prod? |
|---------|---------|---------|-------|
| _(core)_ | `php`, `nginx`, `valkey-state`, `valkey-cache` | Always on | Yes |
| `ws` | `ws` | WebSocket server (internal :9008, published via nginx :8080) | Yes |
| `cron` | `cron` | supercronic runs `docker/cron/crontab` | Yes |
| `mysql` | `mysql` | MySQL 8.4 — Aurora-compatible, use when parity with the server matters | No — dev/local only |
| `mariadb` | `mariadb` | MariaDB — lighter local alternative, NOT Aurora-compatible | No — dev/local only |
| `postgres` | `postgres` | PostgreSQL 16 | No — dev/local only |
| `cli` | `cli` | Interactive shell / one-off commands | As needed |
| `tools` | `tools` | composer / phpstan / phpunit on the host tree | No — dev only |

Production uses an **external** database (`DB_HOST=<managed endpoint>`, no db
profile). Valkey ports are **never** published; only nginx publishes
`HTTP_PORT` (→ :80) and `WS_PORT` (→ :8080).

## Build and run

**Sandboxed or firewalled environments:** the image build downloads from
Docker Hub, the Alpine package mirrors, `pecl.php.net`, and (vendor stage)
Packagist/GitHub. Sandbox network policies commonly block `pecl.php.net` by
default, which makes the build fail slowly and confusingly. Check
connectivity first; if anything is blocked, stop and ask the operator to
allow the domain instead of retrying the build.

The signal is the response **body**, not the HTTP status code: a policy block
returns a body starting `Blocked by network policy`. Any other response — 200,
301, 403, 404 — means the request reached the real destination, so the path is
open. (A bare `GET /` to Packagist, the Docker registry, or a CDN routinely
answers 403/404 while being perfectly reachable — that is NOT a block, so do
not key the check on the status code.)

```bash
for host in dl-cdn.alpinelinux.org pecl.php.net repo.packagist.org \
            api.github.com registry-1.docker.io auth.docker.io \
            production.cloudflare.docker.com; do
    body="$(curl -sS --max-time 10 "https://${host}/" 2>/dev/null)" \
        && ! grep -q "Blocked by network policy" <<<"$body" \
        && echo "OK      ${host}" || echo "BLOCKED ${host}"
done
```

```bash
# Local development: full stack incl. a local DB. ws/cron are server-only —
# leave them off, rarely needed in dev.
./docker_manage.sh -e <you> build
./docker_manage.sh -e <you> --profile <mysql|mariadb|postgres> up -d
./docker_manage.sh -e <you> run --rm cli -c "bash /var/www/html/bin/cli_run.sh manager/tools/migrate"

# Server / deployment mode: ws + cron enabled, DB is external/managed.
./docker_manage.sh -e <instance> build
./docker_manage.sh -e <instance> --profile ws --profile cron up -d
```

> **Build-time GitHub API rate limits (optional token).** `composer install`
> during `build` needs no credentials — a GitHub token only helps avoid the
> *unauthenticated* API rate limit (60 req/hr per IP) in repeated or CI
> builds. If you hit rate-limit failures, pass one via
> `docker buildx build --secret id=composer_auth,src=auth.json` — the
> Dockerfile's matching optional secret mount is ignored when absent.

## Deploy procedure

Both images are built and tagged together with the **same `IMAGE_TAG`**; the
nginx image bakes `public/` from the PHP image, so they must never drift —
always deploy the pair.

```bash
# 1. Build both targets (php-app + nginx-app) at IMAGE_TAG (from the env-file).
./docker_manage.sh -e <i> build

# 2. Bring up core (php, nginx, valkey-state, valkey-cache) + server profiles.
./docker_manage.sh -e <i> --profile ws --profile cron up -d

# 3. First run only: migrate (or set RUN_MIGRATIONS=true on ONE instance).
./docker_manage.sh -e <i> exec php bash /var/www/html/bin/cli_run.sh manager/tools/migrate

# 4. Deploy new code: rebuild at a new tag, up -d, then reload FPM (below).
```

### OPcache reload — the standard deploy step

`opcache.validate_timestamps=0` is baked into every image, so PHP never
re-reads changed files. After deploying new code you **must** reset OPcache
by reloading the FPM master:

```bash
./docker_manage.sh -e <i> exec php kill -USR2 1
```

`SIGUSR2` gracefully reloads the FPM master (finishes in-flight requests) and
clears OPcache. In a rebuild-and-replace deploy, recreating the `php`
container achieves the same thing. (This is also a debugging trap — see
Troubleshooting below.)

## Live-code dev modes (`-b`, `-m`)

`-b` bind-mounts a host checkout's `application/` (read-only) over the baked
image code so edits apply without rebuilds; `-m` does the same for an
`ixaya/manager` checkout's `system/` over the vendor copy. Both also mount a
dev-only ini that sets `opcache.validate_timestamps=1`, so edits apply on the
next request — no reload needed. Set the source paths in
`docker/env/<you>.docker.env`:

- `CODE_BIND_PATH` (`-b`) — path containing `application/`
- `MANAGER_BIND_PATH` (`-m`) — path containing a manager checkout's `system/`

Relative values resolve against `docker/`, never your shell's cwd — `..` is
this app's root. Absolute paths also work. `docker_manage.sh` verifies the
resolved path before starting anything. `public/`, `vendor/`, and `bin/` are
never bound — an `index.php` or composer change needs a rebuild.

```bash
./docker_manage.sh -e <you> -b up -d        # app code live
./docker_manage.sh -e <you> -b -m up -d     # app + framework live
```

The two flags are independent and only cover their own layer: `-b` surfaces
`application/` changes, `-m` surfaces `system/` changes. Editing one layer
without its flag is a **silent no-op** — the container keeps serving the baked
copy. This bites hardest with migrations: add a file under
`application/modules/<m>/migrations/` while running `-m` only and
`manager/tools/plan` reports `pending:0` as if the file didn't exist. Pass both
flags whenever you're editing both layers.

**Never used for prod instances.** Production always runs the baked,
immutable image; the bind modes exist only to shorten the dev loop.

### Multi-dev shared checkout

For a shared integration box, the expected host tree is a git checkout owned
by one deploy user, group-readable (Alpine's `www-data`, uid 82, only needs
**read**), updated only via `git pull` on a shared integration branch — no
sftp, no manual copies. Give each developer (or each integration checkout)
its own instance so `-b` sessions never collide: unique
`HTTP_PORT`/`WS_PORT` in `<name>.docker.env`, unique `DB_NAME` in
`<name>.env`, and `CODE_BIND_PATH` pointing at that checkout.

### Rebuild boundary

- **Needs a rebuild:** `composer.json`/`composer.lock` changes, and any new
  class that relies on the composer classmap rather than CI3/MX's own
  module-path discovery.
- **Does NOT need a rebuild:** CI3 module controllers/models/views under
  `application/` — MX resolves these by file path convention at request
  time. Edit and refresh; that's it.

## Static analysis & unit tests (`tools` service)

phpstan/phpunit/php-cs-fixer run through the `tools` service — the image's own
`vendor-builder` stage (composer plus the exact runtime PHP and extensions),
with the project tree mounted at `/work`. Dev dependencies land in the host
tree's `vendor/`; baked images never contain them (the build's
`composer install --no-dev` plus a runtime image with no composer at all).

Writing the tests themselves — the base classes, fixtures, and the DB-free vs
DB-backed choice — is covered in `testing.md`; this section is only how to run
them.

```bash
./docker_manage.sh -e <i> --profile tools build tools   # once, and after Dockerfile changes
./docker_manage.sh -e <i> run --rm tools composer install
./docker_manage.sh -e <i> run --rm tools vendor/bin/phpstan analyse
./docker_manage.sh -e <i> run --rm tools vendor/bin/phpunit
./docker_manage.sh -e <i> run --rm tools vendor/bin/php-cs-fixer check --diff
```

`run` targets the profile-gated service directly — no `--profile tools`
needed — and `up` never starts it. `run` joins the instance's network, so
DB-backed tests reach the db/valkey services with no extra wiring. On Linux
hosts composer's writes (`vendor/`, an updated `composer.lock`) come out
root-owned — `chown` them back if that gets in your workflow's way.

### DB-backed unit tests

The suite runs in the `testing` environment: the committed `.env.testing`
holds the non-secret DB config (service-name hosts, so it works from the
tools container as-is) and the gitignored `.env.testing.priv` holds `DB_PASS`
— same secrets split as the instance env files. Tests hit the instance's
normal dev DB with namespaced, self-cleaning fixtures.

The schema must exist. If the full stack has already run its migrations,
nothing to do; with only the db service up, migrate through the tools
service using the same testing env the tests will use:

```bash
./docker_manage.sh -e <i> --profile postgres up -d postgres   # or your db profile
./docker_manage.sh -e <i> run --rm -e CI_ENV=testing -e REQUEST_METHOD=GET \
    tools php -f public/index.php manager/tools/migrate
./docker_manage.sh -e <i> run --rm tools vendor/bin/phpunit --testdox
```

One gotcha: single-file runs need absolute paths (`vendor/bin/phpunit
/work/tests/unit/auth/LoginTest.php`) because the CLI boot chdir()s to
`public/`.

> **Test error visibility.** `.env.testing` sets `APP_ENV=development`, so PHP
> errors surface in the phpunit output. Set `APP_ENV=testing` there (or pass
> `-e APP_ENV=testing`, which process env outranks) to silence them.

## First login on a fresh database

Migrations seed one admin user whose factory password is unusable until
claimed. Claim it once and store what it prints:

```bash
./docker_manage.sh -e <you> exec php bash /var/www/html/bin/cli_run.sh manager/tools/claim_admin
```

Write the printed credentials into `docker/env/<you>.agent.env` (gitignored;
template: `docker/env/sample.agent.env`) — never into committed files. Then
log in through the normal auth endpoint to obtain an `api_key` for
`X-API-KEY` requests. The command refuses once the account is claimed;
details and invariants live in the `ixaya-auth` skill (Bootstrap section).

CLI commands inside the running stack — always via `bin/cli_run.sh`, never
plain `php`:

```bash
./docker_manage.sh -e <instance> exec php bash /var/www/html/bin/cli_run.sh manager/health_checks
./docker_manage.sh -e <instance> logs -f php
```

## Valkey topology

Two Valkey instances per app instance:

| Instance | Holds | Policy | Persistence | Volume |
|----------|-------|--------|-------------|--------|
| `valkey-state` | **sessions** (db1) | `noeviction` | AOF (+RDB) | yes |
| `valkey-cache` | **cache + queues + pub/sub** (`LIB_REDIS`, db2) | `allkeys-lru` | none | no |

Sessions are durable (users must not be logged out on restart); the cache
side is evictable by design — a full cache on a `noeviction` store would make
**all** writes fail, so `LIB_REDIS` deliberately lives on the LRU instance.
Accepted tradeoff: queue entries riding `LIB_REDIS` are evictable under
memory pressure.

### Session save_path — required form

The CI3 redis session driver parses the password from **`auth=`** (not
`password=`) and the timeout only as `<int>.<int>`:

```
CF_SESS_SAVE_PATH=tcp://valkey-state:6379?timeout=10.0&prefix=mgr_session&database=1&auth=<valkey pw>
```

## Secrets layout & rotation

| What | Where | Reaches the app how | In `docker inspect`? |
|------|-------|---------------------|----------------------|
| App credentials (`LIB_REDIS_PASSWORD`, `CF_SESS_SAVE_PATH`, `DB_PASS`, `CF_ENCRYPTION_KEY`, API keys) | `docker/env/<i>.priv.env` (mode 600) | bind-mounted `ro` to `/var/www/html/.env.priv` | **No** |
| Non-secret config the app/entrypoint reads (incl. `DB_USER` — an identifier, not a secret) | `docker/env/<i>.env` | `--env-file` + `env_file:` | Yes (no secrets here) |
| Docker-infrastructure-only config (ports, tags, build args, limits) | `docker/env/<i>.docker.env` | `--env-file` interpolation only | N/A |
| Valkey server password | `docker/secrets/<i>.valkey_password` | compose secret → `--requirepass` via entrypoint | **No** |
| DB passwords (dev profiles) | `docker/secrets/<i>.db_password`, `.db_root_password` | compose secrets → `*_FILE` env | **No** |

No secret is ever in an image layer, a compose env-file, or `docker inspect`.

**Rotate the Valkey password** — update **both** files, then restart:

1. `printf 'NEW' > docker/secrets/<i>.valkey_password`
2. In `docker/env/<i>.priv.env`, set `LIB_REDIS_PASSWORD=NEW` **and** the
   `auth=` in `CF_SESS_SAVE_PATH=…auth=NEW`.
3. `./docker_manage.sh -e <i> up -d` (recreates affected containers).
   Existing session keys survive on valkey-state (AOF).

**Rotate the DB password** — update `docker/secrets/<i>.db_password` **and**
`DB_PASS` in `.priv.env`, alter the DB user, then `up -d`.

## Resource limits & tuning

Every service has `mem_limit` + `cpus` (env-overridable in
`<i>.docker.env`). FPM is `pm=dynamic` with `pm.max_children` from
`PHP_PM_MAX_CHILDREN` (20 dev / 50 prod reference). It is a **build arg** —
baked into the pool at image build time — so changing it requires a rebuild,
not a restart.

Size `PHP_MEM_LIMIT` from **measured** average worker RSS, not from PHP's
`memory_limit` (256M is a per-request ceiling, not a sizing input):
roughly `PHP_PM_MAX_CHILDREN × avg_RSS × 1.3 + OPcache shared memory
(128 MB)`. Reference: this stack measures ~32–34 MB per worker, making the
shipped `1024m` comfortable for 20 workers.

nginx's connection numbers are sized for the reference workload of ~200
long-lived WebSocket clients plus brief HTTP bursts — see the comments in
`docker/nginx/nginx.conf` (`worker_connections`, `worker_rlimit_nofile`) and
raise the compose `ulimits.nofile` together with `worker_rlimit_nofile` if
you ever increase them. WebSockets hold an nginx connection slot for their
whole lifetime but consume **zero** FPM children — they proxy to the `ws`
service, never to PHP-FPM — so size `PHP_PM_MAX_CHILDREN` from brief PHP
request concurrency only.

**Host prerequisites (production):** two Valkey-related kernel settings live
on the HOST (not container-settable) and Valkey warns at startup if missing:
`vm.overcommit_memory = 1` (via `/etc/sysctl.conf` + `sysctl`) and
transparent huge pages disabled.

## Agent access & smoke-test module

Procedure only — credential values live in `docker/env/<instance>.agent.env`
(gitignored, mode 600; template `docker/env/sample.agent.env`). On a fresh
database run `manager/tools/claim_admin` first (see "First login" above).

1. Source `<instance>.agent.env` for `AGENT_BASE_URL`, `AGENT_USERNAME`,
   `AGENT_PASSWORD`.
2. Log in through the app's **normal** auth endpoint with those credentials.
   The response includes an `api_key`.
3. Send it as the `X-API-KEY` header on subsequent requests.

There is no separate token mechanism for agents — this is the same login →
API-key flow any client goes through.

`docker/php/smoke/` holds a thin CI3 module (`smoke`) of live-wiring probes
(`GET /smoke/async` dispatches a real async CLI job; `GET /smoke/whoami`
proves the full login → X-API-KEY chain). Probes only — no assertions,
fixtures, or reporting. Auth is the normal API-key
auth above, never a bypass, and every controller 403s loudly if
`ENVIRONMENT === 'production'`. Two ways to use it:

```bash
# Baked into a local image (INCLUDE_SMOKE_MODULE=true, local images only):
./docker_manage.sh -e local build && ./docker_manage.sh -e local up -d

# Hand-copied for a -b instance (no rebuild needed):
cp -r docker/php/smoke/* application/modules/smoke/
```

With the flag `false` (the default), the module never enters an image layer
at all. `application/modules/smoke/` is gitignored specifically so the
hand-copy never gets committed — remove the directory when done.

## Troubleshooting

### Config behaves as if a value never loaded

Check what the app actually resolves — `.priv.env` values are invisible to
`printenv` by design (they reach the app as a mounted file, not process
env), and env files use last-key-wins so an empty-looking section proves
nothing:

```bash
./docker_manage.sh -e <instance> exec php bash /var/www/html/bin/cli_run.sh manager/tools/env_check
```

It reports per key which source won (process env, `$_ENV`, `.env.priv`,
`.env`) — values are never printed. Pass a key name to check a single key.

### Silent 500 with empty logs — the pre-logger fatal

**A failing bare `GET /` is a finding, not a wrong URL.** In the default
setup `/` routes to a REST controller, and every REST request runs the full
stack before any controller logic: bootstrap, routing, auth machinery, and a
DB **write** (the api_log insert). So a bare `/` is a legitimate end-to-end
health probe — it surfaces a dead DB connection or missing schema
immediately. If `/` 500s, do NOT go hunting for the "correct" entry point;
the failure is app-wide and path-hunting just produces identical 500s.

The visibility ladder for a failing request, in order:

1. `docker logs <i>-php-1` — PHP `error_log` → container stderr.
2. `/var/log/manager/{app,cli,cron}` in-container — the app's own log.
3. `application/logs/` — CI3's default location (normally unused here).

**All three empty does NOT mean no error** — it means the failure happens
*before* the app's logging subsystem initializes, so nothing can record it.
Don't keep re-checking the same channels; escalate instead:

1. Flip `display_errors`/`display_startup_errors` on (dev instance only).
   If the body is STILL empty, the error handler itself is failing too — go
   straight to step 3.
2. Enable `db_debug` on the DB config so connection failures surface.
3. Use the silent-fatal probe (see the `ixaya-live-probes` skill,
   `references/silent-fatal-probe.md`) — a try/catch + shutdown-function
   wrapper that forces out both catchable throwables and true fatals.

**Known signature: `Call to a member function ... on false` deep in the
query builder** (`real_escape_string() on false`, `result_array() on
false`). This always means the same thing: the DB **connection** itself
never succeeded, and with `db_debug` off the connect failure was swallowed —
`conn_id` stays `false` and the first query fatals with this misleading
TypeError instead of a clean "could not connect". Don't chase the query
builder. Check what the app resolves for the DB credentials
(`manager/tools/env_check` — no key = framework must-haves, includes all
`DB_*`), then `db_debug`. A docker smoke test burned a full debug session
tracing this exact signature to a `DB_PASS` that silently resolved to null.

### My edit isn't taking effect

`opcache.validate_timestamps=0` is baked into every image. If you edit a PHP
file and re-test WITHOUT `-b`/`-m` mode active, PHP still serves the old
bytecode — until the FPM master reloads (`kill -USR2 1`) or the container is
recreated from a rebuilt image. Easy to misread as "my fix didn't work".
Before trusting a negative test result, check: was this container running
before the edit, and is the relevant bind mode active? Under `-b`/`-m` the
dev ini enables timestamp validation, so edits apply on the next request and
this confusion can't happen.
