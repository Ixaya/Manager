# Framework homologation — validation record

## `BASEPATH` guard

**The inventory grep was itself defective, and that is worth keeping.** The
original check was a bare `grep -q 'BASEPATH'` substring match, which counts a
docblock mentioning `APPPATH/BASEPATH` as a guard, and counts the model
scaffold's template string in `Tools.php` as a guard while that file had none.
Anchor on the pattern, not the word:

```bash
head -n 15 "$f" | grep -qE "defined\(\s*'BASEPATH'\s*\)\s*(or|OR)\s*exit|if\s*\(\s*!\s*defined\(\s*'BASEPATH'\s*\)\s*\)"
```

Adoption at the time of the ruling, `system/` + `sample/application` +
`extras/` (the last for the count only):

| Category | Guarded/Total | Ruling |
|---|---|---|
| Controllers | 14/30 | keep |
| Models | 13/22 | keep |
| Libraries — implementation | 13/14 | keep |
| Libraries — package alias | 1/12 | keep |
| Helpers | 10/11 | keep |
| Config | 28/30 | keep |
| Migrations | 7/15 | keep |
| Language | 37/41 | keep |
| Core / hooks | 9/10, 0/1 | keep |
| Seeds | 0/1 | **exempt** |
| Views | 10/54 | **exempt** |

The alias-library row is the one that explains how the drift spread:
`Jwt_lib.php`, named in the framework workflow doc as *the exemplar to copy
from*, was itself unguarded, so every new library copied the gap.

**Boot-path verification, by execution.** With the guard added to all three
files: `GET /` byte-identical before and after; CLI `manager/health_checks`
identical; the full scaffold PHPUnit suite `86 tests, 209 assertions, 0
failures` against Postgres with migrations applied.

**After the apply pass**, re-verified with a full-file anchored grep: no
in-scope file missing the guard, no duplicates. One regex false negative — a
probe model using a third variant, `(defined('BASEPATH')) or exit(...)` —
already guarded and untouched. Final end-to-end check: `claim_admin` → real
`POST /auth/api/login` → `GET /auth/api/profile` with the returned
`X-API-KEY`, all `200`, plus the PHPUnit suite green again.

Two mount traps cost time and are worth recording: `MANAGER_BIND_PATH` must be
the repo root, not its `system/` directory, because the compose override
appends `/system` itself; and the `tools` service is **not** covered by the
manager-bind flag, so a PHPUnit run through it needs an explicit volume
override or it silently tests the stale vendored mirror.

## Model conventions

**The corpus was swept once so it does not need re-greping.** Twelve real
models; the raw-SQL sweep returned hits only under `modules/probes/` (out of
scope), four of them commented out.

**`GREATEST` was run, not reasoned about.** Against a real SQLite engine via
native `SQLite3::query()` — byte-identical to what CI's driver executes, no
rewriting in between — both `SELECT GREATEST(0, 5-3)` and the exact query the
model built returned `false` with `no such function: GREATEST`. That moved the
finding from "portability note" to "broken endpoint", and moved the verdict
from CANNOT-VERIFY to VERIFIED.

**Engine matrix**, via real HTTP against a bound stack per engine, with a real
`api_key` and a keyless control returning `403`:

| Engine | Auth round trip | Under-max branch | Over-max branch | Both `order_by` variants |
|---|---|---|---|---|
| Postgres | pass | 1 attempt → remaining 2 | 5 attempts → remaining 0 | pass |
| MySQL | pass | same | same | pass |
| MariaDB | pass | same | same | pass |
| SQLite | n/a (no HTTP stack run) | verified directly by raw query | same | n/a |

**One defect was found by the live test, not by the fix.** Calling
`get_list()` with no explicit `order_by` returned nothing on Postgres: the
shared `mgr_build_order_by()` substitutes a literal `'id'`, and the query
joins two tables that both have one. The probe had reproduced the broken path
by hand-rolling its params — corrected to build them the way a real controller
would. The helper-level defect is recorded in `handoff.md`.

## CLI shape

Per controller, one CLI invocation that must succeed and one HTTP request that
must be refused:

- `Tools.php` — CLI `manager/tools/help` printed the command list, exit 0;
  HTTP `500` with the generic envelope (not the body-less worst case that was
  flagged as acceptable).
- `Websockets.php` — CLI guard passed, exit 0; HTTP `500`, same envelope.
- `cron/Example.php` — CLI guard passed (no "Direct access" entry in the app
  log); HTTP `500`, same envelope.
- `Health_checks.php` — HTTP `200 running`, unaffected, no code change.

Two incidentals during that run, both traced to the probe environment rather
than the change and neither pursued: the websocket link body came back empty
(pre-existing `websocket_lib` configuration), and the cron example failed
downstream on database connectivity because no DB profile was started.

## Gates

`phpstan analyse` — `[OK] No errors`. `php-cs-fixer` — 0 files changed. Both
through the Docker `tools` service; a host run is not a valid check.
