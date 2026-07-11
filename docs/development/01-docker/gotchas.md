# Docker stack — agent gotchas & conventions

Agent-facing conventions and gotchas for the Docker stack under `docker/`.
This is not operator documentation — that's `README.md` (sibling file). This
file is for a future agent session about to touch this stack: what will
silently go wrong, what must never be done, and where to look before
changing something. See `decisions.md` for the design rationale behind
choices referenced here.

---

## Comment style — Dockerfile, nginx/php configs, any other file under `docker/`, and `docker_manage.sh`

Comments in these files are written for the person implementing/running this
stack — configuring it, deploying it, debugging it live — not for someone
developing or redesigning it. State the value, the constraint, and what
breaks if it's set wrong. Skip why it was built this way or what alternative
was considered — that's a maintainer concern, and a maintainer is already
reading this file (`gotchas.md`).

**Discovery runs one direction: this file points at the config file, never
the other way around.** A comment inside a `docker/` config/build file must
never say "see gotchas.md", "see README.md", or point to any
doc. If a comment
feels like it needs that pointer, that's the signal the content belongs
here, not that a pointer should be added.

- **1–2 lines per comment.** Up to **4 lines** is allowed specifically for a
  misconfiguration warning — stated as a direct constraint/consequence
  ("must stay above X, or Y breaks"), not a design justification.
- Write for someone configuring/running the file: the value, the
  constraint, what to change and when — not the reasoning chain behind why
  it was chosen that way.
- **Nothing gets deleted outright — it moves.** When trimming or
  reformatting a comment, confirm the removed knowledge is captured
  somewhere in this file, in an entry that leads with the exact file path
  (or file+directive) so it's findable by searching for the file. Only cut
  content from a config file once its knowledge has an address here.
- Do NOT write history into these comments: no dates, no "Item 5"/"Phase 2"/
  review-session labels, no "the operator decided X". That belongs in
  this file or `decisions.md`, not the file itself.
- Do NOT address the comment to an LLM or agent ("verify this before
  proceeding", "read the version via `docker run ...`").

This file is where the deeper, cemented background lives instead:
non-obvious rationale, what broke before and why, cross-file interactions,
and anything worth remembering across sessions that would be too long or
too narrative for an inline comment (see "Build gotchas" below for existing
examples of that split in practice — new entries should follow the same
"bold file path first" shape).

---

## File map

| Path | What it is |
|------|------------|
| `docker-compose.yml` | Core services + all profiles (`ws`, `cron`, `mysql`, `mariadb`, `postgres`, `cli`). The only compose file loaded unconditionally. |
| `docker-compose.dev-bind.yml` | Opt-in override, only loaded with `-b`/`--bind`. See "Never bind" below before touching it. |
| `docker_manage.sh` (repo root) | The only supported entrypoint. Never invoke `docker compose` directly against this stack — the wrapper computes per-instance file paths (`APP_ENV_FILE`, `APP_SECRETS_MOUNT`, `VALKEY_SECRET_FILE`, `DB_*_FILE`) that the compose file depends on. |
| `env/sample.docker.env`, `env/sample.env`, `env/sample.secrets.env`, `env/sample.agent.env` | The four committed templates (short comments by design — the per-var background lives in "Env template notes" below). Every other file under `env/` is a per-instance, gitignored/svn-ignored instantiation of one of these. See "Env var placement" below before adding a new var to any of them. |
| `secrets/` | Docker secret files (`<i>.valkey_password`, `<i>.db_password`, `<i>.db_root_password`). Real credential VALUES — never referenced by any compose `environment:` block, only by `secrets:`/`*_FILE`. |
| `Dockerfile` | The stack's build definition (at `docker/`, not `docker/php/`, since it builds BOTH images). Five stages; two real targets: `php-app`, `nginx-app`. All COPY sources are relative to the build context (project root, `context: ..`), not to this file's location. See "Build gotchas" below — several details are non-obvious and have already broken once each. |
| `php/entrypoint.sh` | Runs inside the `php-app` container at start. Reads `MGR_LOG_PATH`, `WAIT_FOR_DB`, `DB_HOST`, `DB_PORT`, `RUN_MIGRATIONS` from its own process environment — this is why those vars can never move to `.docker.env` (see "Env var placement"). `CF_LOG_PATH` is legacy/on-prem-only and is never read here. |
| `php/conf.d/20-opcache.ini` | Baked into the image, `validate_timestamps=0`. See "OPcache" below. |
| `php/conf.d.dev/99-dev-opcache.ini` | Lives in a directory the Dockerfile never `COPY`s (see "Build gotchas" — a wholesale `COPY docker/php/conf.d/` once baked this in by accident). Reaches a container ONLY via the `-b` bind-mount; sets `validate_timestamps=1`, loads after `20-` alphabetically so it wins when both are present. |
| `php/tests/` | The smoke-test module's **committed source**. Never gitignored/svn-ignored anywhere — if you ever see an ignore rule that would catch it, that's a bug, stop and report it. |
| `docker/php/bin/cli_run.sh` | The ONLY `cli_run.sh` baked into the image (Alpine-correct paths). The repo-root `bin/` is intentionally not copied at all. See "Never patch the wrong cli_run.sh" below. |
| `SVN_COMMANDS.md` | Operator-run only. See "SVN" below before you touch anything ignore-related. |
| `sample/` | Historical original-spec reference copy (pre-dates this implementation). Not live tooling. Never edit it, never treat it as a template — it exists for spec comparison only. |

---

## Hard rules

- **Never bind `vendor/` or `bin/` in any dev-bind override.** `vendor/` is
  composer-managed, read-only, and none of this stack's design assumes it
  can drift from what's baked into the image. `bin/` must keep the image's
  Alpine-corrected `docker/php/bin/cli_run.sh` (see "Never patch the wrong
  cli_run.sh" below) — binding the repo's `bin/` over it silently
  reintroduces a path bug that only manifests on Alpine, not on a dev's
  Mac/Linux host, so it can look fine locally and fail in the container.
- **Never patch `vendor/`.** It's composer-managed — `ixaya/manager` is a
  public Packagist dependency (resolved via Packagist's default, no
  `repositories` entry in `composer.json`), and a local patch will be
  silently discarded on the next `composer install`/rebuild. If something in
  `vendor/` is wrong, the fix belongs in the app layer (`application/`,
  `composer.json` version bump, or a flagged upstream issue), never a direct
  edit.
- **`RUN_MIGRATIONS`/`WAIT_FOR_DB` reach containers ONLY via the explicit
  `environment:` block in `docker-compose.yml`** (`RUN_MIGRATIONS:
  ${RUN_MIGRATIONS:-false}` on php/ws/cron), not via the bulk `env_file:`
  load — `entrypoint.sh` reads them from the container's real process
  environment, and that block is what puts them there even though both vars
  now live in `<i>.docker.env` (see "Env var placement"). **Never remove
  that block** without also moving both vars back into `<i>.env`, or
  `entrypoint.sh`'s `WAIT_FOR_DB`/`RUN_MIGRATIONS` checks silently stop
  working.
- **`WEBSOCKET_PORT` is dual-consumer and must stay in `<i>.env`, never
  `<i>.docker.env`.** It's read by the PHP app itself
  (`mgr_env_int('WEBSOCKET_PORT', ...)`, the ws server's actual bind port)
  *and* by compose at render time for the `ws` healthcheck literal. Moving
  it to `.docker.env` would silently break the app's own bind port even
  though the healthcheck would still render fine (it only needs
  compose-time interpolation, which either file satisfies) — the app-level
  breakage is the one that would go unnoticed until the ws service actually
  failed to bind.
- **Never set `CF_LOG_PATH` in any `docker/env/*.env` file.** Docker uses
  `MGR_LOG_PATH` exclusively (unified root, `manager-logs` volume at
  `/var/log/manager`); `CF_LOG_PATH` is legacy/on-prem-only now (see
  `README.md` §1). `config.php` gives `CF_LOG_PATH` precedence over
  `MGR_LOG_PATH` when both are set (specific overrides general) — so
  reintroducing it in a Docker env file wouldn't error, it would silently
  pin the framework log back to whatever path `CF_LOG_PATH` names (likely a
  non-existent, non-mounted one) while `cli/` continues on the unified root,
  splitting the two streams again without warning.
- **SVN is operator-only.** This repo is Subversion, not git (confirmed via
  a real `.svn/` working copy — every `.gitignore` file in the tree is
  dormant, since SVN never reads it). An agent must never run any `svn`
  command, read or write. If ignore rules need to change, write/update
  `docker/SVN_COMMANDS.md` — commands for a human to run and verify — don't
  attempt to infer or act on SVN state yourself.
- **`.gitignore` files are dormant backport artifacts, not the operative
  ignore mechanism.** They exist only so ignore rules travel if this
  codebase is ever backported to a git-based base framework. The ACTUAL
  protection in this repo is `svn:ignore`/`svn:global-ignores`, documented
  in `docker/SVN_COMMANDS.md`. If you add a file that should never be
  committed, update the relevant `.gitignore` (for backport fidelity) AND
  add/extend the matching section of `SVN_COMMANDS.md` (for it to actually
  do anything here) — one without the other leaves a real gap.

---

## Secrets audit — the standing definition (do BOTH layers)

Whenever a change touches how a secret reaches a container (compose
`secrets:`/`environment:`, a `command:`/entrypoint argument, an env file, or
the Dockerfile), verify no credential leaks — and verify it **both ways**.
Grep for the actual credential **values** (read them from
`docker/secrets/<i>.*` and `docker/env/<i>.priv.env`), not just the words
`password`/`secret`, across every running `<i>-*` container:

- **Layer A — `docker inspect`.** The value must appear nowhere; only secret
  *file paths* (`/run/secrets/…`, bind-mount `Source`) are acceptable.
- **Layer B — `docker top <container>` AND host `ps aux`.** The value must
  not appear in any process's argv either.

Layer B is not optional and is easy to forget: a `docker inspect`-only audit
passes while a password sits in plain `ps` output. This is exactly how the
Valkey `--requirepass <pw>` argv leak survived every earlier audit until it
was caught in an earlier audit pass — inspect was clean, the
argv was not. Any audit that greps only `inspect` is incomplete; treat "both
layers, values not names" as the definition of a passing secrets audit.

---

## Env var placement — check before adding any new var

Every instance has three env files, split by actual consumer, not by
"feels secret or not." Getting this wrong has already caused two real bugs
(a build-arg leak into running containers, and a silently-wrong MySQL/
Postgres username) — both fixed, see `decisions.md` ("Env scope split",
"`DB_USER` required, no default") for the rationale. The rule going forward:

1. **Does the PHP app read it** (`mgr_env`/`mgr_env_int`/`getenv` anywhere
   under `application/` or the read-only `vendor/ixaya/manager`,
   `vendor/nielbuys/framework`)? → `<i>.env`.
2. **Does a script running INSIDE a container read it** (currently only
   `docker/php/entrypoint.sh`, via `${VAR}` in its own bash process
   environment)? → `<i>.env`. (`entrypoint.sh` only sees what `env_file:`
   bulk-loads, which is `<i>.env` alone — never `<i>.docker.env`.)
3. **Is it ONLY ever referenced as `${VAR}` inside `docker-compose.yml`/
   `docker-compose.dev-bind.yml` or by `docker_manage.sh` itself** (ports,
   image tags, build args, `mem_limit`/`cpus`, bind-mount source paths)? →
   `<i>.docker.env`.
4. **Is it a credential** (password, key, token — anything that would be
   bad in `docker inspect`)? → `<i>.priv.env`, regardless of 1–3. Secrets
   never go in either non-priv file.

**Before moving or reclassifying an existing var**, grep every
`${VAR...}` in `docker-compose.yml`/`docker-compose.dev-bind.yml` against
where it's actually defined across all three file types — this is exactly
what caught the `DB_USER` bug (it was interpolated in compose for
`MYSQL_USER`/`POSTGRES_USER` but only ever defined in `.priv.env`, which
compose interpolation can never read). Don't assume a var's current
location is correct just because nothing has broken yet — `DB_USER` sat
wrong for the entire Phase 1/2 buildout before it surfaced.

**Special case, worth internalizing:** a var can be read by an
in-container script (rule 2) and still safely live in `.docker.env` if
`docker-compose.yml` also declares it under an explicit `environment:`
block on that service (see the `RUN_MIGRATIONS`/`WAIT_FOR_DB` hard rule
above) — the explicit block re-injects it from whichever `--env-file`
supplies the value, so the bulk `env_file:` load isn't the only path in.
Don't assume "read by entrypoint.sh" alone forces `.env` placement — check
whether an explicit `environment:` override already exists for it first.

**Today's full classification**, for reference:

- **`<i>.env`** (app and/or entrypoint.sh): the FULL non-secret app-var
  inventory — the template mirrors the root `.env.sample`'s Package/Project
  sections, since `env_file:` injection is the only channel for non-secret
  app config in a container (no `.env` file is mounted; only `.env.priv`
  is). Placement-tricky members: `MGR_LOG_PATH` (read by both `config.php`
  and `entrypoint.sh` — not dual-consumer in the `WEBSOCKET_PORT` sense
  since compose never interpolates it), `DB_PORT` (entrypoint.sh's
  `WAIT_FOR_DB` TCP check AND, since the DB-port config landed in
  `database.php` (`'port' => mgr_env('DB_PORT', null)`), the app itself),
  `DB_NAME`/`DB_USER` (also compose's `MYSQL_*`/`POSTGRES_*`
  interpolation), `WEBSOCKET_PORT` (dual-consumer, see hard rule above),
  and `CF_LOG_PATH` — the one root-sample var deliberately EXCLUDED from
  the docker inventory (hard rule above).
- **`<i>.docker.env`** (compose/build-arg/wrapper only): `HTTP_PORT`,
  `WS_PORT`, `IMAGE_REPO`, `IMAGE_REPO_NGINX`, `IMAGE_TAG`,
  `PHP_PM_MAX_CHILDREN`, `INCLUDE_TEST_MODULE`, `PHP_CPUS`, `PHP_MEM_LIMIT`,
  `PHP_HEALTHCHECK_INTERVAL`, `NGINX_CPUS`, `NGINX_MEM_LIMIT`,
  `WS_MEM_LIMIT`, `CRON_MEM_LIMIT`, `VALKEY_STATE_MEM_LIMIT`,
  `VALKEY_CACHE_MEM_LIMIT`, `VALKEY_STATE_MAXMEMORY`,
  `VALKEY_CACHE_MAXMEMORY`, `MYSQL_MEM_LIMIT`, `MYSQL_CPUS`,
  `MARIADB_MEM_LIMIT`, `MARIADB_CPUS`, `POSTGRES_MEM_LIMIT`,
  `POSTGRES_CPUS`, `MEDIA_PATH`, `PRIVATE_PATH`, `CODE_BIND_PATH`,
  `RUN_MIGRATIONS`, `WAIT_FOR_DB` (dual-consumer, see hard rule above).
- **`<i>.priv.env`** (secrets, mounted as a file, never `env_file:`):
  `LIB_REDIS_PASSWORD`, `CF_SESS_SAVE_PATH`, `DB_PASS`, `CF_ENCRYPTION_KEY`.
- **`<i>.agent.env`** (separate family, never read by compose or the app —
  only by whoever drives the smoke-test module from outside docker):
  `AGENT_BASE_URL`, `AGENT_USERNAME`, `AGENT_PASSWORD`.

### Env template notes (background stripped from the template comments)

The `env/sample.*` templates deliberately carry only short comments; the
per-var background that used to live there is here.

`sample.env` — the ONLY file that is both passed to compose via
`--env-file` AND injected into php/ws/cron/cli via `env_file:`. Its values
ARE visible in `docker inspect` — that's by design; it's the non-secret file.

Layout: base sections (a verbatim mirror of the root `.env.sample`) followed
by two override sections at the bottom — **the LAST occurrence of a key wins**
in both consumption paths (verified empirically with `docker compose config`).
Duplicate keys in this file are therefore intentional, not a mistake — never
"dedupe" it.

- **"Docker specific"** — keys whose value ALWAYS differs in docker
  (`MGR_LOG_PATH`, `CACHE_ADAPTER=redis`, `CF_SESS_DRIVER=redis`,
  `LIB_REDIS_HOST=valkey-cache`, `WEBSOCKET_*`, instance identity). Never
  "fix" these to match the root sample.
- **"Docker deployment-dependent"** — the `DB_*` block, valid only with the
  bundled db profile; instances on RDS/external DB delete it and set the
  base `DB_*` values to the managed endpoint.

Refreshing after the root sample changes is mechanical: paste the root
`.env.sample` over the base sections, delete any `CF_LOG_PATH` line (the
single deliberate omission — hard rule above), keep the bottom sections.

- `MGR_LOG_PATH` — unified root for all Manager log streams (framework +
  async CLI), mounted via the `manager-logs` volume. The app derives `app/`
  and `cli/` subdirs from it; the entrypoint creates both on boot. Trailing
  slash required.
- `DB_HOST` — production: external/managed endpoint, no db profile. Dev:
  `mysql` with `--profile mysql` (or `mariadb`/`postgres`).
- `DB_DRIVER` — `mysqli` (also for MariaDB) or `postgre`; never pdo.
- `DB_COLLATION` — `utf8mb4_0900_ai_ci` is MySQL-8-only; MariaDB needs a
  MariaDB collation (e.g. `utf8mb4_unicode_ci`); see the charset/collation
  matrix comment in the root `.env.sample`.
- `CACHE_ADAPTER` — MUST stay `redis` so cache/queues/pub-sub all use the
  LIB_REDIS connection (the "Path B" design decision, README §7).
- `LIB_REDIS_CHANNEL_PREFIX` — empty on purpose: per-instance Valkey
  isolation makes a prefix moot.
- `LIB_REDIS_PASSWORD` / `CF_SESS_SAVE_PATH` — secrets; `.priv.env` only.

`sample.docker.env`:

- `PHP_PM_MAX_CHILDREN` — BUILD arg (rebuild to change); 20 is the dev
  reference, 50 the prod reference. Size `PHP_MEM_LIMIT` from MEASURED
  worker RSS (README tuning section).
- `PHP_HEALTHCHECK_INTERVAL` — compose healthcheck override; takes effect
  on recreate, no rebuild needed.
- `MYSQL_*`/`MARIADB_*`/`POSTGRES_*` limits — dev/local db profiles only;
  production uses an external DB.
- `VALKEY_*_MAXMEMORY` — passed as a `command:` argument, not env; values
  in the template are dev defaults.
- `MEDIA_PATH`/`PRIVATE_PATH` — host/EFS bind-mount SOURCE paths. The
  container only ever sees the fixed TARGET (`/var/www/html/public/media`,
  `/var/www/html/private`); it never sees these variable names.
- `RUN_MIGRATIONS` — set `true` on exactly ONE php instance to migrate on
  boot.
- `INCLUDE_TEST_MODULE` — build arg; local images only.

`sample.secrets.env` — the `MUST equal docker/secrets/<i>.*` pairings are
load-bearing: `LIB_REDIS_PASSWORD` ↔ `<i>.valkey_password`, `DB_PASS` ↔
`<i>.db_password`. `CF_SESS_SAVE_PATH` embeds the same Valkey password via
`auth=` — the CI3 redis session driver parses `auth=` (NOT `password=`) and
requires timeout in `<int>.<int>` form (HANDOFF §2.1). `DB_USER` is
deliberately NOT here (identifier, not a secret — and compose interpolation
for `MYSQL_USER`/`POSTGRES_USER` can only read `--env-file`-supplied files,
never the bind-mounted priv file).

`sample.agent.env` — consumed entirely OUTSIDE docker by whoever drives the
smoke-test endpoints (log in via the normal auth endpoint, then use the
returned api_key as `X-Api-Key`). `local`'s seeded default is the admin
created by the first migration. Instance copies are mode 600 and
VCS-ignored, like every other secret-bearing file here.

---

## Build gotchas (things that have already broken once)

- **Restricting nginx's PHP location to `index.php` alone isn't enough —
  any other `.php` under `public/` must also be explicitly denied**, not
  just left unmatched. Without a `deny all` catch-all, an unmatched `.php`
  falls through to `location /`'s `try_files` and gets served as a
  downloadable static file — raw source code, not executed. Restricting
  execution without also denying access just trades one leak for another.
- **`supercronic`'s download URL must use `${TARGETARCH}`**, not a
  hardcoded arch — a dev laptop can be amd64 while this stack has also been
  built/tested on arm64. `TARGETARCH` is populated automatically by
  BuildKit; just declare `ARG TARGETARCH` in the stage before using it.
- **The supercronic download is verified against a per-arch `sha256sum` pin,
  and the pins are generated only by `bin/supercronic-checksums.sh` — never
  by hand, from memory, or by copying GitHub's asset digest directly.** The
  provenance chain matters: aptible publishes only a **SHA1** per arch in the
  release notes (an author-authored value, independent of the asset bytes),
  and supercronic ships no signatures or `SHA256SUMS` asset. GitHub's API
  exposes a SHA256 `digest`, but that is *computed from the asset bytes* — it
  self-heals if a hijacked token swaps the binary, so it is not an
  independent attestation. The script therefore (1) reads aptible's SHA1 from
  the release notes, (2) downloads the binary and verifies it against that
  SHA1 (a swap-only tamper fails here), then (3) computes the SHA256 of the
  *verified* binary. The pinned SHA256 is thus anchored to the bytes aptible
  vouched for, while giving the build a collision-resistant check (SHA1 alone
  is collision-weak). To bump: change the version in the Dockerfile's download
  URL, run `bin/supercronic-checksums.sh <version>`, and paste its printed
  `case` block over the pins. The script is a host/maintenance tool — it is
  NOT part of the image build and lives in the repo-root `bin/` (which is not
  copied into any image).
- **PECL extension versions (`apcu`, `redis`, `msgpack`) are pinned, not
  latest.** Read the running versions empirically from a built image
  (`php -r 'echo phpversion("apcu");'` or `pecl list`) before bumping —
  never guess a version number. Bump by changing the pin, rebuilding, and
  re-running smoke tests.
- **Composer runs only in the `vendor-builder` stage; the runtime `php-app`
  stage has no composer.** Both descend from `php-base` (shared PHP +
  extensions, built once). Never collapse this back into a single stage —
  that reintroduces the composer binary and its download cache into the
  runtime image (~114MB) and puts build tooling on the production surface.
  `vendor/` is `COPY --from=vendor-builder`; `composer.json`/`composer.lock`
  are deliberately not carried into runtime (the autoloader is
  self-contained).
- **The FPM pool `ARG`/`COPY`/render block sits at the end of `php-app`,
  after the `COPY --from=vendor-builder vendor` and the application copies.**
  Keeping it last means changing `PHP_PM_MAX_CHILDREN` re-runs only the small
  render. (The dependency install now lives in a separate stage the arg can't
  invalidate at all — but placing the render before the big vendor COPY would
  still needlessly re-copy it on every arg change.)
- **`.dockerignore`'s `docker/` allowlist is scoped to exactly the subdirs
  the Dockerfile `COPY`s** (`docker/php/`, `docker/nginx/` — the CLI runner
  now lives at `docker/php/bin/`, covered by `!docker/php/`). Never widen it
  to a blanket `!docker/` — that reintroduces `docker/secrets/` and
  `docker/env/*.priv.env` into the build context.
- **`sockets` needs `linux-headers`** in the Dockerfile's build-deps virtual
  package — without it, `docker-php-ext-install sockets` fails because
  `linux/sock_diag.h` isn't present in base Alpine.
- **The stock `nginx` image ships its own `default.conf`** with a `:80`
  server block that shadows a custom vhost of the same port. Always `RUN rm
  -f /etc/nginx/conf.d/default.conf` before `COPY`-ing custom `conf.d/`
  files into the `nginx-app` stage.
- **Busybox `wget` in Alpine resolves `localhost` to `::1` (IPv6) first.**
  Any healthcheck that curls/wgets `localhost` against a service that only
  binds IPv4 will fail — use `127.0.0.1` explicitly in Alpine-based
  healthchecks.
- **Application code is NOT in composer's autoload scope, so there is no
  `dump-autoload` step in the build.** `composer.json` declares no
  `autoload` section, and the app is loaded by CI's MX loader, not composer
  — verified empirically that the generated classmap is byte-identical with
  `application/` (and the smoke-test module) present or absent. Do not
  reintroduce a `composer dump-autoload` after copying `application/`
  "so the module is picked up": it never was, and the copy needs no
  autoloader step. `vendor/` depends solely on `composer.json`/`composer.lock`.
- **macOS's case-insensitive filesystem hides Linux path-casing bugs.** A
  `require` with the wrong case in a vendor file (e.g. `Rest_Controller.php`
  vs `REST_Controller.php`) can work fine on a Mac dev machine and fail with
  "Failed to open stream" only once built/run on Linux (which is exactly
  what the container is). If a vendor version bump ever reintroduces a
  case mismatch, this is why it wouldn't have been caught locally.
- **`docker/php/bin/cli_run.sh` is the ONLY `cli_run.sh` in the image; the
  repo-root `bin/` is not copied at all.** The docker-specific copy has
  Alpine-correct paths (`/bin/nice`, not `/usr/bin/nice`; `/usr/local/bin/php`,
  the FPM image's path, not `/usr/bin/php`). The repo's own `bin/` is a
  host/dev/deploy junk drawer (install.php, server.sh, publish scripts, a
  host-path cli_run.sh) and is deliberately excluded from the runtime image —
  so to change CLI invocation behavior inside a container, edit
  `docker/php/bin/cli_run.sh`; editing the repo's `bin/cli_run.sh` has no
  effect on any container built from this Dockerfile.
- **`docker/Dockerfile`'s `php-base` stage activates `php.ini-production`
  before `conf.d/` loads, so every directive not explicitly overridden runs
  on production defaults instead of PHP's compiled-in ones.** Concretely:
  `zend.exception_ignore_args` defaults to Off upstream, meaning an
  uncaught exception's logged stack trace includes full function
  arguments — e.g. a trace through `login($email, $password)` would log
  the password. Activating `php.ini-production` turns this On, stripping
  arguments from logged traces. If this `RUN mv` line is ever removed,
  this protection silently regresses back to the compiled default.
- **`docker-compose.yml`'s `php` service healthcheck `test:` command must
  mirror `docker/Dockerfile`'s `HEALTHCHECK CMD` exactly.** The compose
  override only exists to make the interval/timeout/retries configurable
  per instance without a rebuild — but a `HEALTHCHECK` in a Dockerfile
  can't be partially overridden, so the actual FastCGI probe command is
  duplicated in both places. If the Dockerfile's probe ever changes (a
  different pool status path, a different port), update both or the
  compose healthcheck silently drifts from what the image actually serves.
- **`docker/php/fpm.d/www.conf.template`'s `request_terminate_timeout`
  must stay above `docker/nginx/conf.d/app.conf`'s `fastcgi_read_timeout`
  (currently 65s vs. 60s).** `max_execution_time` only counts CPU time — a
  worker blocked on a slow DB query or external call runs past it
  indefinitely. `request_terminate_timeout` is the real backstop that
  kills such a worker, and it must exceed nginx's timeout so nginx is
  normally the one that gives up first; misalign them and either FPM kills
  workers nginx hasn't given up on yet, or nothing ever intervenes.
- **`docker/nginx/nginx.conf`'s `worker_shutdown_timeout 30s` forces
  WebSocket clients to reconnect ~30s after an `nginx -s reload`.** WS
  connections are held open up to 3600s (`conf.d/ws.conf`); without this
  directive, a reload leaves the old worker generation (and its live
  connections) running alongside the new one for as long as the
  longest-lived connection — up to an hour of doubled workers. Clients are
  expected to reconnect within the 30s window; raising or removing this
  value trades that off against a longer doubled-worker period on reload.
- **Dev-only PHP conf.d overrides live in `docker/php/conf.d.dev/`, never
  `docker/php/conf.d/`.** The Dockerfile bakes `docker/php/conf.d/` into
  every image wholesale (`COPY docker/php/conf.d/ ...`); a dev-only file
  placed there once (`99-dev-opcache.ini`) got silently baked into every
  build, including prod-shaped ones, because it loaded alphabetically
  after `20-opcache.ini` and won. `docker/php/conf.d.dev/` is never
  `COPY`'d by the Dockerfile — only reached via
  `docker-compose.dev-bind.yml`'s bind-mounts — so a file placed there is
  structurally guaranteed to reach a container only under `-b` mode. If
  you ever need a new dev-only PHP ini override, it goes in
  `conf.d.dev/`, never `conf.d/`.

---

## REST controller constructor order

The general rule — `$this->methods[...]['auth_override']` must be set
**before** `parent::__construct()` (which runs the auth check immediately),
and libraries should load opportunistically in the method that uses them,
not the constructor — is documented once, in the root `AGENTS.md`
("Coding Patterns"). Don't duplicate it here; that's the canonical source
for any REST controller in this app, docker-related or not.

The docker-specific extension, used by the smoke-test module
(`docker/php/tests/controllers/Async.php`): a controller that must never
run in production should check `ENVIRONMENT === 'production'` **AFTER**
`parent::__construct()`, not before or instead of the normal auth check.
Putting the guard after `parent::__construct()` means an unauthenticated
request never even reaches it (the parent's own auth failure already
calls `$this->response()`, which exits) — so the guard is genuinely a
second, independent layer on top of normal auth, not a replacement for it.
If you add another environment-gated controller, follow this same order:
normal auth via `parent::__construct()` first, environment check after.

---

## OPcache staleness — the debugging trap

`docker/php/conf.d/20-opcache.ini` bakes `opcache.validate_timestamps=0`
into every image. If you (an agent) edit a PHP file and then curl the app
to check your change, and you're NOT running under `-b`/`--bind` mode, your
edit will not be visible — PHP is still serving the compiled bytecode from
before your edit, and it will keep doing so until the FPM master is
reloaded (`kill -USR2 1` in the php container) or the container is
recreated from a rebuilt image. This is easy to misread as "my fix didn't
work." Before concluding that, check: was this container running before
the edit, and is `-b` mode active? If not-`-b` and pre-existing, reload or
rebuild before trusting a negative test result. Under `-b` mode,
`docker/php/conf.d.dev/99-dev-opcache.ini` (reached only via a bind-mount,
never baked — see the Build Gotchas entry on this) sets
`validate_timestamps=1` specifically so this class of confusion doesn't
happen — see `README.md` §6 and §7 for the operator-facing version
of this.

---

## `docker/valkey/entrypoint.sh` — password rendering and the root-start assumption

The password is rendered into a tmpfs config file (`include <real conf>` +
`requirepass`) rather than passed as `--requirepass` on the `valkey-server`
command line, because `docker top`/host `ps` can read process argv even
though `docker inspect` and compose's `command:` stay clean. `valkey-server`
also rejects two positional config-file arguments outright (`FATAL CONFIG
FILE ERROR`), which is why an `include` directive is used instead of passing
both files.

The script assumes the container always starts as root: it needs root to
read the secret file and to `chown` a fresh named volume, then drops to the
same non-root `valkey` user the stock image's own entrypoint uses, via the
same `chown`+`setpriv` sequence. If compose ever adds a `user:` override on
these services, both the `chown` and the `setpriv` re-exec would start
failing (a non-root process can't `chown` to another user or re-exec itself
as one), and fresh-volume ownership would need an entirely different fix —
don't add `user:` to `valkey-state`/`valkey-cache` without revisiting this
script first.
