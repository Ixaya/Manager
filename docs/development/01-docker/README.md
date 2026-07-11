# Docker stack — operations guide

CodeIgniter-3 "Manager" app packaged as two lockstep images (PHP-FPM + nginx)
with Valkey, optional WebSocket/cron workers, and dev-only MySQL/MariaDB/Postgres.

See also:
- `decisions.md` — design decisions with evidence and revisit-when conditions.
- `gotchas.md` — agent-facing conventions, hard rules, and build gotchas for
  anyone editing files under `docker/`.

All operations go through the wrapper:

```bash
./docker_manage.sh -e <instance> [-b|--bind] <docker compose args...>
# expands to:
# docker compose -f docker/docker-compose.yml -p <instance> \
#                --env-file docker/env/<instance>.docker.env \
#                --env-file docker/env/<instance>.env <args...>
```

`<instance>` selects the env-files, secret files, project name (network/volume
namespace), and published ports — so multiple instances coexist with zero
cross-talk.

---

## 1. First-time setup

```bash
cp docker/env/sample.docker.env  docker/env/dev.docker.env
cp docker/env/sample.env         docker/env/dev.env
cp docker/env/sample.secrets.env docker/env/dev.priv.env
chmod 600 docker/env/dev.priv.env

# Valkey password (must match LIB_REDIS_PASSWORD and the auth= in CF_SESS_SAVE_PATH)
printf 'my-strong-valkey-pw' > docker/secrets/dev.valkey_password
chmod 600 docker/secrets/dev.valkey_password

# Only for the dev mysql/mariadb/postgres profiles (must match DB_PASS in .priv.env):
printf 'my-db-pw'   > docker/secrets/dev.db_password
printf 'my-root-pw' > docker/secrets/dev.db_root_password
chmod 600 docker/secrets/dev.db_*
```

Edit `dev.docker.env` (ports, image tags, resource sizing), `dev.env` (DB
host/name, cache/session/websocket config), and `dev.priv.env` (all
credentials). A `dev` instance is already provided in this repo with
throwaway dev passwords.

> Write secret files with `printf` (no trailing newline). The consumers strip a
> trailing newline anyway, but keeping them clean avoids surprises.

### Env files: the three-file model

Every instance has **three** non-agent env files, split by who actually
consumes each variable — not by "secret vs non-secret" alone:

| File | Who reads it | Injected into containers? | Visible in `docker inspect`? |
|------|---------------|---------------------------|-------------------------------|
| `<i>.docker.env` | `docker-compose.yml`/`docker-compose.dev-bind.yml` interpolation and `docker_manage.sh` itself (ports, image tags, build args like `PHP_PM_MAX_CHILDREN`/`INCLUDE_TEST_MODULE`, `mem_limit`/`cpus`, bind-mount source paths, `CODE_BIND_PATH`) | **No** — no service's `env_file:` references this file | N/A (never enters a container) |
| `<i>.env` | The PHP app itself (`mgr_env`/`getenv`, e.g. `APP_ENV`, `DB_HOST`, `CACHE_*`, `LIB_REDIS_*`, `WEBSOCKET_*`) **and** `docker/php/entrypoint.sh` (`MGR_LOG_PATH`, `WAIT_FOR_DB`, `DB_PORT`, `RUN_MIGRATIONS`) | **Yes** — the only file loaded via `env_file:` on php/ws/cron/cli | Yes (non-secret by design) |
| `<i>.priv.env` | The PHP app, via the bind-mounted `/var/www/html/.env.priv` **file** (not the process environment) | Mounted as a file, not `env_file:` | **No** — never in the process env |

**Why the split:** before this split, `<i>.env` held *everything* non-secret,
and since `env_file:` loads the whole file into php/ws/cron's process
environment, build/wrapper-only vars like `PHP_PM_MAX_CHILDREN` and
`INCLUDE_TEST_MODULE` leaked into the running containers even though nothing
inside them ever reads those vars. `docker exec <i>-php-1 env` now contains
none of `<i>.docker.env`'s vars — only what the app or `entrypoint.sh`
actually consumes. See `gotchas.md` "Env var placement" for the full
per-variable classification and the two special cases
(`RUN_MIGRATIONS`/`WAIT_FOR_DB` safely live in `.docker.env` despite being
read by `entrypoint.sh`, because `docker-compose.yml` re-injects them via an
explicit `environment:` block regardless of which `--env-file` supplies the
value).

`docker_manage.sh` requires both `<i>.docker.env` and `<i>.env` to exist and
aborts with a usage message if either is missing — same fail-loud contract
as the existing `.priv.env`/secrets checks.

---

## 2. Deploy procedure (build → tag → up → reload)

Both images are built and tagged together with the **same `IMAGE_TAG`**; the
nginx image bakes `public/` from the PHP image, so they must never drift.

```bash
# 1. Build both targets (php-app + nginx-app) at IMAGE_TAG (from the env-file).
./docker_manage.sh -e dev build

# 2. Bring up core (php, nginx, valkey-state, valkey-cache).
./docker_manage.sh -e dev up -d

# 3. First run only: migrate (or set RUN_MIGRATIONS=true on ONE instance).
./docker_manage.sh -e dev exec php bash /var/www/html/bin/cli_run.sh manager/tools/migrate

# 4. Deploy new code: rebuild at a new tag, up -d, then reload FPM (see §6).
```

Optional workers:

```bash
./docker_manage.sh -e dev --profile mysql up -d           # dev DB (Aurora-compatible)
./docker_manage.sh -e dev --profile mariadb up -d         # dev DB (lighter, disk-constrained devs)
./docker_manage.sh -e dev --profile ws --profile cron up -d
```

> **Build-time GitHub API rate limits (optional token).** `ixaya/manager` is
> a public Packagist dependency, so `composer install` during `build` needs
> no credentials to resolve or download it. A GitHub token is only ever
> useful to avoid GitHub's *unauthenticated* API rate limit (60 req/hr per
> IP) when composer fetches dist zipballs in repeated or CI builds — it is
> not an access requirement. If you hit rate-limit failures, pass one via
> `docker buildx build --secret id=composer_auth,src=auth.json` — the
> Dockerfile has a matching optional (`required=false`)
> `--mount=type=secret,id=composer_auth` that is simply ignored when absent.

### Profile matrix

| Profile | Service | Purpose | Prod? |
|---------|---------|---------|-------|
| _(core)_ | `php`, `nginx`, `valkey-state`, `valkey-cache` | Always on | Yes |
| `ws` | `ws` | WebSocket server (`manager/websockets/serve`, internal :9008) | Yes |
| `cron` | `cron` | supercronic runs `docker/cron/crontab` | Yes |
| `mysql` | `mysql` | MySQL 8.4 (`DB_HOST=mysql`) — Aurora-compatible, use when parity with the dev/prod server matters | No — dev/local only |
| `mariadb` | `mariadb` | MariaDB 12.3.2 (`DB_HOST=mariadb`) — lighter local alternative, NOT Aurora-compatible | No — dev/local only |
| `postgres` | `postgres` | PostgreSQL 16 | No — dev/local only |
| `cli` | `cli` | Interactive shell / one-off commands | As needed |

Production uses an **external** database (`DB_HOST=<managed endpoint>`, no db
profile). Valkey ports are **never** published; only nginx publishes
`HTTP_PORT` (→ :80) and `WS_PORT` (→ :8080).

### Bumping the supercronic version

The cron daemon binary is pinned by SHA256 per architecture. To move to a new
release, edit the version in the `supercronic-bin` stage's download URL in
`docker/Dockerfile`, then regenerate the pins:

```bash
bin/supercronic-checksums.sh v0.2.47      # prints a ready-to-paste `case` block
```

Paste its output over the existing `amd64)`/`arm64)` pins and rebuild. The
script verifies the download against aptible's published SHA1 before computing
the SHA256 it prints — see `gotchas.md` ("supercronic" build gotcha) for
why the pins are produced this way rather than copied by hand.

---

## 3. Valkey topology (Path B)

Two Valkey instances per app instance:

| Instance | Holds | Policy | Persistence | Volume |
|----------|-------|--------|-------------|--------|
| `valkey-state` | **sessions** (db1) | `noeviction` | AOF (+RDB) | yes |
| `valkey-cache` | **cache + queues + pub/sub** (`LIB_REDIS`, db2) | `allkeys-lru` | none | no |

Under Path B (full rationale and evidence in `decisions.md`):
- Sessions → `valkey-state` (durable; users must not be logged out on restart).
- `LIB_REDIS` (cache + queues + pub/sub) → `valkey-cache`. It is put on the
  **LRU** instance, not the noeviction one, because a full cache on a
  `noeviction` store would make **all** writes fail (OOM). pub/sub needs no
  persistence.

**Accepted tradeoff:** queue entries riding `LIB_REDIS` are evictable under
memory pressure — see `decisions.md` for the TODO to migrate to Path A.

### Session save_path — required form

The CI3 redis session driver parses the password from **`auth=`** (not
`password=`) and the timeout only as `<int>.<int>`:

```
CF_SESS_SAVE_PATH=tcp://valkey-state:6379?timeout=10.0&prefix=mgr_session&database=1&auth=<valkey pw>
```

---

## 4. Secrets layout

| What | Where | Reaches the app how | In `docker inspect`? |
|------|-------|---------------------|----------------------|
| App credentials (`LIB_REDIS_PASSWORD`, `CF_SESS_SAVE_PATH`, `DB_PASS`, `CF_ENCRYPTION_KEY`, API keys) | `docker/env/<i>.priv.env` (mode 600) | bind-mounted `ro` to `/var/www/html/.env.priv`, read natively by `MGR_Env_lib` | **No** — it's a mounted file, not env |
| Non-secret config the app/entrypoint actually reads (includes `DB_USER` — an identifier, not a secret; also interpolated for `MYSQL_USER`/`MARIADB_USER`/`POSTGRES_USER`, required with no default) | `docker/env/<i>.env` | `--env-file` (interpolation) + `env_file:` (into php/ws/cron/cli containers) | Yes (no secrets here) |
| Docker-infrastructure-only config (ports, image tags, build args, resource limits) | `docker/env/<i>.docker.env` | `--env-file` (interpolation ONLY — no service's `env_file:` references it) | N/A — never enters a container |
| Valkey server password | `docker/secrets/<i>.valkey_password` | compose secret → `/run/secrets/valkey_password`, fed to `--requirepass` by the entrypoint wrapper | **No** |
| DB passwords (dev profiles) | `docker/secrets/<i>.db_password`, `.db_root_password` | compose secrets → `*_FILE` env of mysql/mariadb/postgres | **No** |

Rules enforced: no secret is ever in an image layer, in a compose env-file, or
in `docker inspect`. `.dockerignore` keeps all `.env*` out of the build context.

### Rotation

**Valkey password** — update **both** files, then restart the instance:

1. `printf 'NEW' > docker/secrets/<i>.valkey_password`
2. In `docker/env/<i>.priv.env`, set `LIB_REDIS_PASSWORD=NEW` **and** the `auth=`
   in `CF_SESS_SAVE_PATH=…auth=NEW`.
3. `./docker_manage.sh -e <i> up -d` (recreates affected containers). Sessions/cache
   are re-authenticated; existing session keys survive on valkey-state (AOF).

**DB password** — update `docker/secrets/<i>.db_password` **and** `DB_PASS` in
`.priv.env`, alter the DB user, then `up -d`.

---

## 5. Resource limits & tuning

Every service has `mem_limit` + `cpus` (env-overridable). FPM is `pm=dynamic`
with `pm.max_children` from `PHP_PM_MAX_CHILDREN` (20 dev / 50 prod reference).
This value is a **build arg** — it is baked into the pool at image build time
(start/spare servers derived from it), so a dev image and a prod image can ship
different sizing. **Changing it requires a rebuild**, not a restart:

```bash
# e.g. a prod env-file sets PHP_PM_MAX_CHILDREN=50, then:
./docker_manage.sh -e prod build
```

`php` `memory_limit` (`docker/php/conf.d/10-app.ini`) is **256M per request** —
do **not** size the container cap from it. Size `PHP_MEM_LIMIT` from measured
average worker RSS (expect ~40–80 MB for CI3): roughly
`PHP_PM_MAX_CHILDREN × avg_RSS + headroom`.

`PHP_MEM_LIMIT=1024m` is confirmed: measured avg worker RSS of 32 MB (max
34 MB) under a 30-request concurrent load against the dev stack. 20 workers ×
34 MB = 680 MB + 1.3× headroom ≈ 893 MB → `1024m` gives comfortable margin
for OPcache shared memory (128 MB default) and payload spikes.

nginx uses `worker_processes auto`. Valkey `mem_limit` is set above each
instance's `maxmemory` to leave fork/COW headroom
(`VALKEY_STATE_MAXMEMORY` 256mb / `VALKEY_CACHE_MAXMEMORY` 128mb in dev).

**Host prerequisites (production):** two Valkey-related kernel settings
live on the HOST, not in any container, and Valkey logs a warning at
startup if they're missing:

- `vm.overcommit_memory = 1` — without it, AOF rewrite / background save
  forks can fail under memory pressure. Not container-settable at all
  (a single global VM-subsystem value, not namespaced); the host operator
  sets it once via `/etc/sysctl.conf` + `sysctl vm.overcommit_memory=1`
  (or a reboot).
- Transparent huge pages disabled — same host-wide scope as above.

`net.core.somaxconn` (the kernel's listen-backlog ceiling) IS
per-container settable via compose `sysctls:`, but isn't set today because
nothing indicates it's needed: it only matters if it's below Valkey's own
`tcp-backlog` setting (default 511), and modern kernels default well above
that (4096+). Add a `sysctls:` entry only once a real host actually logs
the backlog warning, sized from that host's real numbers alongside
`tcp-backlog` (raising one without the other has no effect, since the
effective backlog is the smaller of the two).

---

## 6. OPcache reload = the standard deploy step

`opcache.validate_timestamps=0` (`docker/php/conf.d/20-opcache.ini`), so PHP
never re-reads changed files. After
deploying new code you **must** reset OPcache by reloading the FPM master:

```bash
./docker_manage.sh -e <i> exec php kill -USR2 1
```

`SIGUSR2` gracefully reloads the FPM master (finishes in-flight requests) and
clears OPcache. In a rebuild-and-replace deploy, recreating the `php` container
achieves the same thing.

---

## 7. Dev live-code workflow (`-b` / `--bind`)

An **opt-in** override for local, multi-dev testing: bind-mount `application/`
and `public/` from a host checkout over the code baked into the images, so
several developers can exercise **combined** changes before opening a PR —
without touching how production images are built or deployed.

**Never used for prod instances.** Production always runs the baked,
immutable image. `-b` exists only to shorten the loop while iterating.

### How it works

```bash
./docker_manage.sh -e <instance> -b <compose args...>
```

- Appends `-f docker/docker-compose.dev-bind.yml` to the compose invocation.
- That file bind-mounts, **read-only**:
  - `${CODE_BIND_PATH}/application` → `/var/www/html/application` (php, ws, cron)
  - `${CODE_BIND_PATH}/public` → `/var/www/html/public` (php, ws, cron, nginx)
  - `docker/php/conf.d.dev/99-dev-opcache.ini`, which sets
    `opcache.validate_timestamps=1` so FPM picks up edits on the very next
    request — no reload, no `kill -USR2 1` (contrast with §6, the baked-image
    deploy path, which deliberately disables this for performance).
- `vendor/` and `bin/` are **never** bound. `vendor/` is composer-managed and
  read-only; `bin/` must keep the image's Alpine-corrected
  `docker/php/bin/cli_run.sh` rather than a host copy.
- `CODE_BIND_PATH` **must** be set in the instance's docker env-file
  (`docker/env/<instance>.docker.env`). Without it, `docker_manage.sh` aborts
  with a usage message before compose even runs — it never silently falls
  back to baked code. `docker-compose.dev-bind.yml` also declares the
  variable as `${CODE_BIND_PATH:?...}` (Compose's required-variable syntax)
  as a second, independent guard.
- Named volumes and existing binds (`app-logs` over `application/logs`, the
  `media`/`private` binds under `public/`/the app root) keep working nested
  inside the new read-only binds — Docker/runc establishes mounts
  parent-before-child by path depth regardless of declaration order, so the
  more specific mount always wins.
- Without `-b`, the compose invocation is byte-for-byte what it was before
  this flag existed — `docker-compose.dev-bind.yml` is never referenced.

### Host tree expectations

This workflow does not include tooling to manage the host checkout — by
design, it expects:

- A git checkout owned by one deploy user, group-readable (Alpine's
  `www-data`, uid 82, only needs **read**).
- Updated via `git pull` on a shared **integration branch**. Devs push their
  branches there (normal git flow); nobody edits the tree by any other means
  (no sftp, no manual copies) so the checkout stays a faithful mirror of
  what's under review.

### Per-dev instance recipe

Each developer (or each integration checkout) gets its own instance so
multiple `-b` sessions never collide:

```bash
cp docker/env/sample.docker.env docker/env/dev-<name>.docker.env
# edit dev-<name>.docker.env: unique HTTP_PORT/WS_PORT,
# and set CODE_BIND_PATH=/path/to/that/checkout
cp docker/env/sample.env docker/env/dev-<name>.env
# edit dev-<name>.env: unique DB_NAME
cp docker/env/sample.secrets.env docker/env/dev-<name>.priv.env
chmod 600 docker/env/dev-<name>.priv.env
# ...and the docker/secrets/dev-<name>.* files per §1

./docker_manage.sh -e dev-<name> -b up -d
```

A single-checkout setup (this repo testing itself) can just set
`CODE_BIND_PATH=..` in its own instance's docker env-file — see
`docker/env/local.docker.env`.

### Rebuild boundary

- **Needs `./docker_manage.sh -e <i> build`:** `composer.json`/`composer.lock`
  changes, and any new class that relies on the composer classmap
  (`--classmap-authoritative`) rather than CI3/MX's own module-path
  discovery.
- **Does NOT need a rebuild:** CI3 module controllers/models/views under
  `application/` — MX resolves these by file path convention at request
  time, not through the composer classmap. Edit and refresh; that's it.

---

## 8. Agent access (smoke-test module)

Procedure only — no credential values live in this file. Real values live in
`docker/env/<instance>.agent.env` (gitignored, mode 600; see
`docker/env/sample.agent.env` for the template).

1. Source `<instance>.agent.env` for `AGENT_BASE_URL`, `AGENT_USERNAME`,
   `AGENT_PASSWORD`.
2. Log in through the app's **normal** auth endpoint (the same one any
   client uses — `application/modules/auth/controllers/api/Login.php`) with
   those credentials. The response includes an `api_key`.
3. Send that value as the `X-Api-Key` header on requests to the smoke-test
   module (§9) or any other app endpoint that needs authentication.

There is no separate token mechanism for agents — this is the same login →
API-key flow a human or any other API client goes through.

---

## 9. Smoke-test module

`docker/php/tests/` holds a thin CI3 module (`tests`) of live-wiring probes —
e.g. `GET /tests/async`, which dispatches a real async CLI job and lets you
watch the effect land in `/var/www/logs/cli/`. **Scope discipline:** probes
only. No assertions, fixtures, or reporting — that's a different kind of
tool. Auth is the app's normal API-key auth (§8), not a bypass, and every
controller must 403 loudly if `ENVIRONMENT === 'production'` (belt-and-suspenders
in case it's ever baked into the wrong build).

Two ways to use it:

**Baked into a local image** (`INCLUDE_TEST_MODULE=true`, local images only —
set in `docker/env/local.docker.env`; `dev.docker.env` and any prod
docker env-file leave it unset, defaulting to `false`):

```bash
./docker_manage.sh -e local build
./docker_manage.sh -e local up -d
```

With the flag `false` (the default everywhere else), the module is copied
via a build-context bind-mount that only executes when the flag is `true` —
so with it `false`, the module never enters an image layer at all (confirm
with `docker history <image> | grep tests`, which should show nothing).

**Hand-copied for a `-b` instance** (no rebuild needed):

```bash
cp -r docker/php/tests/* application/modules/tests/
```

`application/modules/tests/` is in the repo's root `.gitignore`
specifically so this never gets committed by accident. Remove the directory
when you're done.

Both modes use the exact same controller code and the exact same auth flow
(§8) — the only difference is whether it's baked in or bind-mounted live.

---

## 10. Known verification gaps

Things that are believed correct but haven't been fully verified end-to-end,
and why:

- **amd64 image builds are verified by URL resolution only, not a full
  build.** The `TARGETARCH` fix for the supercronic download was confirmed to
  resolve the correct binary name (`supercronic-linux-amd64`) via
  `docker buildx build --platform linux/amd64 --target supercronic-bin`,
  but the actual download/execution step fails in environments without
  QEMU/binfmt cross-arch emulation registered — this has only been
  validated as a native arm64 build end-to-end. If you're building for
  amd64 for the first time, confirm the full build succeeds, not just that
  the URL resolves.
- **`docker/SVN_COMMANDS.md` has not yet been executed by an operator.**
  Every command in it is written to be copy-paste run and independently
  verified (propget → propset → verify, per directory), but no `svn`
  command has actually been run against this repo as part of building it —
  by design, since SVN is operator-only (see `gotchas.md`). Treat the
  secrets/env ignore protection it describes as *pending* until an operator
  has run it and confirmed the verification steps pass.
