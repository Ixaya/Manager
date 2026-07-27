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
env-files under `sample/docker/env/` instead of hardcoding them.

This is not a fallback for machines without host PHP — Docker is the only
supported way to run these commands, regardless of what's installed on the
host. It pins the exact PHP version and extension set the stack ships, so a
failure reproduced on the host isn't yet a framework bug. Rationale and
evidence: `docs/development/docker-decisions.md`.

## Where a change goes

`AGENTS.md`'s repo map locates the trees. Two placement rules are easy to get
wrong, and both are invisible until a consuming project breaks.

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
`docs/development/auth-upstream.md`.

## Quality gates

There is no framework test suite. PHPStan (level 5, `phpstan.neon`) and
php-cs-fixer (PSR-12 with tabs, `.php-cs-fixer.php`) are the gates, and both
must pass before a change is finished.

Both analyse the repository root, while the `tools` service is bound to the
scaffold by default. Point an instance's bind path one level higher and the
service works on the repository instead:

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
trusting a green result — grep an edited symbol inside the container — or a
pass may be the released package's behavior rather than yours.

### Optional (suggest-only) framework dependencies

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

Anything touching the database must hold on MySQL/MariaDB, PostgreSQL, SQL
Server, and SQLite. Bring up the profile the change actually touches, and run
the matrix (`--profile postgres`, `--profile mysql`, `--profile mariadb`)
before a release when DB behavior changed: driver quirks have made a fix that
was correct on one engine wrong on another. Tear down with every profile flag
the session used, or the leftover containers outlive it:

```bash
./docker_manage.sh -e <instance> -b -m --profile <db> down -v
```

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
