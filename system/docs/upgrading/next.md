# Upgrading — unreleased

Changes between 2.x releases that alter behavior a project already depends on.
Everything else in a minor release is additive.

## Migrations: the package runs first

`manager/tools/migrate` (and `plan`) now order targets as the package's own
migrations, then the application's `database/migrations`, then the other
modules by key. Before, the package ran last, so a project migration that
altered a package table (for example adding a column to `user`) failed on a
fresh install with the table missing.

Nothing to do for an installed database: recorded versions are untouched. Two
things to check:

- A fresh install whose app-level history creates a package table itself
  (`user`, `group`, `user_group`, `login_attempt`) now finds the package's
  table already there. Remove or skip that migration; the package owns those
  tables.
- A pending package migration in an upgrade now runs before the project's
  pending ones.

## The bundled `mariadb` profile runs a healthcheck as the app user, closes `root@'%'`, and becomes a server tier

Reaches a project only when it reconciles `docker/docker-compose.yml`,
`docker/env/sample.docker.env` and `bin/` from the sample; `composer update`
alone changes nothing.

- **The healthcheck changed:** `mariadb` is healthy once `DB_USER` can log in
  to `DB_NAME` over TCP, not merely when the server answers. An instance whose
  data directory was initialized with an empty `DB_NAME`, `DB_USER` or
  `db_password` therefore turns `unhealthy` after the reconcile; the reason is
  in `docker inspect`. Nothing in the stack acts on that, but a host tool that
  restarts unhealthy containers would. It also reports unhealthy while every
  connection slot is taken.
- **`root` logs in only from inside the container** (`MARIADB_ROOT_HOST:
  localhost`). A new data directory gets no `root@'%'`. One created earlier
  keeps it until a one-time step, over the socket:

  ```bash
  ./docker_manage.sh -e <instance> --profile mariadb exec -T mariadb sh -c 'MYSQL_PWD="$(cat /run/secrets/db_root_password)" exec mariadb -uroot' <<< "DROP USER 'root'@'%';"
  ```

  A throwaway local instance can `down -v` instead. The app user, `docker
  exec` logins and `bin/db-backup.sh` are unaffected; anything that logged in
  as `root` over the network no longer can.
- **New, for servers (additive):** `MARIADB_DATA` in `docker.env` (empty
  keeps today's named volume), the `mgr.backup=mariadb` label, and a `mariadb`
  branch in `bin/db-backup.sh`. **Re-copy `bin/db-backup.sh` to wherever a
  host runs it from before the reconciled stack starts:** a copy without the
  branch fails the site with `unsupported engine` while a `mgr.backup=mariadb`
  container runs. Setup, backups, restore and series bumps are in
  `docs/development/docker-server.md`.
- **A MariaDB series bump upgrades the data directory on its first start, and
  putting the old pin back is not a rollback.** `MARIADB_AUTO_UPGRADE=1` is
  unchanged; what is new is the documented rule: dump first, rollback is a
  restore. Compose and `docker.md` now say "series bump" where they said
  "version bump".
