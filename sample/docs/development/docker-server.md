# Docker stack — running on a server

> Scope: operating the stack on a server — readiness, and the bundled
> PostgreSQL and MariaDB as production tiers: data protection, secrets,
> backups, restore, upgrades. For running the stack in general, see
> `docker.md`; for sizing memory and CPU, `docker-tuning.md`; for editing the
> files under `docker/`, `docker-internals.md` (all beside this file).

## Server readiness

- **Publish only what the proxy needs.** A proxy that runs as a container
  reaches nginx directly over a Docker network of its own, added to nginx's
  `networks:`. Never attach it to `appnet` or `frontnet`: both reach FPM on
  `php:9000`, and FPM applies PHP settings from any FastCGI client. Then
  delete `HTTP_PORT` and `WS_PORT` from `<instance>.docker.env` and nothing
  is published. With a
  reverse proxy on the host itself, keep them and set
  `PUBLISH_IP=127.0.0.1`: Docker's published ports bypass the host
  firewall's INPUT rules, so an `HTTP_PORT` published on all interfaces is
  reachable from anywhere the cloud firewall allows.
- **A copy of `docker-compose.yml` run by other tooling needs no edits for
  ports or tooling.** The base file publishes nothing and has no `tools`
  service; both live in addon files that only `docker_manage.sh` adds. Pass
  `-f docker-compose.ports.yml` as well where the copy should publish.
- **Set `NGINX_TRUSTED_PROXIES`** in `<instance>.docker.env` to every proxy
  between the client and nginx (addresses or CIDRs, space- or
  comma-separated), then `up -d`; no rebuild. Empty, the default,
  trusts no proxy: no client can forge its IP, but behind a proxy every
  client is logged, locked out and rate-limited as the proxy's address. The
  `[nginx-runtime]` line in `nginx-init`'s log shows what was applied. A proxy on
  the host reaches nginx through the published port, usually from the Docker
  network's gateway; the first field of nginx's access log shows the peer.
  - With the default header, `X-Forwarded-For`, list every hop: nginx reads
    the header right to left and takes the first address not on the list,
    so a value the client sent itself is skipped. Never list a whole private
    range on a shared host — any container in it can then set the header and
    pick the client IP the app logs and rate-checks against.
  - A single-value header (`NGINX_REAL_IP_HEADER`, such as a CDN's
    connecting-IP header) is taken verbatim from any listed peer. Unless the
    edge accepts connections only from the CDN's published ranges, or
    overwrites any copy of the header the client sent, anyone who reaches
    the edge directly picks their own IP.
  - The scheme is trusted from the same list: only a listed peer's
    `X-Forwarded-Proto: https` tells the app the request was HTTPS. Behind
    a TLS-terminating proxy with the list empty, `is_https()` is always
    false.
- **FPM's `/status` and `/ping` are not reachable through nginx.** The
  healthchecks don't use them. A monitor connects over FastCGI to
  `php:9000` (status path `/status`, ping `/ping`) from a container on
  `appnet`. Only if it must use HTTP, add a location to
  `docker/nginx/conf.d/app.conf` that allows loopback or the monitor's exact
  address. `allow` matches the address after real-IP substitution, so never
  allow a range that contains a proxy:

  ```nginx
  location ~ ^/(status|ping)$ {
      allow 127.0.0.1;
      deny  all;

      include fastcgi_params;
      fastcgi_pass  $php_upstream;
      fastcgi_param SCRIPT_FILENAME $fastcgi_script_name;
  }
  ```
- **Image builds run outside every cap.** BuildKit is not in any
  instance's cgroup; compiling PHP extensions on a small box while the sites
  are live can exhaust the host. Build elsewhere and pull, or build while
  the sites are idle.
- **Disk grows in three places.** Database volumes (data, temp-table
  spills, and `api_log`, which grows with every REST call until
  `log_prune`'s `api` stage is set); container logs (capped by the `json-file` driver at 10m × 3 per
  container); and the app's own logs under `/var/log/manager`, bounded
  only by `manager/tools/log_prune` — the sample crontab runs it nightly,
  and its deleting stages stay off until the env sets them (`docker.md`,
  "Retention — `manager/tools/log_prune`").
- **Back up the database.** The bundled PostgreSQL and MariaDB have a backup
  script and a server procedure (below). The bundled `mysql` profile is
  dev/local only and has neither; use an external database, or dump it by
  hand if a site's data matters.

- **Host kernel settings** for Valkey (`vm.overcommit_memory = 1`,
  transparent huge pages off) are in `docker.md`, "Resource limits &
  tuning".
- **Set `kernel.yama.ptrace_scope` to 1 or higher on the host.** nginx's
  master and workers share one uid, and at 0 a compromised worker can attach
  to the master and change what nginx serves without touching a file. At 1
  a process can trace only its own descendants; root keeps its access.
  nginx logs a `[ptrace-scope] WARNING` at start while it is 0 or Yama is
  absent.

## Bundled databases: secrets and backups

The sections in this part apply to every bundled production database,
PostgreSQL and MariaDB: the secrets and env files, the backup script, the
off-host copy and the limits of the backups. Each engine's own part, further
down, covers what differs: starting the service, protecting the data,
first-initialization failures, restore and upgrades. A requirement is
something the stack depends on; anything else is a recommendation. Paths are
relative to the directory holding the compose file (`docker/` in a project
checkout).

### Secrets and env files

Compose file secrets and the `.env.priv` mount are plain bind mounts. The
host file's owner and mode reach the container unchanged, so each file must
be readable by the process that reads it. Write them under `umask 077`
with `printf`/`openssl` (no trailing newline), then:

| File | Read by | Set |
|---|---|---|
| `secrets/<instance>.db_password` | PostgreSQL: `postgres/entrypoint.sh`, as root, on a fresh cluster only. MariaDB: the image's entrypoint on a fresh data directory, and the healthcheck on every run, both as root | `root:root 0600` |
| `secrets/<instance>.db_root_password` | the image's entrypoint, still root. MariaDB: also `bin/db-backup.sh`, through `docker exec` as root | `root:root 0600` |
| `secrets/<instance>.valkey_password` | the Valkey entrypoint, still root | `root:root 0600` |
| `env/<instance>.priv.env` | php/ws/cron/cli as `APP_USER:APP_GROUP` | `root:<APP_GID> 0640` |
| `MEDIA_PATH`, `PRIVATE_PATH` | php/cron/cli as `APP_USER`; nginx (uid 101, group `APP_GROUP`) reads public media | `<APP_UID>:<APP_GID>`, `0755` / `0750` (media may be `0750` too) |
| non-secret env files | compose, as the user running it | that user, `0640` |

- **Run the app as a uid:gid of its own** (`APP_USER`/`APP_GROUP`), not the
  deploying user's. Unset, it runs as `www-data`, and the modes above must
  match that instead.

### Backups — `bin/db-backup.sh`

The script finds every running container labelled `mgr.backup=<engine>` on
the host, so **one** scheduled run covers every site. For each one it:

- writes a dump to `backups/<engine>/` beside that site's compose file, as
  `<db>-<tag>-<UTC YYYYMMDDTHHMMSSZ>.<ext>.zst`, compressed with host `zstd`:
  for PostgreSQL `<db>-pg<major>-….dump.zst` (`pg_dump -Fc`), for MariaDB
  `<db>-mariadb-….sql.zst` (see "MariaDB backups");
- writes to a `.partial` file and renames it only on success, so a failed
  dump never counts toward retention;
- keeps the newest `DB_BACKUP_KEEP` good dumps per database and tag, never
  pruning the newest;
- optionally uploads the new dump with `aws s3 cp`.

It logs one line per site (`status=ok`, `uploaded` or `failed`, with a
reason) and exits non-zero if any site failed, after finishing the others.
`backups/` and `backups/<engine>/` must be the running user's own `0700`
directories; the script creates them that way and refuses a site whose
directories are anything else.

| Variable | Default | Meaning |
|---|---|---|
| `DB_BACKUP_KEEP` | `7` | Good dumps kept per database and tag, 1 to 999999 |
| `DB_BACKUP_S3_URI` | empty | Upload each new dump to `<uri>/<site>/<engine>/<file>` |
| `DB_BACKUP_ALLOWED_ROOT` | empty (no check) | Back up only sites whose compose directory resolves under this path; others fail |

Requirements for scheduling it:

- **Root runs only a copy that no site user can edit.** Install one copy
  outside every project directory, writable only by root, and re-copy it
  when the framework's `bin/db-backup.sh` changes. Whoever can edit the
  file root runs gets root.
- **Runs must not overlap.** A slow dump can outlast the interval.
- **Its `PATH` must find `docker`, `zstd`**, and `aws` if uploading.
  Scheduler environments are often minimal.
- **Something must notice a failure:** a non-zero exit, a `status=failed`
  line, or a site whose newest dump is older than one interval.

Whoever can start a labelled container chooses a directory root writes
into. On a host that runs stacks you don't control, set
`DB_BACKUP_ALLOWED_ROOT` to the directory holding your sites.

An example system crontab entry (nightly, overlap-guarded; adjust the
paths):

```
PATH=/usr/local/bin:/usr/bin:/bin
DB_BACKUP_KEEP=7
0 3 * * * root flock -n /run/db-backup.lock /path/to/db-backup.sh >> /path/to/db-backup.log 2>&1
```

### Off-host copy

A dump on the same disk doesn't survive losing the host. Recommendations
for the copy (`DB_BACKUP_S3_URI` is one way; the dumps are plain files any
uploader can take):

- The credential that uploads can only write: no read, list or delete. A
  compromised host then can't erase or read back its own history.
- Restore with a separate credential, never the host's own.
- Keep previous versions, and expire old ones by policy rather than by the
  host.
- Alert on a failed run **and** on a site with no new dump within its
  interval. A backup that silently stops is found only at restore time.

### Known limits (all bundled databases)

- **Only running containers are backed up.** A stopped site's database is
  skipped without a log line; the missing-dump alert catches it.
- **`.partial` leftovers.** A run killed mid-dump (OOM, reboot) leaves
  `<file>.partial` in `backups/<engine>/`. Retention ignores it; delete
  stale ones by hand.
- **`backups/` is never repaired.** A site whose `backups/` is a symlink,
  has a looser mode or another owner fails with its path logged until
  someone fixes it.
- **One database name per compose directory.** Stacks run from the same
  directory share `backups/<engine>/`, so two of them with the same
  database name share one retention pool.

## Bundled PostgreSQL as a production tier

The bundled `postgres` service is a supported tier for small sites: one
database container per site, inside the site's own stack. At scale, a
managed database remains the recommendation. The stack ships what the
database needs from it: a non-superuser app role, data that survives
`down -v`, and `bin/db-backup.sh`. Scheduling, off-host storage and
alerting belong to whoever runs the server.

There is no point-in-time recovery: the worst-case loss is everything
written since the last good dump.

A new cluster initializes with an ICU `und` default locale
(`POSTGRES_INITDB_ARGS` in the compose file). It applies to an empty data
directory only; a cluster created before keeps its default, and moving it is
a dump and restore into a fresh one.

Paths below are relative to the directory holding the compose file
(`docker/` in a project checkout). `<container>` is the `postgres`
container's name.

### Starting the service on a server

- **Through `docker_manage.sh`:** add `--profile postgres`. Every instance
  of one checkout resolves relative paths against the same `docker/`, so
  the protection paths below must include the instance name. Two instances
  sharing one path would start two servers on one data directory.
- **From a copy of the compose file run by other tooling** (one `-f`, no
  profile flags): delete `profiles: [postgres]` from the `postgres` service
  in the copy, so the database starts whatever flags the tooling passes.
  `COMPOSE_PROFILES` in `.env` works only until something runs compose with
  a `--profile` flag, which replaces it instead of adding to it. An override
  file is no way around it either, because an explicit `-f` turns off
  `docker-compose.override.yml`. Copy `postgres/` (the wrapper and
  `initdb/`) beside the compose file: the mounts are relative.

Either way, if the site already holds data, dump it before changing the
`postgres` service.

### Protect the data from `down -v`

Unset, `POSTGRES_DATA` and `VALKEY_STATE_DATA` are named volumes, and
`down -v` deletes them. On a server, point both at bind paths under one
`data/` directory: `./data/<instance>/postgres` and
`./data/<instance>/valkey-state` in `<instance>.docker.env`, or
`./data/postgres` and `./data/valkey-state` in a compose copy's own `.env`.

- **Create `data/` itself as `root:root 0700` before the first `up`, and
  nothing below it.** Docker creates each bind path at `root:root 0755`,
  and Postgres needs that. Its entrypoint finishes initialization as the
  image's `postgres` user, which has to pass through the mount root to
  reach its data directory two levels down. Mode `0700` or `0750` on
  `data/postgres` stops Postgres from starting. Locking the parent keeps
  every non-root host user out of the whole tree.
- **A wrong path gives an empty database.** If `POSTGRES_DATA` points
  somewhere that doesn't exist yet (renamed, mistyped, a new instance
  name), Docker creates an empty directory there and Postgres initializes
  a fresh cluster with the app role. The site comes up healthy and empty.
  Check the path before the first `up` after any change.
- **Switching an existing site's `valkey-state` to a bind path starts it
  empty.** The named volume isn't copied, so every user is logged out once.
  Copy the volume's contents into the new path first if that matters.

### PostgreSQL secrets and app settings

- **Never override the `postgres` service's `entrypoint:`.** The wrapper
  reads `db_password` as root, passes it to the init without exposing it
  to the running server, and guards against a half-initialized cluster.
- **Point the app at the bundled database** in `<instance>.env`:
  `DB_HOST=postgres`, `DB_PORT=5432` and `DB_DRIVER=pdo/pgsql` in its
  bundled-database block (the block overrides the base), with
  `DB_CHAR_SET=UTF8`, `DB_COLLATION` empty and
  `DB_LOCALE_CI=und-u-ks-level2` (`database.md`; without it the first
  `migrate` stops). Generate
  `CF_ENCRYPTION_KEY` (`docker.md`, "Your own instance").

### If the first initialization fails

- **Missing or empty `db_password`:** the wrapper refuses before writing
  anything (`[postgres-entrypoint] FATAL: /run/secrets/db_password is
  missing or empty; not initializing`). Fix the file and start again.
- **Any failure once initialization has started** (a hook error, an OOM
  kill, `DB_USER=postgres` colliding with the superuser, an error from the
  image's own pre-init checks): the first run logs the real cause. Every
  later start refuses with `[postgres-entrypoint] FATAL: the init started
  <UTC time> never finished …`, so the container restart-loops and never
  turns healthy. During initialization the server listens on no TCP port,
  so no client can have written to that cluster.
- **Recovery moves the data directory aside, never deletes it.** After
  fixing the cause:

  ```bash
  docker stop <container>
  sudo mv ./data/<instance>/postgres ./data/<instance>/postgres-failed-$(date -u +%Y%m%dT%H%M%SZ)
  # then start the stack again the way you normally do
  ```

  Docker recreates the path empty and the full initialization runs again.
  Delete the moved directory only after checking it.
- **An existing cluster is never refused** and never reads `db_password`,
  so a missing secret file can't stop a live site from starting.

### Restore drill

Restore each new site's dump once, into a scratch database, before
trusting it. The dumps are root-only, hence `sudo`:

```bash
sudo ls backups/postgres                                          # pick <file>
docker exec -u postgres <container> createdb -U postgres restore_drill
sudo zstd -dc backups/postgres/<file> | \
    docker exec -i -u postgres <container> pg_restore -U postgres -d restore_drill
docker exec -u postgres <container> psql -U postgres -d restore_drill -c '\dt'   # same tables, owned by <DB_USER>
docker exec -u postgres <container> dropdb -U postgres restore_drill
```

A real recovery is the same pipe with `--clean --if-exists -d <db_name>`.
It **replaces** the live database; never run it to test.

### Version bumps and restarts

- **Minor bumps are safe:** the new image starts on the existing data.
- **A major bump (`postgres:18.x` → `19.x`) refuses to start** on the old
  data directory: the image's own upgrade check fails it with a
  `pg_upgrade` message. Under `restart: unless-stopped` that's a restart
  loop. The data is untouched, and putting the old pin back starts it
  again. To upgrade: dump, stop `postgres`, move its data directory aside,
  bump the pin, start it on the empty path, then restore. That check works
  only with the image's default `PGDATA`, so never set `PGDATA`.
- **A deploy that recreates the whole stack** (`up -d --force-recreate`)
  restarts `postgres` and `valkey-state` with it. The data is safe on its
  bind path, but expect a short outage and dropped `ws`/`cron` connections.
  A deploy that runs `docker compose pull` first needs the images in a
  registry: `pull` exits non-zero for a service whose image was only built
  or loaded locally.

### PostgreSQL known limits

- **"Healthy" means initialized and accepting the app's login.** The
  healthcheck needs no init marker and a real connection as `DB_USER`, so
  it also fails while `max_connections` is used up. Nothing in the stack
  acts on unhealthy; a host tool that restarts unhealthy containers would
  bounce the database under load.
- **The wrapper is the guard.** With the `postgres` entrypoint overridden or
  bypassed, a failed init hook can leave a cluster that reports healthy.
- **A major bump starts a new retention pool.** Retention counts per database
  and tag, so after `pg18` → `pg19` the `pg18` dumps are never pruned again.
  Delete them by hand once they are no longer needed.

## Bundled MariaDB as a production tier

The bundled `mariadb` service is a supported tier on the same terms as
PostgreSQL's: one database container per site, inside the site's own stack,
and a managed database remains the recommendation at scale. The stack ships
what the database needs from it: an app user with privileges on `DB_NAME`
only, a `root` that logs in only from inside the container, data that
survives `down -v`, and `bin/db-backup.sh`. Scheduling, off-host storage and
alerting belong to whoever runs the server, as under "Backups" and "Off-host
copy" above, which apply to MariaDB unchanged.

There is no point-in-time recovery: the binary log is off, so the worst-case
loss is everything written since the last good dump.

Paths below are relative to the directory holding the compose file
(`docker/` in a project checkout). `<container>` is the `mariadb`
container's name.

### Starting the MariaDB service on a server

- **Through `docker_manage.sh`:** add `--profile mariadb`. Every instance of
  one checkout resolves relative paths against the same `docker/`, so the
  protection path below must include the instance name. Two instances sharing
  one path would start two servers on one data directory.
- **From a copy of the compose file run by other tooling** (one `-f`, no
  profile flags): delete `profiles: [mariadb]` from the `mariadb` service in
  the copy, so the database starts whatever flags the tooling passes.
  `COMPOSE_PROFILES` and an override file fail for the same reasons as for
  `postgres`. There is no directory to copy beside the compose file: the
  service mounts no script or hook.

If the site already holds data, dump it before changing the `mariadb`
service.

### Protect the MariaDB data from `down -v`

Unset, `MARIADB_DATA` is a named volume, and `down -v` deletes it. On a
server, point it at a bind path under the same `data/` directory as the
others: `./data/<instance>/mariadb` in `<instance>.docker.env`, or
`./data/mariadb` in a compose copy's own `.env`.

- **Create `data/` itself as `root:root 0700` before the first `up`, and
  nothing below it**, as for PostgreSQL. The locked parent keeps every
  non-root host user out of the tree. Unlike PostgreSQL, the mode of the bind
  path itself is not yours to set: the entrypoint starts as root and re-owns
  the mount root to the `mysql` user on every start (`999:999`, `0755`), so a
  mode set there is overwritten.
- **A wrong path gives an empty database.** If `MARIADB_DATA` points
  somewhere that doesn't exist yet (renamed, mistyped, a new instance name),
  Docker creates an empty directory and the entrypoint initializes a fresh
  data directory with `DB_NAME` and `DB_USER`. The site comes up healthy and
  empty. Check the path before the first `up` after any change.
- **Moving an existing site from the named volume to a bind path starts it
  empty.** The volume isn't copied. Dump first and restore into the new
  data directory.

### A data directory created before `MARIADB_ROOT_HOST`

A new data directory gets `root@localhost` only. One created by an earlier
framework version also has `root@'%'`, which any container on `appnet` can
use with the root password. Only the database containers mount that secret;
a service that learns it by other means can log in. Drop the account once,
over the socket:

```bash
docker exec -i <container> sh -c 'MYSQL_PWD="$(cat /run/secrets/db_root_password)" exec mariadb -uroot' <<< "DROP USER 'root'@'%';"
```

The change lives in the data directory and survives restarts. Logins through
`docker exec`, `bin/db-backup.sh` and the app user are unaffected. A local
instance with disposable data can `down -v` instead.

### MariaDB secrets and app settings

The two secret files have the readers in the table above (the first two
rows), and the same modes. The server process runs as `mysql` and can read
neither: the entrypoint reads both as root before it drops privileges.

- **Point the app at the bundled MariaDB** in `<instance>.env`, in its
  bundled-database block (the block overrides the base): `DB_HOST=mariadb`,
  `DB_PORT=3306`, `DB_DRIVER=pdo/mysql`, `DB_CHAR_SET=utf8mb4`,
  `DB_COLLATION=utf8mb4_uca1400_nopad_as_cs`,
  `DB_COLLATION_CI=utf8mb4_uca1400_nopad_as_ci` and `DB_LOCALE_CI` empty
  (`database.md`). Leaving PostgreSQL's `DB_CHAR_SET=UTF8` in the block
  connects silently as `utf8mb3` and refuses 4-byte characters. Generate
  `CF_ENCRYPTION_KEY` (`docker.md`, "Your own instance").
- **`DB_NAME` and `DB_USER` must be set in the files compose reads.** The
  entrypoint creates the database, the user and the user's grant from them on
  a fresh data directory. Empty, the container still starts, reports
  `unhealthy`, and says why (below).

### If the first MariaDB initialization fails

MariaDB has no wrapper and no init marker: a first initialization that goes
wrong still starts. The healthcheck reports it. It logs in as `DB_USER` to
`DB_NAME` over TCP, so after the 30 s start period and three failed checks
the container is `unhealthy`, not restarting, and its last check output says
why:

```bash
docker inspect <container> --format '{{range .State.Health.Log}}{{.ExitCode}} {{.Output}}{{end}}'
```

| Output | Cause |
|---|---|
| `DB_USER or DB_NAME is empty` | the variable is empty in the files compose reads; the data directory was initialized without the database, the user, or the user's grant |
| `ERROR 1045 … (using password: NO)` | `db_password` was empty at the first initialization; the app user was never created |
| `ERROR 1045 … (using password: YES)` | the user is missing, or `db_password` no longer matches the password stored for it |
| `ERROR 1044 … to database '<DB_NAME>'` | the app user has no grant on `DB_NAME` |
| `ERROR 1049 … Unknown database` | `DB_NAME` doesn't exist in this data directory |
| `ERROR 2002 … Can't connect` | the server isn't accepting TCP yet: still starting, or in its socket-only initialization phase |
| `ERROR 1040 … Too many connections` | not an initialization failure; see "MariaDB known limits" |

- **A first initialization that failed, with no data worth keeping in that
  data directory:** after fixing the cause, stop the container, move the
  `MARIADB_DATA` directory aside (never delete it), and start again on the
  empty path.
  The full initialization runs again.

  ```bash
  docker stop <container>
  sudo mv <MARIADB_DATA> <MARIADB_DATA>-failed-$(date -u +%Y%m%dT%H%M%SZ)
  # then start the stack again the way you normally do
  ```

  Delete the moved directory only after checking it. During initialization
  the server listens on no TCP port, so no client can have written to that
  data directory.
- **A kill in the middle of the first initialization** (an OOM kill, a
  reboot) shows as one start that exits 1 (`Unknown database`), then a
  container that stays `unhealthy`. In that data directory `root` has an
  empty password, reachable only from inside the container. Recover the same
  way; don't keep it.
- **An `unhealthy` container that holds data is not a failed initialization.**
  Don't move the data aside. Read the reason and fix it as `root` over the
  socket: the grant, the database, or the user's password.

### MariaDB backups

`bin/db-backup.sh` backs MariaDB up under the same label (`mgr.backup=mariadb`,
set on the service), layout, scheduling requirements and off-host advice as
every bundled database ("Backups" above). What differs:

- **The file** is `backups/mariadb/<db>-mariadb-<UTC YYYYMMDDTHHMMSSZ>.sql.zst`.
  `mariadb` is a constant: the name carries no version, so a series bump
  continues the same retention pool. The exact server version is in the
  dump's own header (`-- Server version`).
- **The command** is `mariadb-dump --single-transaction --routines --triggers
  --events` as `root` over the socket, with the root password read inside the
  container, never on a command line. `--routines` and `--events` are
  needed: without them, stored routines and events are not in the dump. The
  event scheduler is off by default, so restored events don't run until it
  is enabled.
- **Consistency covers InnoDB only.** `--single-transaction` snapshots
  InnoDB tables. Aria and MyISAM tables are dumped under table locks one after
  another, with no warning, so a project that keeps data in them gets a dump
  that is not consistent across tables. The framework's migrations create
  InnoDB tables.
- **A good dump ends with `-- Dump completed on <timestamp>`.** A failed dump
  (an unknown database, a stopped or paused container, the database killed
  mid-dump) leaves no final-named file and no `.partial` after the run, and
  the run exits non-zero.
- **The dump has no `CREATE DATABASE` or `USE`:** the restore names the
  database.

### MariaDB restore drill

Restore each new site's dump once, into a scratch database, before trusting
it. The dumps are root-only, hence `sudo`:

```bash
sudo ls backups/mariadb                                          # pick <file>
sudo zstd -dc backups/mariadb/<file> | sed -n '1,6p'             # "-- Server version" is the server that wrote it
sudo zstd -dc backups/mariadb/<file> | tail -n 2                 # must end "-- Dump completed on …"
docker exec <container> sh -c 'MYSQL_PWD="$(cat /run/secrets/db_root_password)" exec mariadb -uroot -e "create database restore_drill"'
sudo zstd -dc backups/mariadb/<file> | \
    docker exec -i <container> sh -c 'MYSQL_PWD="$(cat /run/secrets/db_root_password)" exec mariadb -uroot restore_drill'
docker exec <container> sh -c 'MYSQL_PWD="$(cat /run/secrets/db_root_password)" exec mariadb -uroot -e "show tables" restore_drill'   # same tables
```

- **Views and stored routines run as their definer**, the app user, which has
  no rights on the scratch database. As `root` there, querying a view fails
  (`ERROR 1356`) and calling a routine fails (`ERROR 1370`) although the
  restore succeeded. To check them, grant the definer on the scratch
  database first, and revoke it after dropping the database (the grant
  outlives the database):

  ```sql
  grant select, execute on restore_drill.* to '<DB_USER>'@'%';
  -- query a view, call a routine
  drop database restore_drill;
  revoke select, execute on restore_drill.* from '<DB_USER>'@'%';
  ```

- **Comparing tables:** `CHECKSUM TABLE … EXTENDED` on the source and the
  scratch copy should match. Tables with `FLOAT` or `DOUBLE` columns can show
  different checksums although every row is identical. The framework's own
  `api_log` is one (its `rtime` column is a `FLOAT`), so expect it to differ
  on every site; compare a row dump of it instead
  (`mariadb-dump --skip-extended-insert --hex-blob --no-create-info <db> api_log`).
- **The header's `Server version`** is the server that wrote the dump. A
  dump restored cleanly into the same series and into the neighbouring one,
  in both directions (11.8 and 12.3); further apart is untested.

A real recovery **replaces** the live database: stop the app, run `drop
database <DB_NAME>; create database <DB_NAME>;`, then the same pipe into
`<DB_NAME>`. The app user's grant is keyed by name and survives the drop.
Never run it to test. If the restore stops partway, the database holds only
the tables restored so far: fix the cause and start again from the drop.

### MariaDB series bumps and restarts

MariaDB's upgrade unit is the **series** (`x.y`: 11.4, 11.8, 12.3), not the
leading number: 11.4 → 11.8 upgrades the data directory just as 11.8 → 12.3
does.

- **A patch bump (`12.3.2` → `12.3.3`) needs nothing.** The new image starts
  on the existing data, logs `MariaDB upgrade not required` and changes
  nothing on disk.
- **A series bump upgrades the data directory on its first start.**
  `MARIADB_AUTO_UPGRADE=1` (set in the compose file) backs up the `mysql`
  system schema to `system_mysql_backup_<old version>.sql.zst` in the data
  directory, then runs `mariadb-upgrade`. The time depends on your data and
  host; measure it on your own server.
- **Putting the old pin back is not a rollback. Never do it.** MariaDB starts
  the older image on the upgraded data directory without a warning and
  accepts writes; that combination is unsupported.
- **Before a series bump:** take a dump, and check that the newest file ends
  with `-- Dump completed` and that its header's `Server version` names the
  old series.
- **Rollback is a restore.** Stop the service; move the data directory aside
  (never delete it); put the old pin back; start on the empty path, where the
  entrypoint creates `DB_NAME` and `DB_USER` again; restore the pre-bump dump
  into a scratch database first, then into `DB_NAME` as for any replace-live
  restore. Everything written after that dump is lost.
- **A deploy that recreates the whole stack** (`up -d --force-recreate`)
  restarts `mariadb` with it. The data is safe on its bind path; expect a
  short outage and dropped `ws`/`cron` connections. Durations depend on your
  data and host: measure the stop and the start on your own server against
  Docker's 10 s default stop grace.

### MariaDB known limits

- **"Healthy" means initialized and accepting the app's login.** The check
  needs a real connection as `DB_USER`, so it also fails while
  `max_connections` is used up (`ERROR 1040`; the container goes `unhealthy`
  and nothing restarts). Nothing in the stack acts on unhealthy; a host tool
  that restarts unhealthy containers would bounce the database under load.
- **`unhealthy` flags a failed initialization; it doesn't stop one.** There
  is no wrapper: a data directory initialized with an empty `DB_NAME`,
  `DB_USER` or `db_password` still serves whatever it has. Check the
  container's health after the first `up`.
- **Consistency is InnoDB-only** ("MariaDB backups").
- **The image leaves its own system-schema backup in the data directory**
  after a series bump (`system_mysql_backup_*`, holding the account hashes).
  It is owned by the `mysql` user (uid 999 on the host) and readable by
  anyone who can reach the directory: only the locked `data/` parent keeps
  host users out of it, so a data path that skips that requirement exposes
  it too. Delete it once the bump is accepted.
- **Startup noise.** A first start logs three `io_uring_queue_init() failed
  … falling back to libaio` warnings and one `memory.pressure not writable`
  warning on a real host. Both are harmless.
- **The shared limits above (running containers only, `.partial`
  leftovers, `backups/` repair, one database name per compose directory)
  apply unchanged.**
