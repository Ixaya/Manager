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

**Sandboxed or firewalled environments:** the image build downloads from
Docker Hub, the Alpine package mirrors, `pecl.php.net`, and (vendor stage)
Packagist/GitHub. Sandbox network policies commonly block `pecl.php.net` by
default, which makes the build fail slowly and confusingly. Check
connectivity first; if anything is blocked, stop and ask the operator to
allow the domain instead of retrying the build:

```bash
for host in dl-cdn.alpinelinux.org pecl.php.net repo.packagist.org api.github.com; do
    curl -sfI --max-time 10 "https://${host}" > /dev/null && echo "OK      ${host}" || echo "BLOCKED ${host}"
done
```

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

## Live-code dev modes (`-b`, `-m`)

`-b` bind-mounts a host checkout's `application/` (read-only) over the baked
image code so edits apply without rebuilds; `-m` does the same for an
`ixaya/manager` checkout's `system/` over the vendor copy. Set the source
paths in `docker/env/<you>.docker.env`:

- `CODE_BIND_PATH` (`-b`) — path containing `application/`
- `MANAGER_BIND_PATH` (`-m`) — path containing a manager checkout's `system/`

Relative values resolve against `docker/`, never your shell's cwd — `..` is
this app's root. Absolute paths also work. `docker_manage.sh` verifies the
resolved path before starting anything. `public/`, `vendor/`, and `bin/` are
never bound — an `index.php` or composer change needs a rebuild.

```bash
./docker_manage.sh -e <you> -b up -d        # app code live
./docker_manage.sh -e <you> -b -m up -d     # app + framework live
```

The two flags are independent and only cover their own layer: `-b` surfaces
`application/` changes, `-m` surfaces `system/` changes. Editing one layer
without its flag is a **silent no-op** — the container keeps serving the baked
copy. This bites hardest with migrations: add a file under
`application/modules/<m>/migrations/` while running `-m` only and
`manager/tools/plan` reports `pending:0` as if the file didn't exist. Pass both
flags whenever you're editing both layers.

## First login on a fresh database

Migrations seed one admin user whose factory password is unusable until
claimed. Claim it once and store what it prints:

```bash
./docker_manage.sh -e <you> exec php bash /var/www/html/bin/cli_run.sh manager/tools/claim_admin
```

Write the printed credentials into `docker/env/<you>.agent.env` (gitignored;
template: `docker/env/sample.agent.env`) — never into committed files. Then
log in through the normal auth endpoint to obtain an `api_key` for
`X-API-KEY` requests. The command refuses once the account is claimed;
details and invariants live in the `ixaya-auth` skill (Bootstrap section).

CLI commands inside the running stack — always via `bin/cli_run.sh`, never
plain `php`:

```bash
./docker_manage.sh -e <instance> exec php bash /var/www/html/bin/cli_run.sh manager/health_checks
./docker_manage.sh -e <instance> logs -f php
```
