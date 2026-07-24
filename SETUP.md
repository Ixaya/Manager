# ixaya/manager — Setting up a new project from zero

Scope: this guide applies to any project built on the **ixaya/manager**
framework. It is the complete setup path — from an empty project directory
to a running, verified stack with working tests, live-code binding, and
green tooling. Following it does **not** require the package README:
everything needed is either here or in the scaffolded project's own docs.

ixaya/manager is an HMVC CodeIgniter-based framework, always consumed as a
Composer dependency. A project bootstraps once from the included scaffold,
and from then on the framework lives in `vendor/ixaya/manager` and upgrades
with `composer update`. Framework code is never copied into or edited
inside a project.

**Prerequisites:** Docker, plus PHP 8.2+ and Composer if you run the initial
install yourself (step 1). After scaffolding, everything else — including
Composer, the dependency resolution, and the test tools — runs through the
Docker stack, so no host database engine or PHP extensions are required.

Throughout, `local` is the standard name for your own Docker instance — use
it literally unless you have a specific reason to run several instances side
by side (see `docs/development/docker.md`, available once you scaffold).

---

## 1. Install the package

First check whether the package is already required — an operator may have
run this step themselves and handed you a project that already lists it
(common in sandboxes where PHP/Composer aren't available to the agent):

```bash
grep -q '"ixaya/manager"' composer.json 2>/dev/null \
  && echo "already required — skip to step 2" \
  || echo "not yet required — install below"
```

If `composer.json` exists and already lists `ixaya/manager`, **skip to step
2** — don't re-require it, so the operator's chosen version constraint is
left untouched.

Otherwise install it:

```bash
composer require ixaya/manager
```

If PHP 8.2+ or Composer isn't present and you were asked to run the whole
setup yourself, stop and ask the operator whether to install them — they
may prefer to run this one step themselves (or in a container) and have you
continue from step 2. Do not install a language runtime without
confirmation.

**Common failure modes:**
- Re-running `composer require` on a project that already has it — the gate
  above avoids this; it would otherwise risk resetting a version constraint
  the operator pinned deliberately.
- On restricted outbound networks (corporate proxy, sandboxed CI), this
  step needs Packagist and GitHub reachable. Confirm connectivity before
  assuming the package itself is broken.

## 2. Scaffold your project

```bash
cp -r vendor/ixaya/manager/sample/. .
```

This copies your project's starting structure — `application/`, `public/`,
`docker/`, `bin/`, `tests/`, `docs/`, and `AGENTS.md` — into the project
root. It copies your application's starting point, not the framework
itself, which stays in `vendor/`.

The package's `composer.json` ships as `sample/composer.json.sample` — a
**template**, not a live file — so this copy never lands a `composer.json`
of its own. Put one in place by whichever path fits:

- **You already have a real `composer.json`** (step 1 required the package,
  or the operator did): merge the template's `require-dev` block (the
  static-analysis, unit-test, and code-style tools) into it by hand,
  keeping your own `ixaya/manager` version constraint and `php` requirement
  as they are:

  ```bash
  diff composer.json vendor/ixaya/manager/sample/composer.json.sample
  ```

- **You are bootstrapping without host Composer** (the operator ran the
  initial require, or will let Docker resolve everything): copy the
  template into place wholesale — nothing is hand-tuned yet, so there is no
  overwrite risk:

  ```bash
  cp vendor/ixaya/manager/sample/composer.json.sample composer.json
  ```

  This adopts the template's version constraint; if the operator pinned a
  specific `ixaya/manager` version in step 1, re-apply it here before the
  dependency resolution in step 7.

**Common failure modes:**
- Overwriting a hand-tuned `composer.json` with the template instead of
  merging — it would revert a deliberate version pin silently. Only the
  wholesale-copy path is safe, and only because nothing custom exists yet.
- Skipping the require-dev merge entirely, then being surprised the
  test/analysis tools aren't available at step 14.

## 3. Link agent skills

The framework ships its coding conventions as agent skills. Link them into
the project so any coding agent picks them up:

```bash
mkdir -p .claude/skills
for skill in vendor/ixaya/manager/system/skills/*/; do
  name=$(basename "$skill")
  rm -rf ".claude/skills/$name"
  ln -s "../../vendor/ixaya/manager/system/skills/$name" ".claude/skills/$name"
done
```

They are symlinks, so they always track the vendor copy; re-run the loop
after a major framework update to pick up any newly added skills. Which
skill applies to which task is described by the routing table in `AGENTS.md`
— read that rather than trying to memorize the set.

**Common failure modes:**
- Running from the wrong directory — this must run from the project root
  (where `.claude/` lives), not from inside `vendor/`.

## 4. Read AGENTS.md

Open the scaffolded `AGENTS.md` at the project root. It is the project's
entry point: high-level description, project-wide conventions, the
task-to-skill routing table, and links into `docs/`. Every step from here
on assumes you (or your agent) work from `AGENTS.md` and the `docs/` tree
going forward.

**Common failure modes:**
- Skipping this because setup "already started" — this is the step that
  makes the project self-documenting. Read it even if steps 1–3 went
  smoothly.

## 5. Set up your `local` Docker instance — env and secrets

Pick a database engine (PostgreSQL, MySQL, or MariaDB) and create the three
env files plus secret files the stack needs. Each instance name selects its
own env files, secrets, and published ports, so instances never collide:

```bash
cp docker/env/sample.env         docker/env/local.env
cp docker/env/sample.docker.env  docker/env/local.docker.env
cp docker/env/sample.priv.env    docker/env/local.priv.env
chmod 600 docker/env/local.priv.env

openssl rand -hex 24 > docker/secrets/local.valkey_password
openssl rand -hex 24 > docker/secrets/local.db_password
chmod 600 docker/secrets/local.*
```

Then:

- In `docker/env/local.env`, set `DB_HOST`/`DB_PORT` for your engine
  (`postgres:5432`, `mysql:3306`, or `mariadb:3306`).
- In `docker/env/local.priv.env`'s **Docker specific** section (bottom of
  the file, which wins by position), paste the two generated passwords into
  `LIB_REDIS_PASSWORD`, the `auth=` parameter of `CF_SESS_SAVE_PATH`, and
  `DB_PASS`, and generate a real encryption key for `CF_ENCRYPTION_KEY`:

  ```bash
  openssl rand -hex 32
  ```

The exact per-engine values and the reasoning behind the file split are in
`docs/development/docker.md`. These files are what makes `docker_manage.sh`
runnable at all — it aborts if any is missing — which is why they come
before anything that invokes it.

**Common failure modes:**
- Leaving the sample's placeholder secrets (`change-me-*`, the all-zeros
  encryption key) in place — the stack starts, but this is unsafe the
  moment anything sensitive touches it.
- Password mismatch between the `docker/secrets/local.*` files and the
  values pasted into `local.priv.env` — the two must be identical. The
  secret files are what the containers mount; `priv.env` is what the PHP
  app reads.
- MySQL/MariaDB additionally need `docker/secrets/local.db_root_password`;
  PostgreSQL does not.

## 6. Set up the base app env

```bash
cp .env.sample .env.local
```

Set `DB_DRIVER`/`DB_CHAR_SET`/`DB_COLLATION` to match the engine chosen in
step 5 (for example PostgreSQL is `postgre` / `UTF8` / empty). The
per-engine table is in `docs/development/docker.md`; how these values reach
the running process — including a non-Docker, host-PHP run using `.env` /
`.env.priv` — is covered in `docs/architecture/environment.md`.

**Common failure modes:**
- A char-set/collation mismatch for the engine (for example leaving MySQL
  defaults while running PostgreSQL) surfaces later as a cryptic "Unable to
  set client connection character set" error, not an obvious config
  mismatch.

## 7. Resolve dependencies and choose optional integrations

With the instance env files in place, Composer becomes available through the
Docker `tools` service — the single path that works whether or not the host
has PHP. **From here on, run Composer only through the `tools` service, never
on the host.** The `tools` image carries the exact PHP version the runtime
containers use, and Composer resolves `composer.lock` against the running
PHP version — some packages cap at different versions per PHP release. A
host Composer on a different PHP version would write a lock that the stack
then installs from, quietly diverging the versions baked into the image from
what was resolved. Keeping every Composer call in `tools` guarantees the
lock and the stack agree.

Build that image once, then reconcile the lock:

```bash
./docker_manage.sh -e local --profile tools build tools
./docker_manage.sh -e local run --rm tools composer update
```

`composer update` (not `install`) reconciles `composer.lock` with the
`composer.json` you assembled in step 2 — the scaffold's shipped lock
predates the require-dev block, and `composer install` would fail on that
mismatch with a misleading "incorrectly merged" message. This also
populates the host `vendor/` the test tools run from later.

Then review the optional integrations. The built-in WebSocket server, cloud
storage, spreadsheet import/export, and similar ship as **suggested**
packages, so the authoritative list stays with the package and updates
itself rather than being duplicated (and going stale) here:

```bash
./docker_manage.sh -e local run --rm tools composer suggest
```

Ask the operator which of these the project actually needs, and require only
those — none are needed to reach a running stack:

```bash
./docker_manage.sh -e local run --rm tools composer require <package>
```

Every `composer require`/`update` rewrites `composer.lock`, and the image
build in step 8 bakes whatever the lock finally contains. Runtime packages
live in `require`, so they are baked into the app image; the dev tools live
in `require-dev` and stay out of it — the build installs with `--no-dev`
from the same lock.

**Common failure modes:**
- Installing every suggested package "to be safe" — unused integrations add
  dependencies and runtime attack surface for no benefit. Require on demand.
- Reaching for `composer install` on the scaffold's stale lock and being
  misled by the merge-conflict wording — `composer update` is the fix, run
  once. Never hand-edit the lock file.

## 8. Build the images and bring up the stack

With the lock finalized, build the app images — they bake `vendor/` from
that lock, so any optional packages from step 7 are included:

```bash
./docker_manage.sh -e local build
./docker_manage.sh -e local --profile <postgres|mysql|mariadb> up -d
./docker_manage.sh -e local ps   # confirm every service reports healthy
```

If you installed the WebSocket server in step 7, add `--profile ws` to the
`up` command. Always operate the stack through `docker_manage.sh`, never
`docker compose` directly — the wrapper wires the per-instance env files and
secrets the compose file depends on.

A later dependency change never needs a forced rebuild: the build keys its
`vendor/` layer on the contents of `composer.json`/`composer.lock`, so the
next `build` after any `composer require` through the tools service (step 7)
re-bakes `vendor/` automatically. Reserve `--no-cache` for changes outside
the manifests (a moved base image, re-pulled OS packages).

**Common failure modes:**
- Sandboxed or firewalled build environments: the image build reaches
  Docker Hub, the Alpine mirrors, the PECL registry, and Packagist/GitHub.
  If any is blocked, the build fails slowly and confusingly rather than with
  a clear message — run the connectivity check in
  `docs/development/docker.md` first instead of retrying the build.
- Forgetting `--profile <engine>` on `up` — the stack starts without a
  database and every request fails.
- Building the app images before finalizing the lock in step 7 — runtime
  optional packages then aren't present until the next rebuild. Do step 7
  first.

## 9. Run migrations

```bash
./docker_manage.sh -e local run --rm cli -c "bash /var/www/html/bin/cli_run.sh manager/tools/migrate"
```

**Common failure modes:**
- Hitting the app before this step and seeing a bare `500` with an empty
  body — that is the expected state of an unmigrated schema, not a broken
  build. Migrate first, then judge whether something is actually wrong.

## 10. Claim the seeded admin account

Migrations seed one admin user whose factory password is unusable until
claimed:

```bash
./docker_manage.sh -e local exec php bash /var/www/html/bin/cli_run.sh manager/tools/claim_admin
```

Store the printed identity and password immediately — they are shown once
and cannot be recovered. Put them in `docker/env/local.agent.env` (copied
from `docker/env/sample.agent.env`) for later login and probe testing.

**Common failure modes:**
- Losing the printed password, or `claim_admin` reporting the account is
  already claimed. Do not edit the database by hand to work around it. On a
  fresh setup with no data to lose, ask the operator whether to reset the
  database stack and re-run migrations, which recreates the seed admin
  unclaimed:

  ```bash
  ./docker_manage.sh -e local --profile <engine> down -v
  ./docker_manage.sh -e local --profile <engine> up -d
  ./docker_manage.sh -e local run --rm cli -c "bash /var/www/html/bin/cli_run.sh manager/tools/migrate"
  ```

  `down -v` deletes the local database volume — only do this during initial
  setup, and only with the operator's go-ahead.

## 11. Verify the API responds

```bash
curl -sSi http://localhost:8080/
```

Expect `200 OK` with a JSON body such as `{"status":1,"message":"API
running"}`. An empty-bodied `500` here almost always means step 9 was
skipped.

## 12. Wire up live-code binding

Bind-mount the project's `application/` over the baked image code so edits
apply on the next request without a rebuild:

```bash
# in docker/env/local.docker.env:
#   CODE_BIND_PATH=..
./docker_manage.sh -e local -b --profile <engine> up -d
```

Confirm the bind actually took before trusting any later test — grep a
string that exists only in your edited file, inside the container:

```bash
./docker_manage.sh -e local exec php grep -n "<a string from your edit>" \
  /var/www/html/application/<path-to-file>
```

A consuming project only ever uses `-b`. The companion `-m` flag binds a
live framework `system/` tree over the vendor copy and is meaningful only
inside the ixaya/manager framework repository itself — never in a project
that consumes it via Composer.

**Common failure modes:**
- Confusing `-b` with `-m` in a consuming project.
- Assuming the bind covers everything — `public/`, `vendor/`, and `bin/`
  are never bound, so an `index.php` or Composer change still needs a
  rebuild even with `-b` active.

## 13. Set up the testing environment

DB-backed tests run in a dedicated `testing` environment. The committed
`.env.testing` holds the profile-independent config; the gitignored
`.env.testing.priv` holds the DB block, because which host/driver applies
depends on the DB engine you chose and shouldn't live in a tracked file:

```bash
cp .env.sample.testing.priv .env.testing.priv
chmod 600 .env.testing.priv
```

Fill in `DB_NAME`/`DB_USER`/`DB_PASS` (the same values as your `local`
instance) and uncomment the block for your engine
(`DB_HOST`/`DB_PORT`/`DB_DRIVER`/`DB_CHAR_SET`/`DB_COLLATION`). Writing and
extending tests is covered in `docs/development/testing.md`.

**Common failure modes:**
- Skipping this file and instead passing DB settings as ad-hoc `-e` flags
  on the tools container. That can appear to work, but it is not the
  sanctioned path, does not persist, and every teammate re-derives it.
  `.env.testing.priv` is the one file designed for this — use it.
- Running against a schema that was never migrated. If the full `local`
  stack already ran migrations (step 9), the DB-backed tests reuse that
  same schema and need nothing extra.

## 14. Verify the tooling

Static analysis, unit tests, and code style all run through the `tools`
service (built in step 7), which mounts the project tree and carries the
exact runtime PHP and extensions — so this works even on a host with no PHP:

```bash
./docker_manage.sh -e local run --rm tools ./vendor/bin/phpunit
./docker_manage.sh -e local run --rm tools ./vendor/bin/phpstan analyse --memory-limit=512M
./docker_manage.sh -e local run --rm tools ./vendor/bin/php-cs-fixer fix --dry-run --diff
```

All three should run clean on a fresh scaffold — this is the "is the setup
actually correct" checkpoint before writing any project code. Useful PHPUnit
variants: `--testsuite <name>` for one suite, `--filter <name>` for one
class or method, and `--testdox` for readable output.

**Common failure modes:**
- `vendor/bin/*` missing — the host `vendor/` is populated by `composer
  update` in step 7. If you reached this step without it (or ran it before
  merging require-dev), re-run
  `./docker_manage.sh -e local run --rm tools composer update`.
- Single-file test runs failing to resolve a relative path: the CLI boot
  changes the working directory, so a single file must be given as an
  absolute path (for example
  `./vendor/bin/phpunit "$PWD/tests/unit/auth/LoginTest.php"`).

## 15. Optional — live probe demo

This confirms the whole stack works together — bind, real authentication,
database, and logging — not just that containers report healthy. The full
pattern is in the live-probes skill (see the routing table in `AGENTS.md`);
in outline:

1. Paste the shared probe base class once into the gitignored probes module
   under `application/modules/probes/controllers/api/`.
2. Write a small probe controller extending it.
3. Confirm the bind is live by grepping a marker string inside the
   container.
4. Log in through the project's auth endpoint with the step 10 credentials
   to obtain a real API key.
5. Call the probe with the key (expect success) and without it (expect
   `401`/`403`).
6. Check all three log channels — in-process capture, container stderr, and
   the application log — since a probe can return the right value while
   still emitting a silent warning.

**Common failure modes:**
- Bypassing auth on the probe to make testing easier — that defeats the
  purpose; a bypassing probe can pass while the real authenticated path is
  broken.
- Letting the probes module leak into a build context — confirm it is
  excluded in `.dockerignore` (the scaffold pre-configures this).

## 16. Git hygiene checkpoint

Before the first commit, confirm these are ignored. The scaffold
pre-configures them, so this is a sanity check, not manual setup:

- `docker/env/*` and `docker/secrets/*`, except the `sample.*` templates
- `.env.*`, except `.env.sample*` and `.env.testing`
- `application/modules/probes/`
- `.claude/` (skill symlinks and any local agent state)
- `docs/workspace/` (temporary working files)

**Common failure modes:**
- A teammate's or CI's fresh clone missing an instance's env files
  (expected — they are gitignored) and needing to redo steps 5, 6, and 13
  for their own `local` instance. That is normal, not a setup bug.

---

## Where to go next

The stack is running and verified. From here, the project's own documents
are the source of truth — day-to-day work never needs the package README:

- `AGENTS.md` (project root) — the entry point: project description,
  project-wide conventions, and the routing table mapping each development
  task to the skill that covers it.
- `docs/development/` — operational references, discoverable by listing the
  directory (the stack, testing, and more). Filenames say what each
  contains.
- `docs/architecture/` — how the application is organized, including how
  configuration reaches the running process.

The linked skills under `.claude/skills/` load the framework's conventions
into a coding agent as each task comes up; `AGENTS.md` says which applies
when.
