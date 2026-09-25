---
name: mgr-live-probes
description: Use when live-testing a code change end-to-end against the running Docker stack — writing a throwaway REST probe controller, verifying auth/DB/session behavior at runtime, or checking that a fix actually executes (not just reads) correctly — in this codebase. Teaches the gitignored probes-module pattern, the authenticated-not-bypassed rule, and the probe base class's error capture — instead of trusting a diff/read-through to confirm a fix works.
---

# Manager Live Probes (runtime verification via the probes module)

> **Prerequisite:** this skill assumes `mgr-code-style` is loaded — invoke it
> before writing any code (probe controllers are code too). It owns naming,
> typing, PHPDoc, and the comments policy. The mgr-docker-ops skill owns
> running and debugging the stack itself (bind flags, exec's default user,
> log channels); this skill only covers what's specific to probes.

Reading a diff confirms it looks right; it doesn't confirm it executes right
against a real DB, a real authenticated request, or the live session driver.
Not every change needs a live probe — a string/comment fix is confirmed by a
grep. Probe when **behavior** changed: comparison semantics, type/cast
changes, anything touching auth, DB, session state, or cross-engine SQL.

**Probe vs. test.** A probe is disposable evidence for one investigation; once
a check should run every time, unconditionally, it belongs in the permanent
suite instead — an **integration** suite against a real DB, not mocks.
Conventions and base classes: `docs/development/testing.md`.

Source of truth (only read if something here is insufficient):
- `references/probe-base-class.md` — the shared probe base class (auth-safe
  REST base, error capture, DB helpers)
- `references/silent-fatal-probe.md` — wrapper for failures that precede
  logger init
- `docs/development/testing.md` — probe-vs-test boundary, integration-suite
  conventions
- `.dockerignore` (project root, or `sample/` in the framework repo) — the
  guard line that keeps the probes module out of build contexts

## Pick your mode first

- **Project mode** — testing application code in a consuming project: bind
  the app tree only (`-b`); framework code is the baked
  `vendor/ixaya/manager`, never edited here.
- **Framework mode** — in the `ixaya/manager` repo itself, testing framework
  code through the bundled `sample/`: add `-m` on top of `-b` so the stack
  reads the live `system/` tree. `-m` has nothing to bind in a project.

Everything else below is identical in both modes.

## Where probes live

Write a throwaway REST controller under the gitignored probes module
(`application/modules/probes/controllers/api/`; in the framework repo that is
`sample/application/modules/probes/...`). Never under the app proper, never
committed — the module ships to no one.

- **Controller name = the task/section being verified** (e.g. `Auth_security`
  for a security pass, `Billing_sync` for a billing fix).
- **Method name = the specific check** (`lockout_get()`, `soft_delete_get()`)
  — one probe per change so each re-tests in isolation. An `all_get()`
  aggregator is fine, but split out any check that can fatal hard
  (`show_error()`/`exit()`, not a catchable exception) into its own endpoint —
  one fatal must not block the rest.
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
the tree (production included) ships the probes. Verify and self-repair before
writing probes:

```bash
grep -qx 'application/modules/probes/' .dockerignore || cat >> .dockerignore <<'EOF'

# Local-only probes module (gitignored) — must never enter a build context
application/modules/probes/
EOF
```

(Run from the directory holding the `.dockerignore` — the project root; in the
framework repo, `sample/`.) The line must come after the `!application/` allow
rule — last match wins in dockerignore.

## Test authenticated, not bypassed

Do **not** set `$this->methods['*']['auth_override'] = 'none'` on a probe.
That skips the exact machinery (`MGR_Rest_Controller`'s key check,
`process_api_user()`, `_remap()` gating) every real endpoint runs through — a
bypassing probe can pass while the authenticated path is broken. Instead:

1. Log in through the app's normal auth endpoint to get a real `X-API-KEY`.
2. Call the probe with that header.
3. A keyless hit must get the framework's normal `401`/`403` — if it doesn't,
   the controller is still bypassing auth somewhere.

This is also the only way to observe request-scoped state that exists only
after real auth (`$logged_in_level`, timezone side effects of
`process_api_user()`).

## Testing a shipped-off seam

A class-property switch (like `$api_only`) has no per-request toggle and
defaults off in every shipped project. To test the "on" behavior, flip it
temporarily in the tracked subclass that carries it (e.g.
`application/core/MY_Exceptions.php`) with a comment marking it temporary,
run the probe, then revert and confirm `git diff` shows nothing — a leftover
flip ships the wrong default to every project that copies the file.

## Benchmark/comparison probes — provenance is part of the result

A probe comparing implementations, drivers, or configurations (a benchmark, a
type/behavior matrix) whose numbers will be cited about the framework must
drive the framework's own API — the Model layer, a REST endpoint, a CLI
command — never a raw client call (`new PDO`, `pg_connect`, `mysqli_connect`),
which measures the client library instead. A raw call is fine for isolating
*why* a framework number looks the way it does, but in its own labeled
section, never the same table. State which layer produced every number.

## Probe base class

Every probe controller extends a shared base pasted once per repo as
`application/modules/probes/controllers/api/Test_probe.php` (gitignored). The
full class (auth-safe REST base, E_ALL capture, DB helpers, assert utilities)
is in `references/probe-base-class.md` beside this file — read it the first
time you probe in a repo, or when the base is missing. Every controller file
that extends it also needs its own `require_once __DIR__ .
'/Test_probe.php';` — MX modules don't share an autoload path across
controller files in the same module, so a probe controller that only
`extends Test_probe` without requiring the file first fails with `Class
"Test_probe" not found`.

## Mutating a loaded library's internal state

A probe that corrupts a library's internals for negative testing (a bad API
key, a broken config value) does it on a **fresh instance**
(`new Sendgrid_lib()`), never the loaded singleton (`$this->sendgrid_lib`):
`$this->load->library()` shares one object per request, so the corruption
silently leaks into every later probe or `all_get()` branch.

## Running the stack

Bringing the stack up/down, `-b`/`-m` bind flags, confirming a bind actually
took, `exec`'s default user, and the three log channels/`log_check` recovery
recipe are all the mgr-docker-ops skill — load it before running anything.
What's specific to probes:

```bash
./docker_manage.sh -e <instance> exec php bash /var/www/html/bin/cli_run.sh manager/tools/migrate
source docker/env/<instance>.agent.env      # AGENT_BASE_URL / _USERNAME / _PASSWORD
KEY=$(curl -s -X POST "$AGENT_BASE_URL/auth/api/login" \
  -d "username=$AGENT_USERNAME&password=$AGENT_PASSWORD" \
  | python3 -c "import sys,json;print(json.load(sys.stdin)['response']['api_key'])")
curl -s -H "X-API-KEY: $KEY" "$AGENT_BASE_URL/probes/api/<controller>/<item>"
curl -s -o /dev/null -w "%{http_code}\n" "$AGENT_BASE_URL/probes/api/<controller>/<item>"  # keyless must 401/403
./docker_manage.sh -e <instance> -b [-m] --profile <db> down -v   # include EVERY --profile used
```

**Timing:** live-test once per group of changes, after they're all written —
not after each one; the stack has real bring-up overhead. Keep the stack up
while you work through them; tear down (`down -v`, every profile flag) at the
end.

Of the three log channels (mgr-docker-ops), the probe base's
`capture_errors()` is the one only a probe has — it alone sees what
`error_reporting` masks, notably `E_DEPRECATED`. For a 500 with every
channel empty, use `references/silent-fatal-probe.md`; if its trace shows
the `... on false` DB signature, run `manager/tools/env_check` first.

## If something unexpected surfaces mid-test

Stop and flag it with a proposed correction — don't live-debug it into the
current fix and don't silently patch it. A probe failing for a reason
unrelated to the change under test is usually a separate problem that deserves
its own decision.

## Anti-patterns

```php
// WRONG — bypasses the exact machinery every real endpoint runs through
$this->methods['*']['auth_override'] = 'none';

// RIGHT — log in for a real X-API-KEY, call the probe with it,
// and confirm a keyless hit still gets 401/403
```
