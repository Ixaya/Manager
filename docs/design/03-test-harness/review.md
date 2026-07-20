# Test harness — validation

How the harness setup was validated (the bootstrap and wiring, not each test
body) and the runtime evidence. Runs used the `local` Docker instance
(postgres) with the live framework mounted over the vendor mirror.

## Setup validation

The bootstrap and setup are sound and internally consistent:

- **Boot sequence** — env pin, then the `argv` pin (CI3 builds the CLI URI from
  `argv`, so PHPUnit's own flags would otherwise become route segments and 404
  the boot), then the output-buffered boot, then the support bases.
- **Boot side-effect is safe** — the default CLI route,
  `manager/health_checks::index()`, only echoes; no DB, no writes. The boot
  succeeds with no test DB, and the output buffer discards the echo.
- **Paths survive the boot's `chdir`** — `index.php` `chdir()`s into `public/`
  in CLI mode, but every `require` in the bootstrap is `__DIR__`-anchored.
- **Env split verified** — `.env.testing` tracked (non-secret); `.env.testing.priv`
  confirmed gitignored (secrets never committed).
- **The Config/Lang seam is a real root-cause fix**, not a workaround, and is
  inert in normal web/CLI boots.
- **Support bases are `abstract`** (never collected as tests) and are required
  in the bootstrap before any class that extends them loads.

## Findings

1. **Example scaffolding was pre-harness cruft — actioned.** A root
   `ExampleTest.php` orphaned from every testsuite (never ran), plus a
   near-duplicate `MainTest.php`; both extended `TestCase` directly, re-implemented
   `__get`, and were `assertTrue(true)` no-ops. Replaced by one DB-free
   `CITestCase` example (see decisions).
2. **phpstan does not analyze `tests/` — open.** `phpstan.neon` `paths:` lists
   only `application/`, while the php-cs-fixer finder includes `tests/`. Test
   code is style-checked but not statically analyzed. Likely deliberate (the
   `__get` super-object magic would generate `property.notFound` noise);
   parked for a decision to align the two gates.
3. **phpunit.xml schema pinned to `13.0` while `require-dev` allows
   `^11 || ^12 || ^13` — informational.** Every attribute in use exists across
   all three majors and PHPUnit treats the schema URL as advisory; no runtime
   effect.

By design: boot failures surface opaquely under the testing env
(`display_errors=0` plus the discarded boot output); the documented workaround
is `-e APP_ENV=development`.

## Runtime verification (local, postgres)

- Full suite via the tools service with the live framework mounted:
  `OK, 55 tests / 136 assertions`, including the DB-free `Example` group.
- `php-cs-fixer --dry-run`: `0 of 61` files need fixing.
- `php -l` clean on the example file.
- phpstan not re-run for the example change: `tests/` is outside phpstan's
  analyzed paths and `application/` was untouched (see finding 2).
