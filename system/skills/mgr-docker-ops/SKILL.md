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
- `docs/development/docker.md` (project root; in the framework repo,
  `sample/docs/development/docker.md` — the root copy there is a
  maintainer pointer, not the content) — the deeper reference this skill
  summarizes: instance bootstrap, engine/profile matrix, the full
  "Live-code dev modes" bind treatment, and the "Silent 500 with empty
  logs" troubleshooting ladder
- `docs/development/docker-internals.md` (project root; in the framework
  repo, `sample/docs/development/docker-internals.md`) — env var placement
  decision tree, for anyone editing files under `docker/`, not for
  operating the stack

## When to use the script vs. a raw `docker` command

**Through `docker_manage.sh -e <instance> ...` — no exceptions:** anything
that starts, stops, builds, execs into, or runs a command inside a
compose-managed service (`up`, `down`, `build`, `exec`, `run`). The wrapper's
checks (required env/secrets files, bind-dir existence) are exactly what
catches a misconfigured instance before it does something confusing — never
run bare `docker compose ...` by hand "to save typing".

**Direct `docker` command, fine as-is:** read-only inspection of an
already-running container by name — `docker ps`, `docker inspect
<container>`, `docker logs <container>`, `docker stats`. These don't need
compose's env wiring, only the container name, and constant-recreating that
wiring for a `grep`/`tail` adds nothing.

## Instances, profiles, bind flags

- `-e <instance>` selects the env files (`dev`/`local`/`framework`/… —
  gitignored, copied from the `sample.*` templates on first use).
- `--profile <db>` (`postgres`/`mysql`/`mariadb`) picks the database
  container; `--profile ws`/`--profile cron` add those services.
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

## Running CLI/tools commands

```bash
./docker_manage.sh -e <instance> exec php bash /var/www/html/bin/cli_run.sh manager/tools/migrate
```

Full command list: `manager/tools/help`. The quality-gate commands
(`phpstan`, `php-cs-fixer`) run through the separate `tools` service instead
— not `exec` into `php`. In the framework repo specifically, running them
against this checkout instead of a lagging vendor mirror needs an extra
bind not covered here — see `docs/development/framework-workflow.md`
(framework repo only, no project-side equivalent).

## `exec` into php/ws/cron/cli runs as `www-data` — enforced, not a habit to remember

`docker_manage.sh` defaults `exec` into these four services to `-u
www-data` automatically; you don't need to type it. This exists because a
command run as root creates root-owned files (a log file, an app file) that
the app's own `www-data` process then can't write to or read — **silently**,
no error at the time, just a dropped log write or a `500 Permission denied`
the next time anyone hits that path. This has bitten this codebase's own
history more than once — that repeat is why the default moved into the
script instead of staying a line in a skill.

Override only when root is actually needed (installing a package,
inspecting a file only root can read): `exec -u root php ...`. If you do,
and you touched `/var/log/manager` or the app tree, run the repair below
before trusting any subsequent result.

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

## Logs — three channels, and the `log_check` trap

A request can return the right value and still hide a silent error. Three
channels, they don't overlap:

- **In-process capture** — only present when running through a harness that
  wraps errors — the mgr-live-probes skill's `capture_errors()` — the only
  channel that sees what `error_reporting` masks (`E_DEPRECATED`).
- **Container stderr** — `docker logs <instance>-php-1` (PHP `error_log`).
- **CI app log** — `/var/log/manager/app/` in-container. Empty can mean
  "nothing happened" **or** "writes are being silently dropped" — CI opens
  the log with a silenced `fopen()`, so a file `www-data` can't append to
  (typically root-owned, left behind by a root-run command) drops every
  entry with no symptom anywhere. Verify writes actually land, as
  `www-data`, before trusting an empty log:

  ```bash
  ./docker_manage.sh -e <instance> exec php bash /var/www/html/bin/cli_run.sh manager/tools/log_check
  ```

  If it reports a failing append test:

  ```bash
  docker exec <instance>-php-1 chown -R www-data:www-data /var/log/manager
  ```

**All channels empty but the request still 500s?** The failure precedes
logger init — re-checking these channels won't show it. Full escalation
ladder (flip `display_errors`, then `db_debug`, then the silent-fatal
wrapper) is `docker.md`'s "Silent 500 with empty logs" section; the wrapper
itself is in the mgr-live-probes skill's `references/silent-fatal-probe.md`.

**Config behaves as if a value never loaded?** Don't trust `printenv` —
`.priv.env` values are invisible to it by design. Run
`manager/tools/env_check` (same `exec` pattern as above) — it reports which
source won per key, without printing values.

## Anti-patterns

```bash
# WRONG — raw docker compose, skips env files/secrets/bind-flag checks
docker compose -f docker/docker-compose.yml exec php bash

# WRONG — running a CLI command as root without a reason; leaves behind
# root-owned files www-data can't read/write afterward
./docker_manage.sh -e local exec -u root php bash /var/www/html/bin/cli_run.sh manager/tools/migrate

# RIGHT — default user, no flag needed
./docker_manage.sh -e local exec php bash /var/www/html/bin/cli_run.sh manager/tools/migrate
```
