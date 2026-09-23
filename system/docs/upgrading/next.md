# Upgrading — unreleased

Changes between 2.x releases that alter behavior a project already depends on.
Everything else in a minor release is additive.

### The bundled database profiles now ship tuned to fit their caps — adopting the new `docker-compose.yml` changes their defaults

Reaches a project only when it reconciles `docker/docker-compose.yml` and
`docker/env/sample.docker.env` from the sample; `composer update` alone
changes nothing. The dev-only `mysql`/`mariadb`/`postgres` profiles
previously ran engine stock settings under a fixed cap, and the engines never
size themselves from it. Adopting the new files changes, for those profiles:

- **Caps:** MySQL 1024m → 512m; MariaDB and PostgreSQL 512m → 640m.
- **`max_connections`:** stock (151 / 100) → 30. Raise it with
  `*_MAX_CONNECTIONS` if your instance runs more than `PHP_PM_MAX_CHILDREN`
  + ws + cron + a few CLI jobs against the database at once — and raise the
  cap with it.
- **MySQL:** `performance_schema` off (`MYSQL_PERFORMANCE_SCHEMA=ON` restores
  it, at ~250 MB of cap), binary logging disabled, `temptable_max_ram` 1G →
  64M.
- **MariaDB:** `tmp_table_size`/`max_heap_table_size` 16M → 4M per session.
- **PostgreSQL:** parallel query off, `/dev/shm` 64m → 128m,
  `effective_cache_size` 4GB → 256MB.
- **Every service:** `memswap_limit` now equals `mem_limit` — a host with
  swap can no longer extend a container past its cap.

New optional knobs, all empty/unchanged by default: `PUBLISH_IP`,
`CGROUP_PARENT`, `PHP_PM_MODE`. An instance `docker.env` that doesn't set
a key gets the compose default above. **One existing value needs
attention:** an instance `docker.env` copied from the previous template
still pins `MARIADB_MEM_LIMIT=512m` / `POSTGRES_MEM_LIMIT=512m`, and 512m
with the new `max_connections` of 30 was OOM-killed under 30 concurrent
heavy queries. Raise those to 640m, or lower `*_MAX_CONNECTIONS` with them.
Sizing guidance: `docs/development/docker-tuning.md`.

### The MariaDB healthcheck stops logging "Access denied" on every run

Reaches a project only when it reconciles `docker/docker-compose.yml` from
the sample. The `mariadb` healthcheck is now the image's `healthcheck.sh`,
plus `MARIADB_AUTO_UPGRADE=1` so an existing volume gets the image's
healthcheck user on its next start. The same flag runs `mariadb-upgrade` on
start after an image version bump, backing up the system tables first.

### One `HEALTHCHECK_INTERVAL` for every service replaces `PHP_HEALTHCHECK_INTERVAL`

Reaches a project only when it reconciles `docker/docker-compose.yml`,
`docker/php/fpm.d/www.conf.template`, and `docker/env/sample.docker.env`
from the sample. Every healthcheck now runs every `HEALTHCHECK_INTERVAL`
(default 30s, previously 10s for Valkey and the databases) with 3 retries
(databases previously 10), and FPM no longer writes an access-log line for
the healthcheck's `/ping`. `PHP_HEALTHCHECK_INTERVAL` is no longer read: an
instance `docker.env` that still sets it falls back to the 30s default —
rename it to `HEALTHCHECK_INTERVAL` if you want a different value. Startup
is unaffected: Docker probes every 5s during each service's start period
whatever the interval.

### PHP-FPM access-log lines now carry the path, client IP, and request cost

Reaches a project only when it reconciles `docker/php/fpm.d/www.conf.template`
(rebuild to apply). A line previously read `172.20.0.6 - <time> "GET " 200` —
the nginx container's IP and an empty path, because nginx rewrites every URL
to `/index.php?/<path>` and FPM's own path field ends up empty. It now reads:

```
172.20.0.1 -  23/Sep/2026:21:56:31 +0000 "GET /smoke/whoami?x=1" 403 8.415ms 2048KB 0.00%
```

client IP (as nginx passes it), original URL with query string (the same one
nginx's own access log already records), duration, peak memory (in 2 MB
steps), and CPU. Anything parsing FPM's container log by field position
needs updating.
