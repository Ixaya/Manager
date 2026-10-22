# Framework development workflow

> Scope: the day-to-day loop for changing this repository — where a
> change goes, which gates it must pass, and proving it executes rather
> than merely reads correctly. A consuming project's own loop is
> documented instead in the shipped scaffold's `sample/docs/development/`.

The day-to-day loop for changing this repository: where a change goes, which
gates it must pass, and how to prove it executes rather than merely reads
correctly. A consuming project's loop is a different thing and is documented
in the shipped scaffold's own `sample/docs/development/`; this file is for
the framework repository.

Everything runs through the Docker stack in `sample/` — run the commands
below from that directory, and read ports, hosts, and paths from the instance
env-files under `sample/docker/env/` instead of hardcoding them. Setting up a
fresh instance follows `sample/docs/development/docker.md`'s generic setup
steps; for framework development, default it to `--profile postgres` /
`DB_DRIVER=pdo/pgsql` — see `docker-decisions.md` for why.

This is not a fallback for machines without host PHP — Docker is the only
supported way to run these commands, regardless of what's installed on the
host. It pins the exact PHP version and extension set the stack ships, so a
failure reproduced on the host isn't yet a framework bug. Rationale and
evidence: `framework/docs/development/docker-decisions.md`.

## Where a change goes

`AGENTS.md`'s repo map locates the trees. Four placement rules are easy to
get wrong, and all four are invisible until a consuming project breaks.

**A framework library is three files, not one.** The project-side pattern —
one `application/libraries/X_lib.php` plus `application/config/lib_x.php` —
does not apply here. A framework library that ships without its unprefixed
alias breaks the alias chain for every project that loads it:

| File | Role |
|---|---|
| `system/libraries/MGR_X_lib.php` | the implementation, class `MGR_X_lib` |
| `system/package/libraries/X_lib.php` | thin alias — `require` the MGR file, then `class X_lib extends MGR_X_lib {}` with an empty body |
| `system/package/config/lib_x.php` | its configuration, values read through `mgr_env()` |

`Jwt_lib` is the exemplar to copy from. The unprefixed alias is what an
application loads (`$this->load->library('jwt_lib')`) and what an app-level
`MY_`/`APP_` subclass extends, so it has to exist even when it adds nothing.
Naming, how the library reaches CI, and the two config modes are in the
`mgr-helpers-libraries` skill.

**`system/third_party/` is upstream-tracked.** MX, the BE Ion Auth fork, and
REST_Controller stay close to their upstreams so updates merge cleanly — fix
in the `MGR_` subclass layer instead. When there is no subclass seam, the
deliberate-exception procedure and the worked example are in
`framework/docs/development/auth-upstream.md`.

**Write the `sample/` prefix explicitly, always.** A bare `application/…` or
`docker/…` path is ambiguous between a consuming project's tree and the
scaffold, and the two are edited by different sessions for different reasons.
Any note marking a file "project-owned, the framework can't push this" needs
its companion — *…but `sample/` carries the canonical copy, so edit `sample/…`
in the same change.* The trigger is not a convention change (that rule is in
`AGENTS.md`): it is any file that exists in both trees, config wiring
included. Two half-landed edits to a twinned file break consuming projects in
ways neither edit would alone — a log-path change once landed in a project's
`application/config/config.php` while the scaffold's copy kept the superseded
key, and since the Docker half of the same work *did* reach `sample/`, new
projects got a `log_path` resolving to empty and a read-only-filesystem error
on first boot.

**A new `system/core/` class must not re-`require` a parent file CI3
already loads unconditionally.** `MY_Controller.php`, `MY_Model.php`, and
the rest of the `subclass_prefix` shims are `require_once`d by CI3 itself
before any controller/model is resolved (`CodeIgniter.php:368-370`), so
their own target (`MGR/Controller.php`, `MGR/Model.php`, …) is already
loaded by the time a new class needs it. Adding a second `require` for that
same file is a fatal "Cannot redeclare class", not a harmless duplicate —
every shim in this codebase uses plain `require`, which doesn't deduplicate
the way `require_once` does. Extend the parent class with no `require` at
all, the way `MGR_Api_Model` (`extends MY_Model`, no require) and
`MGR_Site_Controller` (`extends MGR_Controller`, no require) both do.

One more that breaks nothing, so it drifts silently: **a new `mgr_env_*()`
read in a `system/package/config/*.php` file needs a matching placeholder in
`sample/.env.sample`**, in that file's existing `#<file>.php::<SECTION>`
comment block. The template is the project-facing reference for what is
configurable, and it does not stay accurate on its own.

## Quality gates

There is no framework test suite. PHPStan (level 5, `phpstan.neon`) and
php-cs-fixer (PSR-12 with tabs, `.php-cs-fixer.php`) are the gates, and both
must pass before a change is finished.

Both analyse the repository root, while the `tools` service is bound to the
scaffold by default. Point an instance's bind path one level higher and the
service works on the repository instead (the same key is what makes
`docker_manage.sh` include the tools addon, so it must be set either way):

```bash
# sample/docker/env/<instance>.docker.env
TOOLS_BIND_PATH=../../
```

```bash
./docker_manage.sh -e <instance> run --rm tools composer install
./docker_manage.sh -e <instance> run --rm tools vendor/bin/phpstan analyse
./docker_manage.sh -e <instance> run --rm tools vendor/bin/php-cs-fixer fix
```

`php-cs-fixer check --diff` reports without writing. The bind path is
per-instance and cannot be both places at once, so keep a dedicated instance
for the repository gates and leave the scaffold instance at its default `..`
— that one is what runs the scaffold's own PHPUnit suite below.

## Proving a change executes

Reading a diff confirms it looks right, not that it runs right. Verify live
when behavior changed — comparison semantics, casts, anything touching auth,
DB, session state, or cross-engine SQL; a string or comment fix is fully
confirmed by a grep. Two mechanisms, and the split is deliberate:

- **Throwaway probes**, for "does this actually execute". A REST controller
  under `sample/application/modules/probes/`, which is gitignored, exists
  only for framework development, and never ships to consuming projects (the
  scaffold is copied from a git checkout, where the module is absent). One
  method per thing verified, so any one of them re-tests in isolation, and
  left in place afterwards for the next re-validation. Don't scatter test
  code anywhere else in `sample/` or `system/`. The conventions —
  authenticated-not-bypassed, the `.dockerignore` guard, the run recipe, and
  the three log channels that catch silent errors — are in the
  `mgr-live-probes` skill.
- **The scaffold's PHPUnit suite** (`sample/tests/unit/`), for permanent,
  order-independent assertions. It is an integration suite against a real
  database, not mocks. `sample/AGENTS.md` covers writing for it.

### The vendor mirror lags the working tree

`sample/vendor/ixaya/manager/` is an installed release of the package. It
does not track the tree you are editing and is never updated by hand, so a
stack started plainly exercises released framework code, not your change.

Two consequences before any of the mechanics below. **Never edit anything
under it** — a fix applied there is applied to a copy that the next
`composer update` overwrites, and it will look like it worked. And **exclude
it from every corpus grep**, or each hit in `system/` returns twice and any
count taken from the result is wrong.

Two flags fix that for the runtime services: `-b` binds the application tree
and `-m` binds this repository's `system/` over the mirror, each reading its
host path (`CODE_BIND_PATH`, `MANAGER_BIND_PATH`) from the instance's docker
env-file.

```bash
./docker_manage.sh -e <instance> -b -m --profile <db> up -d
```

Neither flag reaches `tools`, which is a one-off `run` container outside that
override. Any `tools` command that *executes* framework code — the scaffold's
PHPUnit suite above all — has to mount the live tree itself:

```bash
./docker_manage.sh -e <instance> run --rm \
    -v /abs/path/to/manager8/system:/work/vendor/ixaya/manager/system:ro \
    tools vendor/bin/phpunit --testdox
```

The mount source must be an absolute host path. Confirm the bind took before
trusting a green result (see `mgr-docker-ops`) — grep an edited symbol inside
the container — or a pass may be the released package's behavior rather than
yours.

### Optional (suggest-only) framework dependencies

Whether a new integration needs a vendor SDK at all — versus a hand-rolled
client — is the general call `sample/docs/development/libraries.md` covers.
Once one does, it belongs in `ixaya/manager`'s own `composer.json` as
`require-dev` plus `suggest`, never a hard `require`: most consuming
projects use only a handful of the framework's integrations, and a project
that never touches WebSockets or S3 should not be forced to install
`amphp/*` or `aws/aws-sdk-php`. The library class `use`s the SDK's classes
normally; PHP only resolves them when the class is actually instantiated, so
the dependency's absence stays silent until a project that needs the
integration installs the suggested package.

Some framework features — the WebSocket server (`amphp/websocket-server`,
`amphp/log`, `amphp/redis`, `adhocore/jwt`), the AWS integration
(`aws/aws-sdk-php`), spreadsheet import/export (`phpoffice/phpspreadsheet`)
— are only listed as `suggest` in `ixaya/manager`'s own `composer.json`, so
`sample/vendor/` doesn't have them by default. Live-testing one of these
means adding it to `sample/composer.json`'s `require` (not `require-dev` —
the Dockerfile's build stage runs `composer install --no-dev`, so dev-only
deps never reach the runtime image) and rebuilding.

`sample/composer.json` and `sample/composer.lock` are both gitignored at
the framework root, so there's no need to add it, test, then revert each
session — let them accumulate whatever optional packages your local
checkout needs. They ship minimal today only because this gitignore setup
is recent.

### Cross-engine verification

Engine tiers are in AGENTS.md. Day to day, bring up the profile the change
touches — PostgreSQL for a simple change. The tier-1 matrix
(`--profile postgres`, `--profile mariadb`, `--profile mysql`) runs at the
close of any campaign that touched the database and before a release,
aimed at what actually differs per engine: migrations, type mapping,
collation, and driver-level behavior. Driver quirks have made a fix that
was correct on one engine wrong on another.

**The matrix is engines, not drivers.** Each run uses whatever `DB_DRIVER`
the instance is configured with, which is `pdo/*` by default — the same
driver a new project gets. Doubling every engine with its native counterpart
is the tier-2 release pass, not a routine one. Give `mysqli`/`postgre`
their own pass earlier when a change touches driver-specific
behavior — CI3's driver classes, fetch types, connection handling — or when
asked for it by name. For anything about connections or sessions, judge
native `postgre` against `pdo/pgsql`, never alone: `pg_connect()` returns the
existing link for an identical DSN, so two handles that are separate sessions
on every other driver share one there, and a result that only looks right on
`postgre` proves nothing.

**A second engine beside the running one.** To run a probe or the suite on
another engine without replacing the instance's database, start only that
service (`--profile mariadb up -d mariadb`) and give `exec` or `run` the
engine's whole `DB_*` row as `-e` overrides (the per-engine table in
`sample/docs/development/database.md`, with `DB_LOCALE_CI` emptied on
MySQL/MariaDB). REST probes answer on the instance's own engine, so use a CLI
probe (`probes/<controller>/<method>` through `cli_run.sh`). For migrations,
create a scratch database as the engine's root user, grant it to the app
user, and run `manager/tools/migrate` with `-e DB_NAME=<scratch>`. Tear down
with `rm -s -f -v <service>` and remove that service's one volume: `down -v`
would take the running engine's volume with it.

**A change to what migrations emit is judged by a schema diff.** Migrate a
fresh database to the last migration before the one under change
(`manager/tools/migrate <version> <module_key>`, see the mgr-migrations
skill), dump `information_schema.columns` (table, column, type, nullability,
default, collation), and repeat after the change on every engine; the diff
should hold exactly the intended columns. A migration that corrects existing
data needs a second proof: build the legacy state by hand on a fresh database
(alter the columns back, insert a row the migration must refuse), run it, and
compare the result with a fresh install.

Tear down with every profile flag the session used, or the leftover
containers outlive it:

```bash
./docker_manage.sh -e <instance> -b -m --profile <db> down -v
```

### Another PHP or PHPUnit version

The suite runs on another PHP by changing the image's `FROM php:` pin and
nothing else; judge the cell by the run's own output (the PHP and PHPUnit
versions in its header). Use a throwaway instance, so the real instance's
images, volumes and secrets stay untouched:

- Copy an existing instance's three `docker/env/` files, its three
  `docker/secrets/` files and the base `.env.<instance>` at the `sample/`
  root, and give the copy its own `IMAGE_REPO`, `IMAGE_REPO_NGINX` and
  `IMAGE_TAG`.
- Edit the pin at `sample/docker/Dockerfile` by hand, one version per run,
  and `build tools php`. Put the pin back afterwards; `git status` showing
  the Dockerfile unmodified is the check.
- Set `TOOLS_BIND_PATH` (absolute) to a throwaway copy of `sample/` made
  without `vendor/` and `composer.lock`, and run `composer update` through
  `tools`. The scaffold's own `sample/vendor` and `sample/composer.lock` are
  local state that another PHP or PHPUnit major would overwrite. The copy
  resolves on its own: 8.3 gets PHPUnit 12, 8.4 gets 13; force a major by
  editing the copy's `composer.json`. Leaving the gitignored `probes` module
  out of the copy shows what a consuming project runs.
- Run `migrate` and `phpunit` with the `-v` mount of the live `system/`
  (see "The vendor mirror lags the working tree").
- Tear down with `down -v` for every profile used, and `docker rmi` the
  instance's images. `down -v` also removes the instance's composer-cache
  volume; it refills. The copy's `vendor/` is root-owned, so delete it with a
  container.

### Two migration groups

The scaffold ships one migration group, and its PHPUnit suite has no
multi-group test: a second database would have to ship in every project's
setup. When a change touches how the migration runner or
`MGR_Migration_module_lib` pick their connection, set a second group up
temporarily on a disposable instance and revert all of it afterwards:

1. As the engine's superuser inside the db container, create a second
   database on the same server and give it to the app role (PostgreSQL:
   `CREATE DATABASE <name> OWNER <app role>`; MySQL family: create it and
   `GRANT ALL` on it to the app user).
2. In `sample/application/config/database.php`, add a group copying
   `default` with `database` swapped, and the DSN's `dbname=` too on a PDO
   driver.
3. Put a module migration under `migrations/<group>/`, set
   `MGR_MIGRATION_DB=default,<group>` in the instance env, and recreate
   `php`.

Then check, counting each database's tables and its `migration_time_path`
rows before and after:

- **One pass** — `manager/tools/migrate`: each database holds only its own
  tables and version rows.
- **A repeated entry** — `MGR_MIGRATION_DB=default,<group>,default`: the third
  pass changes neither database.
- **A revisit through the library** — `run('default')`, `run('<group>')`,
  `run('default')` on one `MGR_Migration_module_lib` (a probe method): the
  third runner reports the default database (`current_database()` /
  `DATABASE()`) and writes nothing to the second.
- **`plan`** — both databases are unchanged.

Before the runner re-pointed a memoized connection, the repeated entry and
the revisit wrote the default group's whole schema into the second database and overwrote its
version rows, with no error.

## Closing a change

The framework has no CI to catch what a session forgot, so the last pass is
manual. Four things travel with the code and are the usual omissions:

- The gates above, both green.
- Live verification on the engines the change can affect, when behavior
  changed.
- `sample/` updated in the same change if a convention changed — an outdated
  scaffold teaches the old pattern to every new project.
- The matching skill under `system/skills/` updated if the change alters a
  documented convention.

A surprise found while verifying — a failure unrelated to the change in hand
— is its own finding. Flag it with a proposed correction instead of folding a
second fix into the current one.
