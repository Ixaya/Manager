# Upgrading — unreleased

Changes between 2.x releases that alter behavior a project already depends on.
Everything else in a minor release is additive.

### New: `log_prune` prunes the `api_log` table — it grew without bound before this

Additive. The `api_log` table (one row per REST call, request parameters
included) previously grew without bound; `manager/tools/log_prune` now prunes
it as a fourth stream, `api`, so the nightly line you already schedule picks
it up with no change. Like the other two delete stages it stays off (`0`)
until you set `MGR_LOG_PRUNE_API_DELETE_AFTER_DAYS`; `.env.sample` recommends
60. That is longer than `app/`/`cli/`'s 30 / 3 on purpose: besides the debug
logs, `api_log` is the record of which `uri` received which GET/POST, so size
it to how far back you review it.

New installs get an index on `api_log.time`; existing ones don't need it —
`docs/development/docker.md`'s "Retention" section covers adding it
(including a non-blocking PostgreSQL build) and reclaiming disk after the
first prune, which deleting rows alone does not do.

### `tools` and nginx's published ports left `docker-compose.yml` — an instance without `TOOLS_BIND_PATH` loses `tools`

Reaches a project when it reconciles `docker/docker-compose.yml`,
`docker_manage.sh`, `docker/env/sample.docker.env`, and the two new files
beside the compose file, `docker/docker-compose.tools.yml` and
`docker/docker-compose.ports.yml`; `composer update` alone changes nothing.
Take them together: the new compose file without the new wrapper has no
`tools` service and publishes no ports.

The base compose file now carries only what a server runs. `docker_manage.sh`
adds an addon when its keys are non-empty in `<instance>.docker.env`:
`TOOLS_BIND_PATH` adds the `tools` service, `HTTP_PORT` or `WS_PORT` adds
nginx's published ports. No flag, so the dev flow is unchanged.

- **An instance `docker.env` copied from an older template may have no
  `TOOLS_BIND_PATH`.** Check with `grep -n '^TOOLS_BIND_PATH='
  docker/env/*.docker.env` and add `TOOLS_BIND_PATH=..` to each dev instance
  that runs `tools`, on a line of its own (copies of older templates end
  without a newline). Without it,
  `run --rm tools` reports `no such service: tools` (the wrapper prints a
  note saying why).
- **Instances that publish already carry both port keys** and change
  nothing. Deleting both now means nothing is published — what a server
  behind a proxy container on a shared Docker network wants.
- **Anything that runs the compose file without the wrapper** gets neither
  the `tools` service nor nginx's ports from it; pass
  `-f docker-compose.ports.yml` as well where it should publish. A server
  that edited its compose copy to remove nginx's `ports:`, or pinned
  `TOOLS_BIND_PATH` to keep `tools` from mounting its parent directory, can
  drop both edits.
- Merging uses repeated `-f` only — no `include:`, no `!reset`.
- Bringing an existing instance up with the new files recreates nothing
  that didn't change: php, nginx and the Valkeys keep running.

### The bundled-service groups of the docker env now carry `DB_DRIVER`, `LIB_REDIS_PORT` and `LIB_REDIS_SOCKET_TYPE` — adopting them moves a native-driver instance with a bundled database to PDO

Reaches a project when it reconciles `docker/env/sample.env` into its
`docker/env/<instance>.env` files. Those keys used to come only from the base
`.env.<instance>`. `<instance>.env` loads last, and its groups for the
bundled services now set them — an instance on an external service deletes
the matching group, and the base's values apply as before:

- **Redis group** (bundled `valkey-cache`): `LIB_REDIS_PORT=6379`,
  `LIB_REDIS_SOCKET_TYPE=tcp`, beside the `LIB_REDIS_HOST` it already set.
  Nothing changes for an instance already on those values.
- **Bundled-database block:** `DB_DRIVER=pdo/mysql`, beside `DB_HOST`/
  `DB_PORT`. **An instance with a bundled database whose base says `mysqli`
  or `postgre` runs PDO once it adopts this line**, and the JSON an API
  returns changes type: PDO fetches native int/float/bool where the native
  drivers return strings. `PDO::ATTR_STRINGIFY_FETCHES`, commented in
  `application/config/database.php`, restores strings, and
  `docs/development/database.md` holds the trade-off. **PostgreSQL:** set
  `DB_DRIVER=pdo/pgsql` in the block, or the template's `pdo/mysql`
  overrides a correct base and the app can't connect.

Verify per key, after recreating the app containers — `source=process-env`
means the docker layer set it:

```bash
./docker_manage.sh -e <instance> exec php bash /var/www/html/bin/cli_run.sh manager/tools/env_check DB_DRIVER
```

### Bundled database services no longer require `DB_NAME`/`DB_USER` at interpolation — mysql/mariadb start without them

Reaches a project when it reconciles `docker/docker-compose.yml` and
`docker/postgres/entrypoint.sh` together. Compose validated `DB_NAME`/
`DB_USER` for the bundled database services even when no database profile
was active, so an instance without a bundled database had to carry dummy
values; they now default to empty, and the dummies can go.

- **PostgreSQL still refuses**, now from its entrypoint:
  `[postgres-entrypoint] FATAL: DB_USER and DB_NAME must be set in the
  instance's env file; not starting`, before it touches the volume. Take the
  entrypoint with the compose file: the old entrypoint under the new compose
  file would start a fresh cluster's init with an empty `DB_USER`, fail it,
  and leave a cluster that refuses every start until the volume is reset.
  The healthcheck changed too, so the `postgres` container is recreated
  once on the first `up` — a short database restart; an existing cluster
  is reused, not re-initialized.
- **MySQL/MariaDB no longer refuse.** With the profile active and either
  value empty, the container starts healthy without creating the database
  or user. MySQL logs one `[Warn]` (`MYSQL_PASSWORD specified, but missing
  MYSQL_USER`); MariaDB logs nothing. These values reach the database
  services through `<instance>.env` (compose interpolation), not through the
  base `.env.<instance>` the app reads, so the app's own required-key check
  doesn't catch it: on an instance whose `DB_*` live in the base for an
  external database, starting `--profile mysql`/`mariadb` brings up a
  server with no app database or user while the app keeps using the
  external one. Keep `DB_NAME`/`DB_USER` in `<instance>.env` whenever a
  bundled profile runs.
