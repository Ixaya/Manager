# Docker stack — running on a server

> Scope: operating the stack on a server — readiness, and the bundled
> PostgreSQL as a production tier: data protection, secrets, backups,
> restore, upgrades. For running the stack in general, see `docker.md`; for
> sizing memory and CPU, `docker-tuning.md`; for editing the files under
> `docker/`, `docker-internals.md` (all beside this file).

## Server readiness

- **Publish only what the proxy needs.** A proxy that runs as a container
  on a shared Docker network reaches nginx directly: delete `HTTP_PORT` and
  `WS_PORT` from `<instance>.docker.env` and nothing is published. With a
  reverse proxy on the host itself, keep them and set
  `PUBLISH_IP=127.0.0.1`: Docker's published ports bypass the host
  firewall's INPUT rules, so an `HTTP_PORT` published on all interfaces is
  reachable from anywhere the cloud firewall allows.
- **A copy of `docker-compose.yml` run by other tooling needs no edits for
  ports or tooling.** The base file publishes nothing and has no `tools`
  service; both live in addon files that only `docker_manage.sh` adds. Pass
  `-f docker-compose.ports.yml` as well where the copy should publish.
- **Narrow `set_real_ip_from`** in `docker/nginx/nginx.conf` to the proxy's
  address. The shipped config trusts every private range, so on a shared
  host any other container can set `X-Forwarded-For` and pick the client IP
  the app logs and rate-checks against.
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
- **Back up the database.** The bundled PostgreSQL has a backup script and
  a server procedure (below). The other bundled profiles have none of their
  own; dump by hand if a site's data matters:

  ```bash
  ./docker_manage.sh -e <instance> exec -T mariadb sh -c 'MYSQL_PWD="$(cat /run/secrets/db_root_password)" mariadb-dump -uroot --single-transaction --routines --triggers --events <DB_NAME>' > <instance>.sql
  ```

- **Host kernel settings** for Valkey (`vm.overcommit_memory = 1`,
  transparent huge pages off) are in `docker.md`, "Resource limits &
  tuning".

## Bundled PostgreSQL as a production tier

The bundled `postgres` service is a supported tier for small sites: one
database container per site, inside the site's own stack. At scale, a
managed database remains the recommendation. The stack ships what the
database needs from it: a non-superuser app role, data that survives
`down -v`, and `bin/db-backup.sh`. Scheduling, off-host storage and
alerting belong to whoever runs the server. Below, a requirement is
something the stack depends on; anything else is a recommendation.

There is no point-in-time recovery: the worst-case loss is everything
written since the last good dump.

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

### Secrets and env files

Compose file secrets and the `.env.priv` mount are plain bind mounts. The
host file's owner and mode reach the container unchanged, so each file must
be readable by the process that reads it. Write them under `umask 077`
with `printf`/`openssl` (no trailing newline), then:

| File | Read by | Set |
|---|---|---|
| `secrets/<instance>.db_password` | `postgres/entrypoint.sh`, as root, on a fresh cluster only | `root:root 0600` |
| `secrets/<instance>.db_root_password` | the image's entrypoint, still root | `root:root 0600` |
| `secrets/<instance>.valkey_password` | the Valkey entrypoint, still root | `root:root 0600` |
| `env/<instance>.priv.env` | php/ws/cron/cli as `APP_USER:APP_GROUP` | `root:<APP_GID> 0640` |
| `MEDIA_PATH`, `PRIVATE_PATH` | php as `APP_USER`; nginx (uid 101) reads public media | `<APP_UID>:<APP_GID>`, `0755` / `0750` |
| non-secret env files | compose, as the user running it | that user, `0640` |

- **Run the app as a uid:gid of its own** (`APP_USER`/`APP_GROUP`), not the
  deploying user's. Unset, it runs as `www-data`, and the modes above must
  match that instead.
- **Never override the `postgres` service's `entrypoint:`.** The wrapper
  reads `db_password` as root, passes it to the init without exposing it
  to the running server, and guards against a half-initialized cluster.
- **Point the app at the bundled database** in `<instance>.env`:
  `DB_HOST=postgres`, `DB_PORT=5432` and `DB_DRIVER=pdo/pgsql` in its
  bundled-database block (the block overrides the base), with
  `DB_CHAR_SET=UTF8` and `DB_COLLATION` empty (`database.md`). Generate
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

### Backups — `bin/db-backup.sh`

The script finds every running container labelled `mgr.backup=<engine>` on
the host, so **one** scheduled run covers every site. For each one it:

- writes a dump to `backups/<engine>/` beside that site's compose file, as
  `<db>-pg<major>-<UTC YYYYMMDDTHHMMSSZ>.dump.zst` (`pg_dump -Fc`, compressed
  with host `zstd`);
- writes to a `.partial` file and renames it only on success, so a failed
  dump never counts toward retention;
- keeps the newest `DB_BACKUP_KEEP` good dumps, never pruning the newest;
- optionally uploads the new dump with `aws s3 cp`.

It logs one line per site (`status=ok`, `uploaded` or `failed`, with a
reason) and exits non-zero if any site failed, after finishing the others.
`backups/` and `backups/<engine>/` must be the running user's own `0700`
directories; the script creates them that way and refuses a site whose
directories are anything else.

| Variable | Default | Meaning |
|---|---|---|
| `DB_BACKUP_KEEP` | `7` | Good dumps kept per database, 1 to 999999 |
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

### Known limits

- **"Healthy" means initialized and accepting the app's login.** The
  healthcheck needs no init marker and a real connection as `DB_USER`, so
  it also fails while `max_connections` is used up. Nothing in the stack
  acts on unhealthy; a host tool that restarts unhealthy containers would
  bounce the database under load.
- **The wrapper is the guard.** With the `postgres` entrypoint overridden or
  bypassed, a failed init hook can leave a cluster that reports healthy.
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
