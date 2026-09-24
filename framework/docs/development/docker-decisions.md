# Docker stack — design decisions

> Scope: this repo's Docker design rationale — decisions, evidence,
> revisit conditions. For running or editing the stack itself, see
> `docker.md` (beside this file), which points at the shipped docs.

One-time rationale with evidence, kept compact. The operational and
stack-editing docs ship with the stack itself — see
`sample/docs/development/docker.md` and `docker-internals.md`;
`framework/docs/development/docker.md` explains the split.

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
verified by a classmap diff — 38,332 entries byte-identical with
`application/` present or absent — so the previous second
`composer dump-autoload` was a no-op and was removed. Runtime image
681MB→567MB after the split.
Cost: two extra stages to reason about; both descend from `php-base`, so
never collapse them back (that reintroduces composer + its cache into runtime
and doubles the extension build).
Revisit when: never, unless composer itself becomes a runtime dependency
(it is not — the app uses the generated autoloader, which is self-contained).

**Dev tooling (PHPStan/PHPUnit) runs in the `vendor-builder` stage — no
separate testing image, no prod/dev mode switch.**
Decision: `composer.json` carries `phpstan/phpstan` + `phpunit/phpunit` +
`friendsofphp/php-cs-fixer` in `require-dev` (PHPUnit as
`^11 || ^12 || ^13` — 13 needs PHP 8.4, the OR keeps `composer install`
working at the framework's 8.2 floor); a profile-gated `tools` compose
service reuses the
`vendor-builder` build target and mounts the project tree at `/work`.
Why: fidelity — the tools run under the exact PHP + extension set the runtime
ships, so composer's platform checks and PHPStan's analysis match reality; a
separate testing Dockerfile would duplicate those decisions and drift.
Prod-image safety needs no mode flag because three independent guarantees
already hold: `composer install --no-dev` is hard-coded in the only stage
that runs composer; the runtime image contains no composer; and the build
context never copies a host `vendor/` (explicit COPY list).
Evidence: with dev packages present in `composer.lock`, a rebuilt
`vendor-builder` image contains neither `vendor/phpstan` nor
`vendor/phpunit` (verified 2026-07); `docker compose run` targets the
profile-gated service without `--profile`, so `docker_manage.sh` needed no
changes.
Cost: dev `vendor/` lives in the host tree (root-owned writes on Linux
hosts); a persistent named volume (`composer-cache`) for composer's cache.
Revisit when: CI needs static analysis without building `php-base`'s
extension set — then derive a slim stage `FROM` the same base rather than
writing a second Dockerfile.

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

**Concurrency sizing — nginx waiting room vs. FPM execution slots.**
Decision: `worker_connections 1024` (per worker), `worker_rlimit_nofile 4096`
with a matching nginx `ulimits.nofile` (8192) in `docker-compose.yml`;
`PHP_PM_MAX_CHILDREN` stays 20 dev / ~50 prod.
Why: two independent populations, sized separately. WebSockets proxy to the
`ws` service (`conf.d/ws.conf`), never to PHP-FPM — they hold an nginx
connection slot + ~2 FDs each for up to 3600s (they accumulate), but consume
zero FPM children. PHP requests are brief (1–3s) and are the only thing that
sizes `pm.max_children`. Reference workload: an internal portal of ~100–200
users, ~50 concurrent, worst case ~200 each holding one WebSocket. nginx must
absorb ~200 sticky WS + an HTTP burst; 1024/worker covers that even on a
single-core container (`auto` → 1 worker). FPM only sees the brief PHP
concurrency (a few dozen at peak), so ~50 is ample — WebSockets add nothing.
nginx cannot raise its own hard FD limit without `CAP_SYS_RESOURCE`, so the
container ceiling is pinned in compose (`ulimits.nofile`) rather than left to
the host default; raise it and `worker_rlimit_nofile` together.
Evidence: prod baseline (Apache mpm_event) caps at `MaxRequestWorkers 150` and
has never logged `pm.max_children` exhaustion; measured FD hard limits there are
524288, so 4096 takes effect for real. The previous `4096`/`65535` were copied
defaults ~100× the real workload; 65535 also exceeds locked-down sandbox NOFILE
ceilings, producing a harmless startup `[alert]`.
Cost: a spike beyond ~1024 concurrent per worker would queue in the TCP
backlog rather than being served immediately.
Revisit when: concurrent WebSocket clients approach `worker_connections`, the
portal opens to the public, or the `ws` service's own connection ceiling
becomes the binding limit before nginx's.

**Session password param is `auth=`, not `password=`.**
Decision: `CF_SESS_SAVE_PATH` uses `...&auth=<pw>` (see
`sample/docs/development/docker.md`, "Session save_path", for the exact
form).
Why: the CI3 redis session driver's parser only recognizes `auth=` and
requires the timeout as `<int>.<int>` — a literal `...&password=<pw>` form
would connect without auth and fail against a password-protected Valkey.
Evidence: `vendor/nielbuys/framework/system/libraries/Session/drivers/Session_redis_driver.php:137-148`.
Revisit when: never, unless the vendor driver's parser changes.

**Valkey is never network-exposed — with a pre-planned delta if that
changes.**
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
   closed — see `sample/docs/development/docker-internals.md`,
   `docker/valkey/entrypoint.sh`.)
2. Expose cache and state separately — and question exposing state at
   all: whoever holds `valkey-state`'s password holds every user session.
   If the need is a shared cache/queue, publish ONLY `valkey-cache`.
3. Compose `ports:` must bind the specific private interface
   (`"<vpc-ip>:<port>:6379"`, distinct host ports per instance) — never a
   bare port (= 0.0.0.0). The new vars are compose-interpolation-only →
   `<instance>.docker.env`, per `docker-internals.md` "Env var placement".
4. Replace the single `requirepass` god-user with ACL users per consumer;
   remote users get dangerous commands removed (`-@admin`, no
   `FLUSHALL`/`FLUSHDB`/`CONFIG`/`DEBUG`/`SHUTDOWN`/`KEYS`). The ACL file
   is a new secret → `docker/secrets/<instance>.*` pattern, mode 600.
5. Decide TLS-in-VPC consciously: Valkey supports native TLS (`tls-port`,
   cert mounts, disable the plain port); plaintext inside a VPC is a
   defensible policy call — record whichever is chosen here.
6. Security-group/firewall scoping to exact client CIDRs; re-evaluate
   idle `timeout` for remote clients (pub/sub subscribers stay exempt);
   and update IN THE SAME CHANGE: the compose header comment ("Only nginx
   publishes ports…"), the shipped docker.md's "Valkey ports are never
   published" line
   and its rotation procedure (the password now travels to other
   hosts).

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
Cost: resizing the pool needs a rebuild
(`./docker_manage.sh -e <instance> build`),
not just a restart — see the shipped docker.md tuning section.
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

**The app's own DB identifiers are required-or-fail — the framework-layer
twin of the compose `${DB_USER:?}` fix.**
Decision: `MGR_Env_lib::get_required()` (+ `mgr_env_required()` helper)
throws a `RuntimeException` naming the missing key and pointing at
`manager/tools/env_check`; `database.php` uses it for `DB_USER` and
`DB_NAME`. `DB_HOST` keeps its `localhost` default (a legitimate universal
convention); `DB_PASS` keeps `''` (an empty password is a real
configuration, and its absence is one `env_check` away).
Why not `''` as the default: an empty username/database doesn't fail loud —
with `db_debug` off (production) the connect failure leaves
`conn_id === false` silently and every request 500s with empty logs (the
exact `... on false` trap a docker smoke test burned a session on), and an
empty `database` even connects successfully before failing confusingly on
the first query. A silent `'root'` was worse still: it can *succeed* as the
wrong identity on a dev box.
Evidence: throw fires when CI loads the DB config — after `MY_Exceptions`
is wired for web requests, so it renders as a real error, not another
silent 500. Verified live: stack boots normally with `DB_USER` set; with it
unset the request dies with the named-key message.
Cost: none for existing consumers — `sample/` ships to new projects only;
the helper is additive.
Revisit when: never — this is the steady-state form, matching compose.

**Env scope split: `env_file:` loads the whole file.**
Decision: `<instance>.docker.env` (compose/build-arg/wrapper-only vars) is
intentionally never referenced by any service's `env_file:` — see the
"Env files" table in the shipped docker.md.
Why: `env_file:` has no way to load a subset of a file, so keeping
build/wrapper-only vars out of `<instance>.env` entirely is the only way to keep
them out of the container's real process environment (and thus out of
`docker exec ... env`/`docker inspect`).
Revisit when: a var currently in `.docker.env` ever needs to become
sensitive (a credential). At that point it doesn't move to `.env` — it
moves to `.priv.env` (the secrets file), following the same rule as every
other secret. If a var currently in `.docker.env` ever needs to be read by
the app or an in-container script, re-run the classification check in
`docker-internals.md` ("Env var placement") before moving it — don't assume
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

**`-m`/`--manager-bind`: a scoped exception to "never bind `vendor/`",
independent of `-b`.**
Decision: a second opt-in override, `docker-compose.manager-bind.yml`, binds
`${MANAGER_BIND_PATH}/system` (a host `ixaya/manager` checkout) over
`vendor/ixaya/manager/system`, read-only, on `php`/`ws`/`cron` — mirroring
`-b`'s shape but as a fully independent flag with its own required var, not
a mode of `-b`.
Why: the existing `-b`/`--bind` workflow exists to test
`application/`/`public/` changes live; there was no equivalent for testing
`ixaya/manager` framework changes without a `composer.lock` bump/publish/
tag cycle. `vendor/` is off-limits by the hard rule in `docker-internals.md`
because
that rule assumes composer-classmap-based loading that can silently go
stale — but `ixaya/manager`'s own `composer.json` declares no `autoload`
section, and a repo-wide grep found zero PSR-4 `namespace` declarations
under `system/`: it loads via the same CI3/MX path-convention discovery as
`application/`, so the classmap-staleness risk the hard rule guards against
doesn't apply to this one package. Kept as an independent flag (not `-m`
implying `-b`, not `-b` implying `-m`) because the more common real-world
use of `-m` alone is a consuming project isolating a suspected framework
bug by mounting their own `vendor/ixaya/manager` checkout — they may not be
running `-b` at all, and forcing `CODE_BIND_PATH` to also be set for that
case would be exactly the kind of silent, unrelated coupling this stack's
fail-loud design otherwise avoids. `-b -m` together (either order) is fully
supported for the self-testing case (one checkout, both vars pointed at
it) — verified the merged compose config resolves both mounts cleanly, and
that the duplicated `99-dev-opcache.ini` mount both files independently
carry (needed so `-m` gets live-reload standalone) is deduplicated by
Compose itself (identical source+target → one mount, confirmed via
`docker compose config` on `php`/`ws`/`cron`, both flag orders), not a
conflict.
Evidence: `composer.json` (repo root, no `autoload` key);
`grep -rl '^namespace ' system --include=*.php` (zero matches);
`docker/docker-compose.manager-bind.yml`; `docker_manage.sh`'s
`-m`/`--manager-bind` parsing; `docker-internals.md` "Hard rules" carve-out.
Cost: a second vendor-adjacent exception to reason about, narrowly scoped
to this one package/directory — do not extend the pattern to another
vendor package without re-checking that package has no composer autoload
section and no PSR-4 namespaces first.
Revisit when: `ixaya/manager` ever adopts a composer `autoload` section or
PSR-4 namespaces — at that point this override needs a `dump-autoload`
step (or a rebuild boundary, like `-b`'s classmap caveat) or it will
silently fail to pick up new classes while bound.

**Docker docs ship with the stack — root keeps only decisions.**
Decision: the operate doc (`sample/docs/development/docker.md`) and the
stack-editing doc (`sample/docs/development/docker-internals.md`) live in
`sample/` and ship to every consuming project; framework-side only
`docker-decisions.md` (rationale + evidence) and the `docker.md` pointer
remain, as flat files under `framework/docs/development/`. Reference
direction is one-way: root docs may deep-link into `sample/…`; shipped docs
must never reference framework-root `framework/docs/` or `framework/docs/workspace/` (those
paths don't exist in a consuming project). Guard:
`grep -rn 'docker-decisions' sample/` must stay empty — same for any other
framework-side doc name (`docs/workspace` mentions inside
`sample/docs/documentation.md` are the project's own convention, not
framework paths).
Why: the previous split was by where the writing happened, not by audience —
operational knowledge (rotation, OPcache reload, tuning, troubleshooting)
accumulated in root files consumers never receive, and new consumer-relevant
notes kept landing there by default. Consumers also develop the stack (it
lives in their tree), so the editing conventions ship too; only evidence
dossiers and internal context stay framework-side, since consuming projects
grow their own decisions.
Evidence: during the split, four stale imports from the stack's origin
project surfaced and were removed — the SVN hard rule ("this repo is
Subversion" — false here), the SVN_COMMANDS.md references, the already-fixed
supercronic-amd64 verification gap, and a missing-but-referenced
`bin/supercronic-checksums.sh` (recreated; verified it reproduces the
Dockerfile's existing pins from aptible's published SHA1s).
Cost: two docs to keep scoped (each carries a scope header naming its
audience); rationale is deliberately NOT duplicated into shipped files
beyond one-line whys.
Revisit when: a consuming project needs deep rationale routinely — then
consider shipping a condensed decisions extract, not a link.

**Docker is the only supported development path — never a bare host command.**
Decision: `composer`, PHPStan, php-cs-fixer, and the PHPUnit suite always run
through the stack — `docker_manage.sh ... run --rm tools <command>`, or the
`-b`/`-m` bound runtime services — never as a bare host command positioned as
an equal or fallback alternative. This holds regardless of whether the host
happens to have a working PHP install.
Scope: binds `framework/docs/development/` and `system/skills/`, where the reader is
guaranteed this repo's or the sample's Docker stack. Root guides may adopt
it by choice; a root document describing an environment this repo doesn't
control is judged on its own terms.
Why: a bare host run substitutes whatever PHP version and extension set the
developer's machine happens to have for the exact ones the stack pins — a
missing extension `.so` (ImageMagick) or a host PHP outside the framework's
8.2 floor / 8.4-era style reproduces a host artifact, not a framework bug, and
wastes a debugging session on the wrong layer.
Evidence: the corpus's five host-first sites (`sample/AGENTS.md`'s `composer
install`/`vendor/bin/phpstan analyse`/PHPUnit paragraph, `README.md`'s
PHPUnit note, `sample/docs/development/docker.md`'s `tools` row) now state
Docker as the sole path, not a fallback.
Cost: none — the `tools` service already exists for exactly this; the change
is presentational, not new tooling.
Revisit when: never — a future exception needs its own documented rationale,
not a silently reappearing bare command.

**A framework-dev instance should default to Postgres; MySQL/MariaDB are
parity-testing only.**
Decision: when bootstrapping an instance for framework development (not a
consuming project — see `sample/docs/development/docker.md`'s setup section
for the generic copy-from-`sample.*` steps), default it to `--profile
postgres` / `DB_DRIVER=pdo/pgsql`: after copying the templates to
`<instance>.env`/`<instance>.docker.env`/`<instance>.priv.env` and
`.env.sample` to `.env.<instance>`, override `DB_HOST=postgres` and
`DB_DRIVER=pdo/pgsql` in `<instance>.env` before first `up`. Bring up
MySQL/MariaDB only for the cross-engine matrix pass
(`framework-workflow.md`'s "Cross-engine verification"), never as the
everyday instance.
Why: this has been this repo's own dev/test default since Docker's
introduction for 2.0, independent of the separate PDO-vs-native driver
decision — that later change only picked which protocol reaches Postgres,
not the engine choice itself. Postgres is also the leading candidate under
discussion for consuming projects' own default (see "Should new projects
default to PostgreSQL instead of MySQL?", an open proposal) — dogfooding it
here keeps this repo's daily-driver experience aligned with where the
framework may be headed, independent of when/whether that proposal lands.
Evidence: this repo's own `local` instance already runs this way
(`local.env`'s `DB_DRIVER=pdo/pgsql` / `DB_HOST=postgres`, verified
2026-08-09) — this entry makes it an intentional, documented default
instead of tribal state in one gitignored file.
Cost: none — instance env files are gitignored and per-repo; a project's
own instance env is unaffected either way.
Revisit when: recreating a deleted instance env — default it back to
`--profile postgres` / `DB_DRIVER=pdo/pgsql`, not MySQL, unless a specific
session needs the MySQL/MariaDB matrix profiles instead.

**`mariadb` healthcheck uses `mariadb-admin`, not `mysqladmin`.**
Decision: the `mariadb` service's healthcheck runs `mariadb-admin ping`
instead of `mysqladmin ping`.
Why: the pinned `mariadb:12.3.2` image does not ship `mysqladmin` as a
compatibility alias for `mariadb-admin` — the healthcheck copied the
`mysql` service's command verbatim when the stack was introduced
(`8369804`, 2026-07-11) and was never live-verified against a real
`mariadb` container afterward. It went undetected because this repo's own
dev instance defaults to Postgres and the cross-engine matrix "in practice
... does not [run MariaDB] every time" (`framework-workflow.md`) — a fresh
project setup exercising the `mariadb` profile for the first time is what
surfaced it.
Evidence: live-verified 2026-08-24 on a throwaway instance — confirmed
`mysqladmin` absent and `mariadb-admin` present in the pinned image, and
the container reports `healthy` with the new command.
Revisit when: never, unless the pinned `mariadb` image tag changes and
needs re-verifying.
Superseded 2026-09-23: the check is now the image's own
`healthcheck.sh --connect --innodb_initialized`, with
`MARIADB_AUTO_UPGRADE=1`. A credential-less `mariadb-admin ping` reports
alive but logs `Access denied for user 'root'@'127.0.0.1'` on every run —
~8.6k warning lines/day per instance at the 10s interval, burying real
warnings. `healthcheck.sh` authenticates as the image's USAGE-only
`healthcheck` user, which the entrypoint creates only at init or, on an
older datadir, when `MARIADB_AUTO_UPGRADE` is set — without it, a volume
from before the user existed would stay unhealthy. Verified live: zero
`Access denied` lines; with the user and `.my-healthcheck.cnf` removed, a
restart recreated both and came back healthy. Cost of the flag: a version
bump now runs `mariadb-upgrade` on start (system tables backed up first).

**Runtime identity is one compose `user:`; a one-shot `init` service is the
only root step.**
Decision: `php`, `ws`, `cron` and `cli` all start as
`${APP_USER:-www-data}:${APP_GROUP:-www-data}` from `x-app-image`. The FPM
pool carries no `user`/`group`, so its master runs unprivileged too (the
build's `php-fpm -t -R` exists only because that build step is root). A core
`init` service (php-app image, `user: "0:0"`, `network_mode: none`,
`read_only`, mounting only `manager-logs`) creates `app/cli/cron` and
`find … -exec chown`s entries not already owned by the identity; the four
services `depends_on` it with `service_completed_successfully`. Bind mounts
are never touched — their ownership is the host's. This replaces the 2.3.6
design (identity as a build arg rendered into the pool, a root FPM master,
`docker_manage.sh` injecting `-u` on `exec`/`run`, and `entrypoint.sh`
chowning logs after a root migrate).
Why: the case the knob exists for is storage shared with things outside the
stack under a fixed, already-assigned uid (a legacy NFS/EFS tree), often
with several instances per host needing different uids. A build arg meant
one image per identity, a rebuild per change, and a named user added to the
image — FPM rejects `user = #1001`. A runtime identity takes a number with
no passwd entry, one image serves every instance, and no long-running
process is root.
Rejected: gid 0 via `group_add` plus a setgid `2775` log root (the
arbitrary-uid pattern) — puts every service in group root to avoid one
short root step. The wrapper's `-u` injection — it only fired when
`exec`/`run` was the first argument, so any compose flag before it (e.g.
`--profile ws exec php …`) silently ran as root; compose `user:` applies in
every argument order. User-namespace remapping / rootless Docker —
host-level, not shippable; `userns-remap` maps every container by one fixed
offset, so it cannot land an instance on a specific legacy host uid, and
rootless complicates the `mem_limit`/`cpus`/`cgroup_parent` sizing; worth a
doc mention as host hardening only. `cap_drop: [ALL]` — non-root processes
already hold `CapEff 0` and `no-new-privileges` blocks gaining any, so the
gain is only the bounding set. A blanket `chown -R` in `init` — it rewrites
every entry's ctime on every `up`; measured on 50k files: first run
`chown -R` ~85 ms vs `find` ~118 ms, steady state ~78 ms vs ~47 ms with no
writes.
Evidence: live-verified 2026-09-23 on a throwaway instance. Default: FPM
master, workers, `ws`, `cron` all `www-data`. `APP_USER=1234` with no passwd
entry and no rebuild: all healthy, `GET /` 200, `log_check` passes,
`RUN_MIGRATIONS=true` migrates as 1234. `nobody:nobody` (a name already in
the image) works without rebuild. A log volume left by the 2.3.6 stack is
reowned by `init`; a fresh one comes up `1234:1234`. `APP_USER=nosuchuser`:
`init` exits 1 (`find: unknown user`) and `php` stays `Created`. `run --rm
cli` runs `init` first. Not verifiable in that sandbox: host-side ownership
of bind mounts.
Cost: `init` runs on every `up` and shows as an exited container. A project
upgrading from 2.3.6 ports the sample files once and rebuilds; a named user
it added to its Dockerfile keeps working, and a leftover `ARG APP_USER` is
inert.
Revisit when: a service gains a mount that must be writable by the app
identity but isn't a bind mount — it joins `init`'s mounts, not `group_add`.

**`cli_run.sh` sets `umask 022` itself.**
Decision: `docker/php/bin/cli_run.sh` runs `umask 022` before exec'ing PHP.
Why: `docker exec` processes inherit the Docker daemon's umask, not the
container's. Where `dockerd` runs with umask 0, every file an exec'd CLI
command writes — including into `private/`/`media/` bind mounts — comes out
`0666`, writable by any host user. Neither compose nor the entrypoint reaches
an `exec`'d process; the CLI runner is the one path every framework command
takes. Ad-hoc `exec … sh` shells remain the daemon's.
Evidence: live-verified 2026-09-24 on a daemon with umask 0 (services and
`run` still `0022`): via `exec … cli_run.sh`, PHP's umask, a log file, a
`private/` file and an async-style `>>` redirect went `0000`/`666` →
`0022`/`644`. A systemd-managed host showed `0022` for `exec` already.
Cost: none.
Revisit when: never.

**One database container per instance, never a shared server.**
Decision: each instance keeps its own bundled DB container, credentials, and
volume; the tuning work sizes that model rather than consolidating it.
Why: an instance is initialized with its own database and password by
design, so its data and config move with it as one unit; and a misbehaving
site (a query that blows its memory budget) takes down only its own
database — the kernel OOM-kills inside the container that hit its cap.
Evidence: live OOM tests confined every kill to the container under load;
neighbours were untouched. The guarantee is conditional: it holds only
while the sum of caps fits the host (otherwise the host-wide OOM killer
picks any container), and it covers memory only — disk I/O and CPU credits
stay shared (a capped MySQL spilled 2.6 GB of temp tables to disk).
Cost: every instance pays its engine's fixed floor — measured idle, tuned:
MySQL ~155 MiB, MariaDB ~70, Postgres ~20–30. MySQL's floor barely moves
with its buffer pool, which makes it the expensive engine to multiply.
Revisit when: an engine's fixed floor times the instance count stops
fitting the hosts projects actually run on.

**Database engines are sized from `docker.env` flags, fitting inside their
cap — not left at stock defaults under a cap.**
Decision: each DB service's `command:` carries its sized knobs from
`docker.env` (buffer pool/shared buffers, `max_connections`, per-session
temp memory, `performance_schema`), next to its `mem_limit`; `memswap_limit`
equals `mem_limit` on every service. Defaults: MySQL 512m, MariaDB and
Postgres 640m, `max_connections` 30; MySQL `performance_schema` off and
binlog disabled; MariaDB `tmp_table_size` 4M; Postgres parallel query off,
`shm_size` 128m.
Why: no engine reads its cgroup limit, so a cap over stock settings is a
kill threshold, not a tuning — stock MySQL (`temptable_max_ram` 1G) can
outgrow its whole cap by itself. `max_connections` is the lever that makes
"concurrent sessions × per-session peak" finite, and past it clients get a
refused connection instead of the server being killed.
Evidence: 30 concurrent GROUP BY + ORDER BY sessions over ~450 MB: MariaDB
and Postgres were OOM-killed at 384m and 512m (stock per-session settings
and tuned ones alike), clean at 640m; MySQL peaked at 457 MiB inside 512m.
Temp-table spills produced dirty page-cache bursts up to ~90 MiB charged to
the container, which is why a cap sized from anonymous memory alone failed
by timing. Stock MySQL idled at 453 MiB, 224 of it `performance_schema`;
tuned, 162. Details and the small-host tier in the shipped
`docker-tuning.md`.
Cost: `performance_schema` diagnostics are off until re-enabled; MySQL
point-in-time recovery from binlogs is unavailable on the bundled profile
(dev-only by design).
Revisit when: a pinned database image tag changes (re-run the load test in
the shipped `docker-tuning.md`), a project runs the bundled profile as
anything other than dev/local, or MariaDB's cgroup memory-pressure feature (it logs "memory.pressure
not writable" in a stock container) becomes usable and can replace static
sizing.

**Fleet cap via `cgroup_parent`, opt-in and empty by default.**
Decision: every service merges an `x-fleet` fragment setting
`cgroup_parent: ${CGROUP_PARENT:-}`; the host owns the parent (a systemd
slice with `MemoryMax`) and its size.
Why: on a host shared with unrelated services, per-container caps only
protect those services if every cap on the box sums below available RAM.
One parent limit bounds the whole fleet however many instances it holds.
Evidence: empty value verified as Docker's default placement; a set value
applied to every service (`docker inspect` → `HostConfig.CgroupParent`).
The systemd-slice path is not exercised here — the dev sandbox runs the
`cgroupfs` driver.
Revisit when: verified on a systemd-driver host; record the result here.

**One healthcheck interval for every service, no dev/prod split.**
Decision: every healthcheck uses `interval: ${HEALTHCHECK_INTERVAL:-30s}`
and `retries: 3` (previously 10s for Valkey and the databases, 30s
elsewhere, databases at 10 retries; `PHP_HEALTHCHECK_INTERVAL` renamed).
FPM excludes `/ping` from its access log (`access.suppress_path`).
Why: outside Swarm nothing restarts an unhealthy container, so the
steady-state interval only decides how soon `docker ps` or an external
monitor sees trouble — 30s × 3 retries ≈ 90s. Startup ordering doesn't
depend on it: Docker probes every 5s during `start_period` regardless. The
one real dev/prod difference was log noise (an FPM access line per `/ping`,
~2.9k/day at 30s), fixed at the source instead of by stretching the
interval.
Evidence: probes measured at 20–65 ms wall each; ws/cron healthy 6s after
start on a 30s interval, php 5s after start on 300s. With
`HEALTHCHECK_INTERVAL=5s`, five php probes produced zero `/ping` log lines
while normal requests still logged. Host-side exec CPU not measured (daemon
runs outside the dev sandbox).
Revisit when: a deployment runs a monitor that acts on health status and
needs faster detection — shorten that host's `HEALTHCHECK_INTERVAL`, don't
fork the defaults.

**Valkey is part of the core set, not a profile.**
Decision: `valkey-state` and `valkey-cache` stay core services that `php`
waits on; the framework does not ship a Valkey-less shape.
Why: removing them is not a compose-only change — the cache must move to
`CACHE_ADAPTER=file` and sessions to the database driver, which is an app
configuration decision a project makes for itself. Projects on a tight
memory budget do exactly that, editing their own compose; `docker-tuning.md`
names it as one of the levers for fitting several instances on one host.
Revisit when: several projects carry the same Valkey-less edit, making a
supported profile cheaper than the drift.
