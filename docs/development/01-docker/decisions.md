# Docker stack — design decisions

One-time rationale with evidence, kept compact. See `README.md` for the
operational procedures these decisions inform.

**Lockstep images.**
Decision: nginx bakes `public/` from the PHP image at the same `IMAGE_TAG`;
both are always built and deployed as a pair.
Why: no shared code volume, no drift between served assets and running code.
Cost: you always deploy the pair, even for a static-asset-only change.
Revisit when: never, unless the static-asset deploy cadence needs to
decouple from the app deploy cadence badly enough to justify a shared
volume's added complexity.

**Builder-stage split — no composer in the runtime image.**
Decision: the Dockerfile is five stages — `php-base` (PHP + extensions,
built once and shared), `vendor-builder` (the only stage with composer;
installs `vendor/` from `composer.json`/`composer.lock`), `php-app` (runtime:
`COPY --from=vendor-builder vendor` + app code from the build context, no
composer, no manifests), plus `supercronic-bin` and `nginx-app`.
Why: a reference-sample runtime image should carry no build tooling. Isolating
composer to a discarded builder stage keeps the composer binary — and its
download cache — out of the runtime, and means an app-code change never
invalidates the dependency-install layer. `vendor-builder` descends from
`php-base` (not a bare `composer` image) so composer's platform checks run
against the exact extension set the runtime ships.
Evidence: application code is outside composer's autoload scope
(`composer.json` has no `autoload` section; the app loads via CI's MX loader),
verified by a classmap diff — 38,332 entries byte-identical with `application/`
present or absent — so the previous second `composer dump-autoload` was a no-op
and was removed. Runtime image 681MB→567MB after the split.
Cost: two extra stages to reason about; both descend from `php-base`, so
never collapse them back (that reintroduces composer + its cache into runtime
and doubles the extension build).
Revisit when: never, unless composer itself becomes a runtime dependency
(it is not — the app uses the generated autoloader, which is self-contained).

**Path B — Valkey cache/queues/pub-sub share one connection with cache.**
Decision: `LIB_REDIS_*` (cache + queues + pub-sub) → `valkey-cache`
(allkeys-lru); sessions alone → `valkey-state` (noeviction, AOF).
Why: cache, queues, and pub-sub all resolve the same `redis.php` connection
in the read-only `ixaya/manager` package; splitting them needs a second
connection there, which is out of scope for this repo. The cache adapter's
Redis connection cannot be pointed at a different host than `LIB_REDIS_*`
using only `application/config` — there is no cache-specific host key, no
`application/config/redis.php`/`cache.php` override in this repo, and the CI
cache-redis driver, the framework's queue/pub-sub methods, and the WebSocket
subscriber all read the same `config['redis']`. Sessions are independently
routable via a separate PHP save_path unrelated to `redis.php`, so they
already live on `valkey-state`. `LIB_REDIS` goes to the **LRU** instance, not
noeviction, because a full cache on a `noeviction` store would make **all**
writes fail (OOM) — worse than evictable queues; pub/sub needs no persistence.
Evidence: `vendor/ixaya/manager/system/package/config/redis.php:5-11`,
`vendor/nielbuys/framework/system/libraries/Cache/drivers/Cache_redis.php:130-167`,
`vendor/ixaya/manager/system/libraries/MGR/Cache/drivers/Cache_redis.php:45-56`,
`vendor/ixaya/manager/system/libraries/MGR_Websocket_lib.php:166-185`,
`application/config/config.php:402-403` (session save_path).
Cost: queue entries riding `LIB_REDIS` are evictable under memory pressure.
Revisit when: `ixaya/manager` adds a second Redis connection
(`LIB_REDIS_STATE_*`) — then migrate queues/pub-sub to `valkey-state` db2
(Path A) and this repo just adds the new connection's env vars.

**Session password param is `auth=`, not `password=`.**
Decision: `CF_SESS_SAVE_PATH` uses `...&auth=<pw>` (see README §3 for the
exact form).
Why: the CI3 redis session driver's parser only recognizes `auth=` and
requires the timeout as `<int>.<int>` — a literal `...&password=<pw>` form
would connect without auth and fail against a password-protected Valkey.
Evidence: `vendor/nielbuys/framework/system/libraries/Session/drivers/Session_redis_driver.php:137-148`.
Revisit when: never, unless the vendor driver's parser changes.

**Valkey is never network-exposed — with a pre-planned delta if that changes.**
Decision: no Valkey port is ever published; only nginx publishes ports
(`HTTP_PORT`, `WS_PORT`). Enforced by the compose file (no `ports:` on
either valkey service).
Why: the only consumers are the app containers on the instance's private
network; exposure adds attack surface with zero current benefit.
Revisit when: a same-VPC consumer needs direct access. The pre-planned
delta, in order:
1. Valkey must not run as root, and its password must not be visible in
   argv/`docker top` — a network-reachable store running as root with its
   password in argv is a different risk class. (Argv exposure is already
   closed — see `gotchas.md`, `docker/valkey/entrypoint.sh`.)
2. Expose cache and state separately — and question exposing state at
   all: whoever holds `valkey-state`'s password holds every user session.
   If the need is a shared cache/queue, publish ONLY `valkey-cache`.
3. Compose `ports:` must bind the specific private interface
   (`"<vpc-ip>:<port>:6379"`, distinct host ports per instance) — never a
   bare port (= 0.0.0.0). The new vars are compose-interpolation-only →
   `<i>.docker.env`, per `gotchas.md` "Env var placement".
4. Replace the single `requirepass` god-user with ACL users per consumer;
   remote users get dangerous commands removed (`-@admin`, no
   `FLUSHALL`/`FLUSHDB`/`CONFIG`/`DEBUG`/`SHUTDOWN`/`KEYS`). The ACL file
   is a new secret → `docker/secrets/<i>.*` pattern, mode 600.
5. Decide TLS-in-VPC consciously: Valkey supports native TLS (`tls-port`,
   cert mounts, disable the plain port); plaintext inside a VPC is a
   defensible policy call — record whichever is chosen here.
6. Security-group/firewall scoping to exact client CIDRs; re-evaluate
   idle `timeout` for remote clients (pub/sub subscribers stay exempt);
   and update IN THE SAME CHANGE: the compose header comment ("Only nginx
   publishes ports…"), README §3's "Valkey ports are never published" line,
   and README §4's rotation procedure (the password now travels to other
   hosts).

**Database extensions: `mysqli`/`pgsql`, not `pdo_mysql`/`pdo_pgsql`.**
Decision: the Dockerfile installs `mysqli` and `pgsql`, not either PDO
driver.
Why: CI3's native `mysqli` database driver calls `mysqli_*` functions
directly, and its `postgre` driver calls `pg_connect()` — neither one ever
touches PDO. Installing PDO drivers would be dead weight (no code path uses
them). The same `mysqli` extension also covers the `mariadb` dev profile —
MariaDB speaks the MySQL wire protocol, so no separate driver is needed.
Revisit when: the app's database layer is migrated to a PDO-based CI3
driver (`pdo/mysql`, `pdo/pgsql`) — not currently planned.

**WebSocket deps promoted from the framework's `require-dev` to this app's
`require`.**
Decision: `composer.json` directly requires `amphp/redis`,
`amphp/websocket-server`, `amphp/log`, `adhocore/jwt`.
Why: `ixaya/manager` declares them in `require-dev`, so a `composer install
--no-dev` build (this image's build) never installs them — the `ws` profile
would fatal with "class not found" otherwise. Confirmed via `composer.lock`
having zero `packages-dev`.
Cost: this app now tracks four dependency versions that conceptually belong
to the framework.
Revisit when: `ixaya/manager` promotes these to its own `require` — then
drop them from this app's `composer.json` and let the framework bring them
transitively.

**Extensions beyond the original spec list.**
Decision: `gd`, `mbstring`, `zip` are installed even though the original
spec didn't list them.
Why: `phpspreadsheet`/`pkpass`/`zipstream` (already-used app dependencies)
hard-require them — composer's platform check fails the build without them.
Revisit when: never, unless those app dependencies are dropped.

**FPM pool baked at build time, not rendered at runtime.**
Decision: `PHP_PM_MAX_CHILDREN` is a build arg (`www.conf` is rendered
during `docker build`, not by the entrypoint).
Why: this lets `php` run with a **read-only rootfs** (nothing needs to
write pool config at runtime) — same posture as `nginx` and `valkey`.
Cost: resizing the pool needs a rebuild (`./docker_manage.sh -e <i> build`),
not just a restart — see README §5.
Revisit when: never, unless the pool needs to resize without a rebuild
(would require reintroducing a writable rootfs for `php`).

**`DB_USER` required, no default — mirrors `DB_NAME`.**
Decision: `docker-compose.yml` uses `${DB_USER:?...}` for
`MYSQL_USER`/`MARIADB_USER`/`POSTGRES_USER`/the postgres healthcheck, never
a fallback default.
Why: `DB_USER` is an identifier the mysql/mariadb/postgres dev profiles
need for interpolation — a silent default (formerly `${DB_USER:-ixaya}`)
meant the provisioned username could silently diverge from whatever
`DB_USER` was actually intended to be, with no error. Same failure shape
`DB_NAME` was already protected against. The `mariadb` profile was added
after this fix and follows the same `${DB_USER:?...}` form from the start.
Revisit when: never — this is the correct steady-state form, matching
`DB_NAME`.

**The app's own `DB_USER` default is the framework-layer twin of the fixed
compose fallback — not yet fixed.**
Decision (not yet made — flagged for the next opportunity): `mgr_env('DB_USER',
'root')` in `application/config/database.php` still silently falls back to
`'root'` if `DB_USER` is ever absent from the app's process environment, the
exact same silent-default shape the compose-level `DB_USER` fix (above)
just closed one layer down.
Why not fixed now: `application/config/database.php` is application code,
not docker infrastructure — out of scope for the docker/env split, and
changing default-handling in a shared config file needs its own review
independent of docker.
Revisit when: the next `ixaya/manager`/app-config touch point — the
backport-worthy fix is "required-or-fail for DB identifiers" (no silent
default) at the `mgr_env`/config layer, mirroring what compose already
does.

**Env scope split: `env_file:` loads the whole file.**
Decision: `<i>.docker.env` (compose/build-arg/wrapper-only vars) is
intentionally never referenced by any service's `env_file:` — see the
"Env files" table in README §1.
Why: `env_file:` has no way to load a subset of a file, so keeping
build/wrapper-only vars out of `<i>.env` entirely is the only way to keep
them out of the container's real process environment (and thus out of
`docker exec ... env`/`docker inspect`).
Revisit when: a var currently in `.docker.env` ever needs to become
sensitive (a credential). At that point it doesn't move to `.env` — it
moves to `.priv.env` (the secrets file), following the same rule as every
other secret. If a var currently in `.docker.env` ever needs to be read by
the app or an in-container script, re-run the classification check in
`gotchas.md` ("Env var placement") before moving it — don't assume
the reverse move is symmetric with the forward one.

**HSTS/CSP/rate limiting ownership: not nginx, in either environment.**
Decision: `nginx` in this repo never sets `Strict-Transport-Security`,
`Content-Security-Policy`, or rate limits. Production: owned by
Cloudflare/Traefik, whichever sits at the edge — TLS is terminated there,
not in this stack, making it the natural place for TLS-dependent policy.
Local dev: there's no edge layer at all (nginx is the only hop), but the
question is moot regardless — HSTS is a browser directive that's only
honored over an actual HTTPS connection, and local dev is plain HTTP by
construction, so nothing would set it even if this repo wanted to. Rate
limiting has the same "nothing to protect against locally" shape.
Why: "the other layer does it" is exactly how a header ends up owned by
nobody — naming the layer explicitly here closes that gap without adding
config that has no effect in dev and would duplicate the edge in prod.
Note CSP is not quite the same shape as HSTS: it applies over plain HTTP
too (no TLS-only gate), and this repo does serve real HTML via the
`admin`/`frontend` app modules — so if a CSP is ever defined at the edge,
verifying it doesn't break those pages will eventually need testing
somewhere content actually renders, not just a header-presence check.
Revisit when: the edge layer's CSP (if/when defined) needs verification
against real page content — that's a real testing gap this decision
doesn't close, just correctly assigns elsewhere.
