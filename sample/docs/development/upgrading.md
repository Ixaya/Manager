# Upgrading — reconciling a new framework release

> Scope: moving to a newer 2.x release of `ixaya/manager` via `composer
> update`, and reconciling the parts of the scaffold you copied into your
> project at setup time. For the one-time 1.x → 2.0 structural move, see
> `system/docs/upgrading/2.0.0.md` instead — that is a single migration, not
> the ongoing release loop this document covers.

## What changes on a release

The framework code itself lives entirely in `vendor/ixaya/manager` and
needs no reconciliation — `composer update` replaces it outright. What
needs your attention is the part of the shipped scaffold you copied into
your project at setup time: the `.env.sample*` and `docker/env/sample*`
templates, `docker/` compose and config files, `application/config/` and
`application/core/`, `phpunit.xml`, the top-level wrapper scripts
(`docker_manage.sh`, `bin/cli_run.sh`), and your project's own `docs/`. A
release can add, rename, or restructure any of these — reconciling them is
the point of this procedure.

Skills under `.claude/skills/` need none of this: they are symlinks
straight into `vendor/ixaya/manager/system/skills/`, so their content
updates the moment `composer update` finishes. The only follow-up is a new
skill *name* — no symlink exists yet for a skill that didn't exist before,
so re-run the skill-linking loop from `system/docs/setup.md` after a release
that adds one.

## 1. Note the current version

`composer.lock` already records the exact version you're running under your
own version control — no separate bookkeeping is needed. Read it straight from
the lock, which works with no container and no network:

```bash
grep -A2 '"name": "ixaya/manager"' composer.lock | grep '"version"'
```

`./docker_manage.sh -e local run --rm tools composer show ixaya/manager`
reports the same thing on its `versions :` line, alongside the rest of the
package metadata.

Write the result down before updating — you'll need it in "See what
changed" below. If you forget, `git log -p composer.lock` recovers it from
your own history.

If the version reads `dev-master` or `2.x-dev` rather than a number, your
constraint tracks a branch instead of a release, so your installed code has no
version number of its own. Compare the branch against the last release tag
instead — the branch name works directly as one side of the range:

```text
https://github.com/Ixaya/Manager/compare/2.1.2...master
```

That shows everything on the branch since that release, which is what you want
when tracking one. Two things follow from `master` being a moving target: the
range is only meaningful against the tag you last reconciled from, and the same
URL means something different tomorrow. To pin it, substitute the lock entry's
`"reference"` value — the commit SHA, also printed by `composer show` under
`source` — for `master`; the compare accepts a SHA on either side.

## 2. Run the update

Always through the `tools` service — never a bare host `composer`. To move
the framework and nothing else:

```bash
./docker_manage.sh -e local run --rm tools composer update ixaya/manager -W
```

A bare `composer update` also bumps every other dependency in the same run,
which mixes unrelated changes into the same reconcile. Scope it to the
framework first, verify, and update the rest separately. (`-W` lets the
framework's own requirements move with it; without it the update can be
silently refused.)

Your `composer.json` constraint (`^2.0` in the shipped scaffold) governs how
far this can move you — a caret constraint stays inside the major version, so
a new major needs the constraint raised by hand.

Substitute your own instance name for `local` throughout if you use a
different one.

## 3. Read the shipped behavioral upgrade notes first

Every release that changes behavior a project may already depend on ships a
matching file at `vendor/ixaya/manager/system/docs/upgrading/<version>.md` —
written for exactly this reconcile, with the grep to find affected call sites
and a **Verify** checklist per change baked in. A purely additive release
carries no file at all; its absence for a version you're crossing means
there was nothing behavioral to flag for that release, not that a file is
missing.

List what shipped between the version you noted in step 1 and the one you
just landed on:

```bash
ls vendor/ixaya/manager/system/docs/upgrading/
```

Read every `<version>.md` whose version falls in that range (inclusive of
the version you updated to), in order. If your constraint tracks
`master`/a `dev-*` branch rather than a tagged release, this mechanism stops
at the last tag — the unreleased `next.md` in the repo is a live draft that
can still be revised or reverted before it tags, so it's deliberately left
out here; fall back to the compare/commit-message reading in step 1 and step
4 for anything past the last tag.

This is the fast path to the gotchas that actually bite in production — a
client parsing a response shape that no longer exists, a read that now
returns `null` where it used to return `[]`, a column type that silently
double-widened on one engine. It covers only the framework's own behavior,
though, not the scaffold: bringing your copied `sample/` files forward
(`.env` templates, `docker/`, `application/config` and `core`, your own
`docs/`) still needs the compare view below. These notes don't replace that
step — they front-load the part of the reconcile that's cheapest to get
wrong and, being plain files already in `vendor/`, need no network access to
read.

## 4. See what changed — the GitHub compare is the primary path

Open the compare between the version you noted in "Note the current
version" above and the version now in `composer.lock`, old first — the
repository is public, so it loads without signing in:

```text
https://github.com/Ixaya/Manager/compare/2.1.1...2.1.2
```

Three dots, old on the left. Reversing the order renders every change
inverted — additions look like deletions — which is easy to miss and
completely misleading.

A local two-way diff of your project against the new scaffold cannot tell
"the framework changed this" apart from "I customized this" — both show up
as plain differences. The compare view supplies the missing upstream axis
(old scaffold vs. new scaffold), which turns the reconcile into a real
three-way comparison: the compare says what the framework changed and
*why*, your own file says what adopting a hunk would overwrite. The *why*
lives in the **commit messages**, not the diff.

### Which paths in the compare are yours

The compare shows the whole framework repository. Only `sample/` holds files
you own a copy of, and **`sample/` maps to your project root** — not to a
`sample/` directory inside your project:

| Path in the compare | Your action |
|---|---|
| `sample/<path>` | Reconcile as your own `<path>` |
| Anything outside `sample/` | None — your project keeps no copy of it |

So `sample/application/config/config.php` in the compare is your
`application/config/config.php`, and `sample/docs/development/docker.md` is
your `docs/development/docker.md`.

Watch the repository root in particular: it carries its own `AGENTS.md`,
`README.md`, and `framework/docs/`, which are the framework's, not yours —
yours are the same-named files under `sample/`. If a path does not start with
`sample/`, it is not yours, whatever it is named.

**Enumerate the changed `sample/` paths with the `grep` below, every time —
don't rely on eyeballing the rendered compare or on handing the compare to
an LLM-summarization fetch tool to list them for you.** This isn't gated on
how many versions you're crossing: a single release can rename or
restructure enough files (a docs reshuffle, a scaffold reorg) to produce a
diff just as large and misleading as a multi-version jump. Size and
rename-density are what matter, not hop count. Append `.diff` (or `.patch`)
to the compare URL for the raw unified diff, which also sidesteps the
renderer entirely if the file-by-file view is slow or fails outright with
"this comparison is taking too long to generate":

```text
https://github.com/Ixaya/Manager/compare/2.1.1...2.1.2.diff
```

The suffix goes on the end of the whole range, after the new version. Pull
just your own paths out of it:

```bash
curl -s https://github.com/Ixaya/Manager/compare/2.1.1...2.1.2.diff \
  | grep '^diff --git' | grep ' b/sample/'
```

This is exact string matching on diff header lines — it can't miss or
fabricate an entry the way a model summarizing the whole diff can, and it
works the same whether the diff spans one release or ten. A fetch tool that
converts the page to markdown and hands it to a summarizing model can
silently drop or invent entries once the diff is large enough — it may
report "no `sample/` changes" when there are several. Reserve a fetch tool
for prose (commit messages, release notes) where missing a detail is
low-stakes; never for a file list you intend to act on. The rendered
compare view still earns its place for reading *why* a change was made —
the commit messages — just not for compiling the path list.

Read the grep output as `a/<old path> b/<new path>`: when the two differ the
file was **renamed**, which is the case most easily mistaken for a deletion
plus an unrelated new file. A template renamed upstream means your copy
keeps the old name and silently stops matching the scaffold.

`https://github.com/Ixaya/Manager/releases` also carries notes per version
(open the specific tag, e.g. `releases/tag/2.1.2`, rather than paging
through the index). These cover only what the release is about, not a full
diff or every commit, so they're not a step to run on every upgrade — check
them on a bigger jump, or when you suspect a change affects behavior and
want the short version before reading commit messages.

## 5. Offline fallback — no network access

An installed copy has no `.git` (Composer ships a dist archive), so a local
`git diff` against the old release is not possible. What it does ship is the
whole scaffold at `vendor/ixaya/manager/sample`, so you can compare your
project against the freshly-updated copy.

Do **not** blanket-diff your project root against it. Your root also holds
`vendor/` itself, `application/logs/`, caches, and — the reason this matters —
`.env`, `.env.priv`, `docker/env/*`, and `docker/secrets/*`, so the output
buries the real changes in noise and puts secrets on your screen and into
anything you paste. Walk the reconcile targets instead:

```bash
V=vendor/ixaya/manager/sample
for p in .env.sample .env.sample.prod application/config application/core \
         bin docker/docker-compose.yml docker/php docker/nginx docker/cron \
         docker/valkey docker/env/sample.env docker/env/sample.docker.env \
         docker/env/sample.priv.env docker_manage.sh phpunit.xml \
         phpstan.neon docs; do
    [ -e "$V/$p" ] || { echo "GONE UPSTREAM: $p"; continue; }
    diff -rq "$p" "$V/$p" >/dev/null 2>&1 || echo "CHANGED:      $p"
done
```

Then `diff -ru <path> "$V/<path>"` on each reported path to see the hunks.
Note this compares against the **new** scaffold only, so it shows your
customizations and the upstream changes mixed together with no way to tell
them apart — that is exactly the axis the compare view supplies. Use this
only when the compare is unreachable, never as a routine replacement.

## 6. Reconcile per file — adopt, adapt, or keep local

Go through the changed paths one at a time and decide, per file:

- **Adopt** — take the new version outright; nothing here was customized.
- **Adapt** — merge the upstream change into your customized copy by hand.
- **Keep local** — the file diverged on purpose; the upstream change
  doesn't apply here.

Record any deliberate divergence somewhere your team will see it again —
a comment at the point of divergence, or your project's own decision log.

This is never a script to run unattended: any shipped file may have been
customized, so every hunk is a decision for you to make, not one to
automate away.

## 7. Re-run migrations and the quality gates

A release can ship new framework migrations. Check what is pending *before*
running anything — `plan` only reports, and there is no down-migration, so an
applied migration is not undone by rolling the package version back:

```bash
./docker_manage.sh -e local run --rm cli -c "bash /var/www/html/bin/cli_run.sh manager/tools/plan"
```

If it reports pending migrations, back the database up, then apply them:

```bash
./docker_manage.sh -e local run --rm cli -c "bash /var/www/html/bin/cli_run.sh manager/tools/migrate"
```

Then the quality gates:

```bash
./docker_manage.sh -e local run --rm tools ./vendor/bin/phpunit
./docker_manage.sh -e local run --rm tools ./vendor/bin/phpstan analyse --memory-limit=512M
./docker_manage.sh -e local run --rm tools ./vendor/bin/php-cs-fixer fix --dry-run --diff
```

A clean run is the same "did this actually work" checkpoint as the initial
setup's tooling verification. `--dry-run` on the fixer is deliberate: an
upgrade should not silently reformat your code in the same pass, so review
what it reports before dropping the flag.
