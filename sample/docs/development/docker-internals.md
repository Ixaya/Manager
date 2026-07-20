# Docker stack — internals (for anyone editing files under `docker/`)

> Scope: conventions, hard rules, and earned gotchas for *developing* the
> stack — editing the Dockerfile, compose files, configs, or
> `docker_manage.sh`. For running and operating it, see `docker.md`.

## Comment style — files under `docker/` and `docker_manage.sh`

Comments in these files are written for the person implementing/running the
stack — configuring it, deploying it, debugging it live — not for someone
redesigning it. State the value, the constraint, and what breaks if it's set
wrong.

**Discovery runs one direction: this file points at the config file, never
the other way around.** A comment inside a `docker/` config/build file must
never say "see docker-internals.md" or point to any doc. If a comment feels
like it needs that pointer, the content belongs here instead.

- **1–2 lines per comment.** Up to **4 lines** for a misconfiguration
  warning stated as a direct constraint/consequence ("must stay above X, or
  Y breaks"), not a design justification.
- **Nothing gets deleted outright — it moves.** When trimming a config-file
  comment, confirm the removed knowledge is captured here in an entry that
  leads with the exact file path, so it's findable by searching for the file.
- No history (dates, session labels, "the operator decided X"), and never
  address a comment to an LLM/agent.

## File map

| Path | What it is |
|------|------------|
| `docker-compose.yml` | Core services + all profiles. The only compose file loaded unconditionally. |
| `docker-compose.dev-bind.yml` | Opt-in override, only loaded with `-b`/`--bind`. See "Never bind" below before touching it. |
| `docker-compose.manager-bind.yml` | Opt-in override, only loaded with `-m`/`--manager-bind`. Same caution. |
| `docker_manage.sh` (repo root) | The only supported entrypoint — computes per-instance file paths the compose file depends on. |
| `env/sample.docker.env`, `env/sample.env`, `env/sample.secrets.env`, `env/sample.agent.env` | The four committed templates (short comments by design — per-var background lives in "Env template notes" below). Every other file under `env/` is a per-instance, ignored instantiation. |
| `php/smoke/` | The smoke-test module's **committed source**. Never ignored anywhere — if you ever see an ignore rule that would catch it, that's a bug; stop and report it. |

## Hard rules

- **Never bind `vendor/` or `bin/` in the `-b`/`--bind` override
  (`docker-compose.dev-bind.yml`).** `vendor/` is composer-managed,
  read-only, and the override's design assumes it can't drift from what's
  baked into the image. `bin/` must keep the image's Alpine-corrected
  `docker/php/bin/cli_run.sh` (see "the ONLY cli_run.sh" below) — binding
  the repo's `bin/` over it silently reintroduces a path bug that only
  manifests on Alpine, so it looks fine locally and fails in the container.
  - **Narrow, deliberate exception:** `docker-compose.manager-bind.yml`
    binds `vendor/ixaya/manager/system` specifically — nothing else under
    `vendor/`, never `bin/`. This is safe *only* because `ixaya/manager`
    declares no composer `autoload` section and nothing under `system/`
    uses a PSR-4 namespace — it's loaded by CI3/MX path-convention
    discovery exactly like `application/`, so there's no classmap to go
    stale. Do not extend this pattern to any other vendor package without
    re-verifying that same autoload-free precondition — an
    autoload/namespace-based package would silently break on new classes
    while bound.
  - **The `tools` service is not an exception either.** It mounts the whole
    project tree (`vendor/` and `bin/` included) at `/work` — a
    build/analysis sandbox, never a runtime service; the bind rules above
    protect the runtime services' `/var/www/html`, which `tools` never
    touches.
- **Never patch `vendor/`.** It's composer-managed; a local patch is
  silently discarded on the next `composer install`/rebuild. Fixes belong in
  the app layer (`application/`, a version bump, or an upstream issue).
- **`RUN_MIGRATIONS`/`WAIT_FOR_DB` reach containers ONLY via the explicit
  `environment:` block in `docker-compose.yml`**, not via the bulk
  `env_file:` load — `entrypoint.sh` reads them from the container's real
  process environment, and that block is what puts them there even though
  both vars live in `<i>.docker.env`. **Never remove that block** without
  moving both vars into `<i>.env`, or the entrypoint's checks silently stop
  working.
- **`WEBSOCKET_PORT` is dual-consumer and must stay in `<i>.env`, never
  `<i>.docker.env`.** It's read by the PHP app itself (the ws server's
  actual bind port) *and* by compose at render time for the `ws`
  healthcheck. Moving it to `.docker.env` silently breaks the app's bind
  port while the healthcheck still renders fine — the app-level breakage
  goes unnoticed until the ws service fails to bind.
- **Never set `CF_LOG_PATH` in any `docker/env/*.env` file.** Docker uses
  `MGR_LOG_PATH` exclusively (unified root, `manager-logs` volume at
  `/var/log/manager`); `CF_LOG_PATH` is legacy/on-prem-only. `config.php`
  gives `CF_LOG_PATH` precedence when both are set, so reintroducing it
  wouldn't error — it would silently pin the framework log to a
  non-mounted path while `cli/` continues on the unified root, splitting
  the streams without warning.

## Secrets audit — the standing definition (do BOTH layers)

Whenever a change touches how a secret reaches a container (compose
`secrets:`/`environment:`, a `command:`/entrypoint argument, an env file, or
the Dockerfile), verify no credential leaks — **both ways**. Grep for the
actual credential **values** (read them from `docker/secrets/<i>.*` and
`docker/env/<i>.priv.env`), not just the words `password`/`secret`, across
every running `<i>-*` container:

- **Layer A — `docker inspect`.** The value must appear nowhere; only secret
  *file paths* (`/run/secrets/…`, bind-mount `Source`) are acceptable.
- **Layer B — `docker top <container>` AND host `ps aux`.** The value must
  not appear in any process's argv either.

Layer B is not optional and easy to forget: an inspect-only audit passes
while a password sits in plain `ps` output — exactly how a Valkey
`--requirepass <pw>` argv leak once survived several audits. "Both layers,
values not names" is the definition of a passing secrets audit.

## Env var placement — check before adding any new var

Every instance has three env files, split by actual consumer, not by "feels
secret or not". The rule:

1. **Does the PHP app read it** (`mgr_env`/`getenv` anywhere under
   `application/` or the vendor framework)? → `<i>.env`.
2. **Does a script running INSIDE a container read it** (currently only
   `docker/php/entrypoint.sh`)? → `<i>.env`. (`entrypoint.sh` only sees
   what `env_file:` bulk-loads, which is `<i>.env` alone.)
3. **Is it ONLY ever referenced as `${VAR}` inside the compose files or by
   `docker_manage.sh` itself** (ports, image tags, build args,
   `mem_limit`/`cpus`, bind-mount source paths)? → `<i>.docker.env`.
4. **Is it a credential** (anything that would be bad in `docker inspect`)?
   → `<i>.priv.env`, regardless of 1–3.

**Before moving or reclassifying an existing var**, grep every `${VAR...}`
in the compose files against where it's actually defined across all three
file types — compose interpolation can only read `--env-file`-supplied
files, never the bind-mounted priv file, and a var that's interpolated but
defined only in `.priv.env` silently resolves empty.

**Diagnosing env from inside a container: use `manager/tools/env_check`
(or `mgr_env()`), never `printenv`/`getenv`** — see Troubleshooting in
`docker.md`.

### Env template notes (background stripped from the template comments)

The `env/sample.*` templates deliberately carry only short comments; the
per-var background lives here.

`sample.env` — the ONLY file that is both passed to compose via `--env-file`
AND injected into php/ws/cron/cli via `env_file:`. Its values ARE visible in
`docker inspect` — by design; it's the non-secret file.

Layout: base sections (a verbatim mirror of the root `.env.sample`) followed
by two override sections at the bottom — **the LAST occurrence of a key
wins** in both consumption paths. Duplicate keys are therefore intentional —
never "dedupe" this file.

- **"Docker specific"** — keys whose value ALWAYS differs in docker
  (`MGR_LOG_PATH`, `CACHE_ADAPTER=redis`, `CF_SESS_DRIVER=redis`,
  `LIB_REDIS_HOST=valkey-cache`, `WEBSOCKET_*`, instance identity). Never
  "fix" these to match the root sample.
- **"Docker deployment-dependent"** — the `DB_*` block, valid only with the
  bundled db profile; instances on an external/managed DB delete it and set
  the base `DB_*` values to the managed endpoint.

Refreshing after the root sample changes is mechanical: paste the root
`.env.sample` over the base sections, delete any `CF_LOG_PATH` line (the
single deliberate omission — hard rule above), keep the bottom sections.

- `MGR_LOG_PATH` — unified root for all Manager log streams; the app
  derives `app/` and `cli/` subdirs from it; the entrypoint creates both on
  boot. Trailing slash required.
- `DB_DRIVER` — `mysqli` (also for MariaDB) or `postgre`; never pdo.
- `DB_COLLATION` — `utf8mb4_0900_ai_ci` is MySQL-8-only; MariaDB needs a
  MariaDB collation; see the matrix comment in the root `.env.sample`.
- `CACHE_ADAPTER` — MUST stay `redis` so cache/queues/pub-sub all use the
  LIB_REDIS connection (they share one connection by design — the cache
  adapter cannot be pointed at a different host than `LIB_REDIS_*`).
- `LIB_REDIS_CHANNEL_PREFIX` — empty on purpose: per-instance Valkey
  isolation makes a prefix moot.

`sample.docker.env`:

- `PHP_PM_MAX_CHILDREN` — BUILD arg (rebuild to change); 20 dev / 50 prod
  reference. Size `PHP_MEM_LIMIT` from MEASURED worker RSS (`docker.md`
  tuning section).
- `PHP_HEALTHCHECK_INTERVAL` — compose healthcheck override; takes effect on
  recreate, no rebuild.
- `NGINX_NOFILE` — container FD ceiling; must stay ≥ nginx.conf
  `worker_rlimit_nofile`, raise both together.
- `MYSQL_*`/`MARIADB_*`/`POSTGRES_*` limits — dev/local db profiles only.
- `VALKEY_*_MAXMEMORY` — passed as a `command:` argument, not env.
- `MEDIA_PATH`/`PRIVATE_PATH` — host bind-mount SOURCE paths; the container
  only ever sees the fixed TARGET.
- `RUN_MIGRATIONS` — set `true` on exactly ONE php instance to migrate on
  boot.
- `INCLUDE_SMOKE_MODULE` — build arg; local images only.

`sample.secrets.env` — the `MUST equal docker/secrets/<i>.*` pairings are
load-bearing: `LIB_REDIS_PASSWORD` ↔ `<i>.valkey_password`, `DB_PASS` ↔
`<i>.db_password`. `CF_SESS_SAVE_PATH` embeds the same Valkey password via
`auth=` — the CI3 redis session driver parses `auth=` (NOT `password=`) and
requires timeout in `<int>.<int>` form. `DB_USER` is deliberately NOT here
(identifier, not a secret — and compose interpolation for
`MYSQL_USER`/`POSTGRES_USER` can only read `--env-file`-supplied files,
never the bind-mounted priv file).

`sample.agent.env` — consumed entirely OUTSIDE docker by whoever drives the
smoke-test endpoints. Instance copies are mode 600 and ignored, like every
other secret-bearing file here.

## Build gotchas (things that have already broken once)

- **Restricting nginx's PHP location to `index.php` alone isn't enough —
  any other `.php` under `public/` must also be explicitly denied.** Without
  a `deny all` catch-all, an unmatched `.php` falls through to `location /`'s
  `try_files` and gets served as a downloadable static file — raw source.
- **`supercronic`'s download URL must use `${TARGETARCH}`**, not a hardcoded
  arch. `TARGETARCH` is populated automatically by BuildKit; declare
  `ARG TARGETARCH` in the stage before using it.
- **The supercronic download is verified against a per-arch `sha256sum` pin,
  and the pins are generated only by `bin/supercronic-checksums.sh`** —
  never by hand, from memory, or by copying GitHub's asset digest. The
  provenance chain matters: aptible publishes only a **SHA1** per arch in
  the release notes (author-authored, independent of the asset bytes), and
  GitHub's SHA256 `digest` is *computed from the asset bytes* — it
  self-heals if a hijacked token swaps the binary, so it's not an
  independent attestation. The script (1) reads aptible's SHA1 from the
  release notes, (2) downloads and verifies against that SHA1, (3) computes
  the SHA256 of the *verified* binary. To bump: change the version in the
  Dockerfile's download URL, run `bin/supercronic-checksums.sh <version>`,
  paste its printed `case` block over the pins. The script is a
  host/maintenance tool — NOT part of the image build; the repo-root `bin/`
  is never copied into any image.
- **PECL extension versions (`apcu`, `redis`, `msgpack`) are pinned, not
  latest.** Read running versions empirically from a built image
  (`php -r 'echo phpversion("apcu");'`) before bumping — never guess.
- **Composer runs only in the `vendor-builder` stage; the runtime `php-app`
  stage has no composer.** Both descend from `php-base`. Never collapse
  this back into a single stage — that reintroduces the composer binary and
  its cache (~114MB) into the runtime image.
- **The FPM pool `ARG`/`COPY`/render block sits at the end of `php-app`.**
  Keeping it last means changing `PHP_PM_MAX_CHILDREN` re-runs only the
  small render, not the big copies.
- **`.dockerignore`'s `docker/` allowlist is scoped to exactly the subdirs
  the Dockerfile `COPY`s** (`docker/php/`, `docker/nginx/`). Never widen it
  to a blanket `!docker/` — that reintroduces `docker/secrets/` and
  `docker/env/*.priv.env` into the build context.
- **`sockets` needs `linux-headers`** in the build-deps virtual package —
  without it `docker-php-ext-install sockets` fails on base Alpine.
- **The stock `nginx` image ships its own `default.conf`** with a `:80`
  server block that shadows a custom vhost on the same port. Always
  `RUN rm -f /etc/nginx/conf.d/default.conf` before `COPY`-ing custom
  `conf.d/` files.
- **Busybox `wget` in Alpine resolves `localhost` to `::1` (IPv6) first.**
  Any healthcheck against a service that only binds IPv4 must use
  `127.0.0.1` explicitly.
- **Application code is NOT in composer's autoload scope; there is no
  `dump-autoload` step in the build.** The app loads via CI's MX loader.
  Do not reintroduce `composer dump-autoload` after copying `application/`
  "so the module is picked up" — it never was.
- **macOS's case-insensitive filesystem hides Linux path-casing bugs.** A
  `require` with the wrong case can work on a Mac and fail with "Failed to
  open stream" only in the (Linux) container.
- **`docker/php/bin/cli_run.sh` is the ONLY `cli_run.sh` in the image; the
  repo-root `bin/` is not copied at all.** The docker copy has
  Alpine-correct paths (`/bin/nice`, `/usr/local/bin/php`). To change CLI
  invocation behavior inside a container, edit `docker/php/bin/cli_run.sh`;
  editing the repo's `bin/cli_run.sh` has no effect on any container.
- **`php-base` activates `php.ini-production` before `conf.d/` loads.**
  Concretely: `zend.exception_ignore_args` defaults Off upstream — an
  uncaught exception's logged trace would include full function arguments
  (e.g. a password through `login($email, $password)`). Production ini
  turns it On. If the `RUN mv` line is ever removed, this protection
  silently regresses.
- **`docker-compose.yml`'s `php` healthcheck `test:` must mirror the
  Dockerfile's `HEALTHCHECK CMD` exactly.** The compose override exists
  only to make interval/timeout/retries configurable without a rebuild — a
  Dockerfile `HEALTHCHECK` can't be partially overridden, so the probe
  command is duplicated in both places. Change one → change both.
- **`docker/php/fpm.d/www.conf.template`'s `request_terminate_timeout`
  must stay above `docker/nginx/conf.d/app.conf`'s `fastcgi_read_timeout`**
  (65s vs 60s). `max_execution_time` only counts CPU time — a worker
  blocked on slow I/O runs past it; `request_terminate_timeout` is the real
  backstop, and it must exceed nginx's timeout so nginx gives up first.
- **`docker/nginx/nginx.conf`'s `worker_shutdown_timeout 30s` forces WS
  clients to reconnect ~30s after a reload.** WS connections are held open
  up to 3600s; without this, a reload leaves the old worker generation
  alive for the longest connection's lifetime — up to an hour of doubled
  workers.
- **Dev-only PHP conf.d overrides live in `docker/php/conf.d.dev/`, never
  `docker/php/conf.d/`.** The Dockerfile bakes `conf.d/` wholesale into
  every image; a dev-only ini placed there once got baked into prod-shaped
  builds because it loaded alphabetically last. `conf.d.dev/` is never
  `COPY`'d — only reached via the `-b`/`-m` bind-mounts — so a file there
  is structurally guaranteed to reach a container only in a dev mode.

## REST controllers in the smoke-test module

The general constructor-order rule (set
`$this->methods[...]['auth_override']` **before** `parent::__construct()`,
which runs the auth check immediately) is owned by the
`ixaya-rest-controller` skill — read it before editing any REST controller.

The docker-specific extension, used by the smoke-test module
(`docker/php/smoke/controllers/`): a controller that must never run in
production checks `ENVIRONMENT === 'production'` **AFTER**
`parent::__construct()` — in addition to the normal auth check, never
instead of it.

## `docker/valkey/entrypoint.sh` — password rendering and the root-start assumption

The password is rendered into a tmpfs config file (`include <real conf>` +
`requirepass`) rather than passed as `--requirepass` on the command line,
because `docker top`/host `ps` can read process argv even though `docker
inspect` and compose's `command:` stay clean. `valkey-server` also rejects
two positional config-file arguments outright, which is why an `include`
directive is used.

The script assumes the container always starts as root: it needs root to
read the secret file and to `chown` a fresh named volume, then drops to the
stock image's non-root `valkey` user via the same `chown`+`setpriv`
sequence. If compose ever adds a `user:` override on these services, both
steps start failing and fresh-volume ownership needs a different fix —
don't add `user:` to `valkey-state`/`valkey-cache` without revisiting this
script first.
