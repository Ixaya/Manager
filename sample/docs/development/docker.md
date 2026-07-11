# Docker stack — local setup

All operations go through the wrapper — never `docker compose` directly:

```bash
./docker_manage.sh -e <instance> <docker compose args...>
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

## Build and run

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

CLI commands inside the running stack — always via `bin/cli_run.sh`, never
plain `php`:

```bash
./docker_manage.sh -e <instance> exec php bash /var/www/html/bin/cli_run.sh manager/health_checks
./docker_manage.sh -e <instance> logs -f php
```
