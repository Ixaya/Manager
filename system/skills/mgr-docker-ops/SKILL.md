---
name: mgr-docker-ops
description: Use before running any docker/docker compose command, bringing the stack up or down, exec'ing into a container, running a manager/tools CLI command, or debugging a container-side permission/logging problem — not only while writing live probes — in this codebase. Teaches docker_manage.sh's instance/profile/bind-flag model, bind-mount verification, and the three log channels plus the log_check recovery recipe — instead of hand-rolling docker/docker compose calls that skip required env wiring or leave root-owned files behind.
---

# Manager Docker Operations

> **Prerequisite:** this skill assumes `mgr-code-style` is loaded — invoke it
> before editing `docker_manage.sh` or any compose/env file. This skill only
> covers running and debugging the stack, not writing probe controllers (see
> mgr-live-probes for that) or CLI controllers (see mgr-cli-modules).

`docker_manage.sh` is the single entrypoint for every Docker Compose
operation against this stack — it wires the two per-instance env files
compose needs, the secrets mounts, and the `-b`/`-m` bind flags. A raw
`docker compose`/`docker exec` invocation skips all of that **silently, not
loudly** — the container just keeps running whatever it already had, which
is how a session ends up debugging code that was never actually bound.

Source of truth (only read if something here is insufficient):
- `docker_manage.sh` (project root, or `sample/` in the framework repo) —
  the wrapper itself; its header comment documents every flag
- `docs/development/` (in the framework repo, `sample/docs/development/`):
  `docker.md` — the deeper reference this skill summarizes (instance
  bootstrap, engine/profile matrix, "Live-code dev modes", the "Silent 500
  with empty logs" ladder); `docker-internals.md` — env var placement, for
  editing files under `docker/`; `docker-tuning.md` — memory caps,
  per-engine sizing, OOM diagnosis; `docker-server.md` — server readiness,
  the bundled PostgreSQL tier, backups and restore

## When to use the script vs. a raw `docker` command

**Through `docker_manage.sh -e <instance> ...` — no exceptions:** anything
that starts, stops, builds, execs into, or runs a command inside a
compose-managed service (`up`, `down`, `build`, `exec`, `run`). The wrapper's
checks (required env/secrets files, bind-dir existence) are exactly what
catches a misconfigured instance before it does something confusing — never
run bare `docker compose ...` by hand "to save typing". The one narrow
exception — removing an instance the wrapper won't even let you run `down`
against — is `references/stray-instance-cleanup.md`.

**Direct `docker` command, fine as-is:** read-only inspection of an
already-running container by name — `docker ps`, `docker inspect
<container>`, `docker logs <container>`, `docker stats`. These don't need
compose's env wiring, only the container name, and constant-recreating that
wiring for a `grep`/`tail` adds nothing.

## Instances, profiles, bind flags

- `-e <instance>` selects the env files (`dev`/`local`/`framework`/… —
  gitignored, copied from the `sample.*` templates on first use).
- `--profile <db>` (`postgres`/`mysql`/`mariadb`) picks the database
  container; `--profile ws`/`--profile cron` add those services. Every db
  profile needs `docker/secrets/<instance>.db_root_password` as well as
  `db_password`. On Postgres the app's `DB_USER` is a plain database owner,
  not a superuser; admin work (roles, extensions) runs as `postgres`:
  `./docker_manage.sh -e <instance> --profile postgres exec postgres psql -U
  postgres`. Never override the `postgres` entrypoint: it guards the init,
  and after a failed first init every start refuses (`[postgres-entrypoint]
  FATAL: the init started … never finished`) — fix the cause, then `down -v`
  and `up`.
- `-b`/`--bind` mounts the app source live (project mode: your own app
  tree; framework mode: `sample/`, run from the framework repo). `-m`/
  `--manager-bind` additionally mounts the framework repo's own `system/`
  over the vendored copy — framework repo only, independent of `-b`.
- **Never assume a running instance already has the flags you need.** A
  container left running from a previous session may have been started
  without `-b`/`-m` even if you were told otherwise — confirm (next
  section) before trusting anything against it.

Full treatment — verifying `CODE_BIND_PATH`/`MANAGER_BIND_PATH` are set
correctly, what each mode actually mounts — is `docker.md`'s "Live-code dev
modes" section; the bullets above are enough for routine use.

## Never bring up the `sample` instance itself

`sample` is the env-file template other instances are copied from
(`sample.env` → `<instance>.env`, etc.) — it is never a valid `-e` target for
`up`. Starting it invites editing real values (an API key, a password)
directly into files that are still tracked in version control, one commit
away from leaking a secret. If it's already running, that's a bug from a
previous session, not a stack to build on — remove it
(`references/stray-instance-cleanup.md`) and bring up a real instance.

## A stray instance from another project name

A Compose project's identity is the `-e <instance>` name itself
(`docker compose ... -p <instance>`), so instances never share containers,
networks or volumes, even against the identical compose file. A command
under the wrong instance name never touches the right one's containers, and
a mismatched `down` exits 0 with no output — not evidence the target was
empty. Before tearing down or debugging a stack you didn't just bring up,
confirm the owner with `docker ps -a` (containers are named `<instance>-*`,
e.g. `local-php-1`).

## Running CLI/tools commands

```bash
./docker_manage.sh -e <instance> exec php bash /var/www/html/bin/cli_run.sh manager/tools/migrate
```

Full command list: `manager/tools/help`. The quality-gate commands
(`phpstan`, `php-cs-fixer`) run through the separate `tools` service instead
— not `exec` into `php`. In the framework repo specifically, running them
against this checkout instead of a lagging vendor mirror needs an extra
bind not covered here — see `framework/docs/development/framework-workflow.md`
(not shipped; no project-side equivalent).

## Bumping an image pin

Never pick a version from memory or a browsed tag page — both lag. Run
`bin/docker-pin-report.php` through the `tools` service and apply
`docker.md`'s "Updating image pins" rules (hold window, LTS-only
databases, one Alpine across images). A Postgres **major** bump (`18.x` →
`19.x`) refuses to start on existing data — the image's `pg_upgrade`
message, a restart loop: put the old pin back (the data is untouched), then
follow `docker-server.md`'s dump-and-restore procedure. Minor bumps are safe.

## Server instances: backups

Local instances are never backed up — `down -v` resets them on purpose. On
a server, `bin/db-backup.sh` dumps every running `mgr.backup=<engine>`
container on the host, so one scheduled run covers every site; never
schedule it per site. Root must run a copy that no site user can edit,
outside every project directory: whoever can edit the file root runs gets
root. `pg_restore --clean` replaces the target database — drill a restore
into a scratch database, never the live one. Setup, retention, restore and
the off-host copy: `docker-server.md`.

## `exec`/`run` into php/ws/cron/cli run as the instance's app identity — enforced, not a habit to remember

All four services start as `APP_USER:APP_GROUP` from the instance's
`docker.env` (`www-data:www-data` when unset) through one `user:` in
`docker-compose.yml`, so every `exec` and `run --rm cli -c "..."` inherits it
with no flag; nothing runs as root, FPM's master included. This matters
because a root-run command leaves root-owned files the app identity then
can't write or read — **silently**: a dropped log write, or a `500
Permission denied` the next time that path is hit.

The one root step is the one-shot `init` service: it owns the
`manager-logs` volume to that identity before the others start, and never
touches a bind mount (`MEDIA_PATH`, `PRIVATE_PATH` — the host's job). A
failing `init` (e.g. a named `APP_USER` missing from the image) leaves the
four services created but not started; `docker logs <instance>-init-1` says
why. Change the identity only via `APP_USER`/`APP_GROUP` in `docker.env`
(numeric or named, no rebuild, applied on recreate) — when, per
`docker.md`'s "Runtime identity (APP_USER/APP_GROUP)" section.

Override per command only when root is actually needed (installing a
package, inspecting a file only root can read): `exec -u root php ...` /
`run --rm -u root cli ...`. If you do, and you touched `/var/log/manager` or
the app tree, run the repair below before trusting any subsequent result.

## Confirm the bind actually took

A `-b`/`-m` flag that didn't apply (stale container, wrong instance, a typo)
fails silently. Before trusting any result against code you just edited:

```bash
docker exec <instance>-php-1 grep -n "<symbol you just edited>" /var/www/html/...
docker inspect <instance>-php-1 --format '{{json .Mounts}}'   # which paths are actually bind-mounted
```

An absence is not evidence until the channel has produced a positive — a
clean grep looks identical whether the bind took or the file was never
touched.

## An env file edit needs a container recreate, not a restart

Any value Compose loads via `env_file:` (an instance's `.env`/`.docker.env`
etc.) is fixed into the container's process environment at creation time.
Editing the file and re-issuing a request tests the OLD value — `restart`
reuses the existing container and doesn't re-read it either. Recreate the
affected service:

```bash
./docker_manage.sh -e <instance> -b -m --profile <db> up -d --force-recreate <service>
```

Confirm the new value actually reached the process before trusting a
result against it — `docker exec <instance>-php-1 printenv | grep <KEY>` —
the same "don't trust absence of an error" discipline as the bind check
above.

## Confirm which DB config a test run actually used

Switching `DB_DRIVER` for a live check touches **two** files, not one: the
instance's `<instance>.env` (what the app/CLI use) and `.env.testing.priv`
(what PHPUnit uses) — different bootstraps read them and neither stays in
sync with the other automatically. A suite that "still passes" after only
one was switched is not evidence of anything; it may have quietly run on
the old driver. Confirm the actual driver a run used from the run's own
output, not from which env file you remember editing.

## A container that restarts or drops every connection: check for an OOM kill first

Every service has a hard memory cap, and reaching it kills a process inside
that container with nothing in the app log: MySQL/MariaDB restart and run
crash recovery, Postgres resets every session, an FPM worker returns a 502.
Rule it in or out before debugging the app:

```bash
docker events --since 1h --filter event=oom          # which containers were OOM-killed
docker exec <c> cat /sys/fs/cgroup/memory.events     # oom_kill counter for this container
```

`OOM command not allowed when used memory > 'maxmemory'` in the app log is
not a kill: `valkey-state` is full, and every request that starts a session
500s. Raise `VALKEY_STATE_MAXMEMORY`, and its cap with it.

Sizing a cap and its engine settings together is `docker-tuning.md`'s
subject — never raise a database knob without raising its cap with it.
`valkey-state` peaks at ~2× its dataset during an AOF rewrite; check a
`maxmemory`/cap pairing with `bin/valkey-profile.sh -e <instance>` (host
side, throwaway copy) rather than by eye.

## Logs — three channels, and the `log_check` trap

A request can return the right value and still hide a silent error. Three
channels, they don't overlap:

- **In-process capture** — only present when running through a harness that
  wraps errors — the mgr-live-probes skill's `capture_errors()` — the only
  channel that sees what `error_reporting` masks (`E_DEPRECATED`).
- **Container stderr** — `docker logs <instance>-php-1` (PHP `error_log`).
- **CI app log** — `/var/log/manager/app/` in-container. Empty can mean
  "nothing happened" **or** "writes are being silently dropped" — CI opens
  the log with a silenced `fopen()`, so a file the app identity can't
  append to (typically root-owned, left behind by a root-run command) drops
  every entry with no symptom anywhere. Verify writes actually land, as the
  app identity, before trusting an empty log:

  ```bash
  ./docker_manage.sh -e <instance> exec php bash /var/www/html/bin/cli_run.sh manager/tools/log_check
  ```

  If it reports a failing append test, re-run `init`, which re-owns the
  volume without restarting anything else:

  ```bash
  ./docker_manage.sh -e <instance> up -d init
  ```

**All channels empty but the request still 500s?** The failure precedes
logger init — re-checking these channels won't show it. Full escalation
ladder (flip `display_errors`, then `db_debug`, then the silent-fatal
wrapper) is `docker.md`'s "Silent 500 with empty logs" section; the wrapper
itself is in the mgr-live-probes skill's `references/silent-fatal-probe.md`.

**Nothing rotates `/var/log/manager/{app,cli}` on its own** —
`manager/tools/log_prune` does, and the same command prunes the `api_log`
table (`streams=api`, off until `MGR_LOG_PRUNE_API_DELETE_AFTER_DAYS` is
set); defaults and scheduling are `docker.md`'s "Retention". Without the
`cron` profile, schedule it from the host with `exec -T php`, never
`run cli` — `docker.md`'s "Host cron calling into a Docker instance".

**Config behaves as if a value never loaded?** Don't trust `printenv` —
`.priv.env` values are invisible to it by design. Run
`manager/tools/env_check` (same `exec` pattern as above) — it reports which
source won per key, without printing values.

## Anti-patterns

```bash
# WRONG — raw docker compose, skips env files/secrets/bind-flag checks
docker compose -f docker/docker-compose.yml exec php bash

# WRONG — running a CLI command as root without a reason; leaves behind
# root-owned files the app identity can't read/write afterward
./docker_manage.sh -e local exec -u root php bash /var/www/html/bin/cli_run.sh manager/tools/migrate

# RIGHT — default user, no flag needed, either subcommand
./docker_manage.sh -e local exec php bash /var/www/html/bin/cli_run.sh manager/tools/migrate
./docker_manage.sh -e local run --rm cli -c "bash /var/www/html/bin/cli_run.sh manager/tools/migrate"
```
