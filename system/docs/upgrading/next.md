# Upgrading — unreleased

Changes between 2.x releases that alter behavior a project already depends on.
Everything else in a minor release is additive.

## Language selection is resolved in the controller base

The `Language` controller (`language/change/{locale}`) that the 2.0.0 table
listed as a package replacement was never routable: the package
`controllers/` directory was not a module location. It is removed. A project
that still has `application/controllers/Language.php` deletes that file but
keeps the (empty) `application/controllers/` directory, which module routing
resolves through. A project that wants a language picker
writes `$_SESSION['language']` from its own controller.

The request language is now resolved in the controller base, before anything
loads a language file, and only values listed in `MGR_LANGUAGES` are accepted
(exact match; anything else is ignored and the configured `language` stays).

- **Every web and REST controller** reads the `X-Language` request header.
  Ion Auth messages follow it. The header is in the default
  `REST_ALLOWED_CORS_HEADERS`; a project that sets that variable replaces the
  whole list and must add `X-Language` itself for cross-origin frontends.
- **`?language=` and `$_SESSION['language']` now apply only to controllers
  that extend `APP_Site_Controller`.** A web controller extending
  `MY_Controller` that used either stops switching; make it extend
  `APP_Site_Controller`. `?language=` still needs `$language_enabled`; the
  session value does not.
- **A language missing from `MGR_LANGUAGES` is no longer applied.** The
  default list is `english,spanish`; a project that reaches `japanese` (or
  any other locale) adds it to `MGR_LANGUAGES`. The REST controller loads its
  own `rest_controller_lang.php` in the resolved language when `REST_LANGUAGE`
  is unset (the package ships it for `english`, `spanish` and `japanese`), so
  a locale a project adds itself needs that file under its language
  directory, or `REST_LANGUAGE` set to pin the REST messages.
- **`REST_LANGUAGE` unset follows the request language** (otherwise the
  configured `language`, `english` by default). Set it to keep REST's own
  messages fixed; `.env` files that carry `REST_LANGUAGE=english` keep that
  pin.
- **`Accept-Language` is not read.** Override `language_candidates()` in a
  base controller to add it; `mgr-web-controllers` has an example.

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

## The bundled `postgres` profile refuses the superuser over TCP

Reaches a project only when it reconciles `docker/postgres/` from the sample
(a compose copy run by other tooling also needs `postgres/` re-copied beside
it); `composer update` alone changes nothing.

- **`postgres` logs in only from inside the container**, as MariaDB's `root`
  now does. A new cluster refuses it over TCP from anywhere but loopback. One
  created earlier keeps accepting it until a one-time step:

  ```bash
  ./docker_manage.sh -e <instance> --profile postgres exec -u postgres postgres sh /docker-entrypoint-initdb.d/20-superuser-local-only.sh
  ./docker_manage.sh -e <instance> --profile postgres exec -u postgres postgres psql -U postgres -Atc 'SELECT pg_reload_conf()'
  ```

  A throwaway local instance can `down -v` instead. The app role, `docker
  exec` logins and `bin/db-backup.sh` are unaffected; anything that logged in
  as `postgres` over the network no longer can.
