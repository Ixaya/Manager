---
name: ixaya-live-probes
description: Use when live-testing a code change end-to-end against the running Docker stack — writing a throwaway REST probe controller, verifying auth/DB/session behavior at runtime, or checking that a fix actually executes (not just reads) correctly. Teaches the gitignored test-module probe pattern, the authenticated-not-bypassed rule, the Docker run recipe, and the three log channels that catch silent errors.
---

# Ixaya Live Probes (runtime verification via the test module)

> **Prerequisite:** this skill assumes `ixaya-code-style` is loaded — invoke it
> before writing any code (probe controllers are code too). It owns naming,
> typing, PHPDoc, and the comments policy; this skill only covers runtime probing.

Reading a diff confirms it looks right; it doesn't confirm it executes right
against a real DB, a real authenticated request, or the live session driver.
Not every change needs a live probe — a string/comment fix is confirmed by a
grep. Probe when **behavior** changed: comparison semantics, type/cast
changes, anything touching auth, DB, session state, or cross-engine SQL.

## Pick your mode first

- **Project mode** — you are in a consuming project, testing application
  code. Bind the app tree only: `-b`/`--bind`. Framework code comes from the
  baked `vendor/ixaya/manager` — you never edit it here.
- **Framework mode** — you are in the `ixaya/manager` repo itself (the repo
  whose `system/` you are editing), testing framework code through the
  bundled `sample/`. Add `-m`/`--manager-bind` on top of `-b` so the stack
  reads the live `system/` tree. **`-m` applies to this repo only** — in a
  consuming project it has nothing to bind.

Everything else below is identical in both modes.

## Where probes live

Write a throwaway REST controller under the gitignored test module
(`application/modules/test/controllers/api/`; in the framework repo that is
`sample/application/modules/test/...`). Never under the app proper, never
committed — the module ships to no one.

- **Controller name = the task/section being verified** (e.g. `Auth_security`
  for a security pass, `Billing_sync` for a billing fix).
- **Method name = the item** (`f1_get()`, `item12_get()`) — one probe per
  finding so any item re-tests in isolation. An `all_get()` aggregator is
  fine, but split out any check that can fatal hard (`show_error()`/`exit()`,
  not a catchable exception) into its own endpoint — one fatal must not block
  the rest.
- Probes may mix **static checks** (read live source with
  `file_get_contents`/`preg_match`, reflect classes) and **runtime checks**
  (real DB queries, real library calls) in one controller — they answer
  different questions; use both.
- **Leave the controller in place afterward.** It's gitignored and cheap to
  re-run next time the same area needs re-validating. Don't delete it as
  "cleanup".

### Before writing any probe: check the .dockerignore guard

The Dockerfile does `COPY application/` and the build context is governed by
`.dockerignore`, not git. Without an exclusion line, every image built from
the tree (production included) ships the probes. Verify and self-repair
before writing probes:

```bash
grep -qx 'application/modules/test/' .dockerignore || cat >> .dockerignore <<'EOF'

# Local-only probe/test module (gitignored) — must never enter a build context
application/modules/test/
EOF
```

(Run from the directory holding the `.dockerignore` — the project root; in
the framework repo, `sample/`.) The line must come after the `!application/`
allow rule — last match wins in dockerignore.

## Test authenticated, not bypassed

Do **not** set `$this->methods['*']['auth_override'] = 'none'` on a probe.
That skips the exact machinery (`MGR_Rest_Controller`'s key check,
`process_api_user()`, `_remap()` gating) every real endpoint runs through — a
bypassing probe can pass while the authenticated path is broken. Instead:

1. Log in through the app's normal auth endpoint to get a real `X-API-KEY`.
2. Call the probe with that header.
3. A keyless hit must get the framework's normal `401`/`403` — if it
   doesn't, the controller is still bypassing auth somewhere.

This is also the only way to observe request-scoped state that exists only
after real auth (`$logged_in_level`, timezone side effects of
`process_api_user()`).

## Probe base class

Every probe controller extends a shared base pasted once per repo as
`application/modules/test/controllers/api/Test_probe.php` (gitignored).
The full class (auth-safe REST base, E_ALL capture, DB helpers, assert
utilities) is in `references/probe-base-class.md` beside this file — read
it the first time you probe in a repo, or when the base is missing.

## Running the stack

Reuse an existing instance if present (`ls docker/env/*.env` inside
`sample/` or the project root; instance env files are gitignored — on a
fresh clone create one from the `sample.*` templates first). Read values
from the instance env files — don't hardcode ports/hosts. Don't rebuild
unless `composer.json`/`.lock` changed; bind mounts cover live PHP source.

```bash
# project mode: ./docker_manage.sh -e <i> -b --profile <db> up -d
# framework mode (manager repo only — adds the system/ bind):
./docker_manage.sh -e <i> -b -m --profile <db> up -d
./docker_manage.sh -e <i> exec php bash /var/www/html/bin/cli_run.sh manager/tools/migrate
source docker/env/<i>.agent.env      # AGENT_BASE_URL / _USERNAME / _PASSWORD
KEY=$(curl -s -X POST "$AGENT_BASE_URL/auth/api/login" \
  -d "username=$AGENT_USERNAME&password=$AGENT_PASSWORD" \
  | python3 -c "import sys,json;print(json.load(sys.stdin)['api_key'])")
curl -s -H "X-Api-Key: $KEY" "$AGENT_BASE_URL/test/api/<controller>/<item>"
curl -s -o /dev/null -w "%{http_code}\n" "$AGENT_BASE_URL/test/api/<controller>/<item>"  # keyless must 401/403
./docker_manage.sh -e <i> -b [-m] --profile <db> down -v   # include EVERY --profile used
```

Confirm the bind took before trusting any result: grep an edited symbol in
the container (`docker exec <i>-php-1 grep -n "<symbol>" /var/www/html/...`)
so you know the running code is your tree, not a baked image. Bring up the
real profile the change touches (e.g. `--profile postgres` for a
Postgres-specific fix).

**Timing:** live-test once per batch, after all fixes in it are written up —
not per item mid-batch; the stack has real bring-up overhead. Keep the stack
up across items in the same session; tear down (`down -v`, every profile
flag) at the end.

## Check the logs, not just the response

A probe returning the right value can still emit a silent
warning/notice/deprecation. Three channels, they don't overlap:

- **In-process** (`capture_errors()` above) — the only one that sees what
  the app's `error_reporting` masks, notably `E_DEPRECATED`.
- **Container stderr** — `docker logs <i>-php-1` (PHP `error_log`). Echo a
  boundary marker to stderr first to scope it.
- **CI app log** — `/var/log/manager/app/` in-container. Empty = no
  error-level entries.

**All channels empty but the request still 500s?** The failure precedes
logger init — no amount of re-checking these channels will show it. Use the
silent-fatal wrapper in `references/silent-fatal-probe.md`; if the trace has
the `... on false` DB signature, run `manager/tools/env_check` first.

## If something unexpected surfaces mid-test

Stop and flag it with a proposed correction — don't live-debug it into the
current fix and don't silently patch it. A probe failing for a reason
unrelated to the item under test is usually a new finding that deserves its
own decision.
