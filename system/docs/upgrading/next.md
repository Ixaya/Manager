# Upgrading — unreleased

Changes between 2.x releases that alter behavior a project already depends on.
Everything else in a minor release is additive.

### Case-insensitive identity columns: set `DB_COLLATION_CI` / `DB_LOCALE_CI` before migrating

A new package migration, `20261014120000_Manager_ion_auth_v4`, makes
`user.username`, `user.email` and `login_attempt.login` case-insensitive
columns, and gives `username` (and on PostgreSQL also `email`) a unique key.
It also indexes `login_attempt` on `(login, ip_address)` and `(time)`: every
lockout check and every successful login's purge of old attempts scanned the
whole table before.
Login, the duplicate checks and lockout then match case variants on every
engine. New projects also get exact (case- and accent-sensitive) database
defaults. `docs/development/database.md`'s "Case sensitivity" section has the
model; what an existing project does:

- **Set the new key before running `migrate`**, in every connection group
  that runs the manager migrations: `dbcollatci` (env `DB_COLLATION_CI`) on
  MySQL/MariaDB, `dblocaleci` (env `DB_LOCALE_CI`) on PostgreSQL. The sample
  `database.php` and env templates show both. Without it the migration stops
  before changing anything:
  `MGR_Migration_builder: case_insensitive column 'username' needs the 'dbcollatci' setting on the connection to database '…'`.
  The same key is what `like_ci()` searches need on MySQL/MariaDB.
- **MySQL/MariaDB: keep your `DB_COLLATION`, and set `DB_COLLATION_CI` to the
  collation your identity columns already have.** The migration then changes
  no comparison semantics; it only adds the `username` unique key. Read the
  current value with:

  ```sql
  SELECT TABLE_NAME, COLUMN_NAME, COLLATION_NAME FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND ((TABLE_NAME = 'user' AND COLUMN_NAME IN ('username', 'email'))
      OR (TABLE_NAME = 'login_attempt' AND COLUMN_NAME = 'login'));
  ```

  Never copy the scaffold's new exact `DB_COLLATION` into an existing
  project: every later `CREATE TABLE` would get it while older tables keep
  the old one, and `=` between columns of the two collations fails with
  ERROR 1267 *"Illegal mix of collations"*. For the same reason, choosing a
  `DB_COLLATION_CI` that differs from your other columns makes your own
  joins to these three columns fail with that error.
- **PostgreSQL: 18 or later is required.** The columns get a
  nondeterministic collation (`manager_case_insensitive`, created from
  `DB_LOCALE_CI`; recommended `und-u-ks-level2`). Plain `LIKE` on them needs
  18+, and `ILIKE` on them errors on every version: search them with
  `like()`, not `like_ci()` or a hand-written `ILIKE`. The app role needs
  `CREATE` on the schema once, to create the collation.
- **PostgreSQL installs gain the `email` unique key that v3 never created**
  (CI3's PostgreSQL forge silently dropped `unique` in `modify_column()`).
  Existing data may hold exact duplicate emails as well as case variants.
- **Fix duplicate `username` / `email` rows by hand first.** The migration
  checks each column under the case-insensitive collation before altering
  anything, and stops on any duplicate with the table, column, group count
  and the user ids per group (first 20 groups, never the values). It checks
  one column per run, so a database with both kinds stops twice. It never
  chooses a surviving account.
- **Skipping it:** `manager/tools/version_set 20261014120000 vendor:ixaya:manager:system:package:modules:manager <connection>`
  records the migration without running it, and the code works without it.
  On MySQL/MariaDB that loses the `username` unique key and the NOT NULL
  correction below. On PostgreSQL it
  also keeps the columns case-sensitive (login and lockout stay exact) and
  skips the `email` unique-key repair. Either way it also skips the
  `login_attempt` indexes; add them with your own migration if the table is
  large.
- **MySQL/MariaDB: four Ion Auth columns become NOT NULL.** `user.ip_address`,
  `user.email`, `user.created_on` and `login_attempt.ip_address` were left
  nullable by an earlier migration on those engines; PostgreSQL always had
  them NOT NULL. Before changing anything the migration looks for NULLs and
  stops with the table, column, row count and ids (first 20, never the
  values); it never fills a value in. Fix those rows by hand and run `migrate`
  again. The next entry has the cause.
- **A collation change rebuilds the table** on MySQL/MariaDB and takes an
  ACCESS EXCLUSIVE lock on PostgreSQL; plan a window for a large `user` or
  `login_attempt` table. The two new `login_attempt` indexes are built in the
  same run, after the collation change, so they add no second lock window on
  PostgreSQL. MySQL/MariaDB do not roll back a failed run: fix the
  cause and run `migrate` again (every step is idempotent).
- **Scaffold values reach new projects and new data volumes only.** The
  templates now ship `utf8mb4_0900_as_cs` / `utf8mb4_0900_as_ci` (MySQL) and
  `utf8mb4_uca1400_nopad_as_cs` / `utf8mb4_uca1400_nopad_as_ci` (MariaDB);
  the compose `--collation-server` lines follow the exact values, and the
  bundled PostgreSQL initializes with `POSTGRES_INITDB_ARGS:
  --locale-provider=icu --icu-locale=und`. An existing database volume keeps
  its server default; changing a PostgreSQL cluster's default is a dump and
  restore.

### An unspecified `nullable:` in `field()` now means NOT NULL on every operation

Reaches a project through `composer update`; the scaffold's migration-builder
test door and test (`tests/support/MigrationBuilderHarness.php`,
`tests/unit/migrations/NullabilityDefaultTest.php`) come with reconciling the
sample.

A `field()` without `nullable:` used to mean three things: NOT NULL in
`create_table`, nullable in `add_column`, and on `modify_column` unchanged on
PostgreSQL but nullable on MySQL/MariaDB (`MODIFY` redefines the whole
column). That is how Ion Auth's `modify_column()` calls left four columns
nullable on MySQL/MariaDB only. It now means NOT NULL on all of them.
Migrations that already ran are never re-executed, so no existing database
changes; what changes is migrations that run after you upgrade (a fresh
install, a new migration):

- **`create_table`:** no change.
- **`add_column` without `nullable:`:** NOT NULL instead of nullable. Add
  `nullable: true`, or a `default` for a populated table.
- **`modify_column()` / `modify_column_cast()` without `nullable:`:** NOT
  NULL on every engine. Restating a nullable column this way now needs
  `nullable: true`; without it, PostgreSQL fails while rows hold NULL, and
  MySQL/MariaDB with strict mode off rewrite the NULLs to `''`/`0`.
- **New `assert_no_nulls(table:, columns:)` builder helper** stops with the
  row ids before a tightening; call it ahead of such a `modify_column()`.
- **MySQL/MariaDB projects: your own tables may already be nullable where
  you meant NOT NULL.** A column created NOT NULL and later restated with
  `modify_column()` without `nullable:` was made nullable. List what is
  nullable and compare it with what your migrations declared:

  ```sql
  SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND IS_NULLABLE = 'YES';
  ```

  Restore a column with a new migration that calls `assert_no_nulls()` and
  then `modify_column()` with `nullable: false`, or leave it as it is.

### `modify_column(unique: true)` now creates the unique index on PostgreSQL

CI3's PostgreSQL forges never emitted `unique` on a column change. A new
framework forge subclass (`system/core/MGR/Forge/`, loaded by
`MGR_Loader::dbforge()`) appends `CREATE UNIQUE INDEX IF NOT EXISTS
{table}_{column}_key` for each such field, so fresh installs get
`user_email_key` at v3. Your own migrations that relied on the silent no-op
now get the index, or fail on duplicates they used to accept. On
MySQL/MariaDB nothing changes; there, repeating `unique: true` on a column
that already has a unique key still adds a second one, so prefer
`add_index(unique: true)`.

A project `MY_Loader` that does not extend `MGR_Loader` (the sample's does)
does not get the forge subclass.

### Migration runner: a throwing migration is rolled back, and an exact-version run stays on one connection

- `MGR_Migration::version()` rolls back any transaction still open on the
  migration connection before rethrowing, so a migration that fails between
  `start_ddl_transaction()` and `end_ddl_transaction()` leaves PostgreSQL
  clean instead of an aborted transaction.
- `manager/tools/migrate <version> <module_key>` ran the migration's builder
  SQL and its `dbforge` SQL on two different connections, because the
  version lookup replaced `$CI->db`. On PostgreSQL, inside a transaction,
  that hung. The lookup now uses its own handle and leaves `$CI->db` alone; a
  project `MY_Migration_module_lib` that relied on the swap will notice.

### New: `CF_ENCRYPTION_CIPHER` selects the encryption cipher, and `generate_enc_key` sizes the key for it

Additive; off by default. Nothing changes for an instance that does not set
it: the cipher stays `aes-128` and a key already in use keeps working.

`aes-256` is the production recommendation (`.env.sample.prod`) but is **not
adopted automatically**, and switching a live instance is a data migration:
ciphertext carries no cipher marker, so after changing the cipher or the key
everything encrypted under the old setting returns `false` from `decrypt()`
until it is re-encrypted. Set the cipher and a matching key together on a new
instance, or re-encrypt first.

- **Key length follows the cipher:** `aes-128` takes 32 hex chars, `aes-256`
  takes 64. With `CF_ENCRYPTION_CIPHER` set, a key shorter than the cipher
  needs stops the app with an error instead of being zero-padded by OpenSSL
  (a 16-byte key under `aes-256` ran as a 128-bit key before). An unknown
  cipher name is refused the same way.
- **`manager/tools/generate_enc_key` now takes `[cipher|bytes]`.** No argument
  sizes the key for the configured cipher, so the default output is the same
  16 bytes as before; a byte count still works. A zero, negative or
  non-numeric argument now fails with a message instead of an uncaught
  `ValueError`, and a fractional one no longer becomes a one-byte key.
- **Reaches a project** through `composer update` (`system/package/config/encryption.php`;
  a project's own `application/config/encryption.php` takes precedence);
  the `.env.sample` line and the key comments in `.env.sample.priv` /
  `docker/env/sample.priv.env` come with reconciling the sample.

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

### CLI failures go to stderr with exit 1, and a web error behind buffered output answers its real status

Reaches a project through `composer update`; the new `APP_Cli_Controller`
comes with reconciling the sample.

Under the CLI:

- **The error block moves from stdout to stderr, and the process exits 1
  instead of 0.** This covers `show_error()`, `show_404()` (an unknown
  command), PHP errors that reach the renderer, and uncaught exceptions,
  including REST controllers run through `bin/cli_run_api.sh`. stdout carries
  only the command's result. A script that searched stdout for `**ERROR`, or
  treated exit 0 as success, needs a look. In a log written with `2>&1`, the
  error now appears above any output the command had buffered before it.
- **`manager/tools` commands and async jobs now print an uncaught exception
  in production.** They used to exit 1 with nothing on either stream, because
  CI3's handler prints only with `display_errors` on. Message, class, file and
  line now reach stderr, which a job's `2>&1` sends to its `cli/*.log`. A
  failing async job logs the exception twice in the app log
  (`CLI run failed: …`, then the exception itself). PHP warnings are unchanged: logged, and the command continues in
  production.
- **New: `APP_Cli_Controller`** gives a project's own CLI controllers and
  crons the same behavior: it refuses HTTP and sends an uncaught exception to
  stderr with exit 1. Additive: nothing changes until a controller extends
  it. To adopt, copy `sample/application/core/APP_Cli_Controller.php` into
  `application/core/`, change `extends CI_Controller` to
  `extends APP_Cli_Controller`, and drop the constructor's `is_cli()` guard.
  A controller that defines its own `_remap()` does not get the guard.
- **An HTTP request to a CLI route now answers 403 instead of 500**, and no
  longer writes an app-log error line. This covers `manager/tools`,
  `manager/websockets`, and any controller extending `APP_Cli_Controller`.
  A monitor that alerted on those 500s, or on their log lines, goes quiet.

On the web (the JSON error path; the HTML views used with
`$api_only = false` are unchanged):

- **An error raised while output is buffered now answers its real status,
  with the error as the whole body.** It used to answer **200 with the
  partial output** (a half-rendered view, a half-written response), and
  execution continued past the error. What the body discloses is unchanged:
  production gets the generic envelope.
- **In development, an error after the response is complete replaces it.**
  A warning in a shutdown function or destructor that runs after a REST
  response now answers 500 with the error instead of the 200: a result
  produced alongside an error is not trustworthy. Front-end code that strips
  warning text from the body to recover the JSON stops working, by design.
  Production is unchanged.

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
