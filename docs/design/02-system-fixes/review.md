# System fixes — validation record

## Findings validation (2026-07-11)

All 18 items checked against the code before fixing (existence, behavior,
verbatim baseline quote, verdict). Results: zero hallucinated references;
one item already fixed (#17, CORS max-age — closed by an earlier commit);
one false sub-claim (#18's redis env rename never happened); one
unsubstantiated sub-claim (#14's "Spanish comment blocks in migrations" —
none exist).

## Fix verification

- **`php -l` / phpstan / php-cs-fixer** clean on the touched files
  (phpstan operator-run; the `string|null` PHPDoc widening in
  `MGR_Attachment_lib` exists specifically to keep its null-guards
  phpstan-live).
- **#2 scaffold end-to-end:** generated migration passed `php -l` and
  migrated cleanly on a real Postgres stack (correct PK sequence, varchar
  constraint, timestamps) — the builder's cross-engine translation fired.
- **#16 `encrypt_name` inversion live-tested** via an authenticated
  multipart probe (`System_migration.php::item16_post`, gitignored test
  module, real API key, keyless hit 403'd): `encrypt=1` stores a hashed
  name, `encrypt=0` preserves the original — both directions were reversed
  before the fix.
- **`put_file` blob path live-tested:** a binary-bytes string blob stored
  and round-tripped byte-for-byte through `put_file()` +
  `get_file_base64()` — the exact call the old `array $data` hint rejected.
  Incidental fix proven in the same probe: `put_file_local` now
  mime-detects the full path (was the bare filename, always `false`).
- **#15 rename live-tested**, which is what surfaced the
  `mgr_mimes_config()` fatal (see decisions) — both helper spellings return
  identical results at runtime.
- **Closing review (2026-07-14):** every fixed item re-diffed against its
  baseline quote in an independent session — all fixed-verified, none
  regressed.

## Probe assets (keep, reuse)

`sample/application/modules/test/controllers/api/System_migration.php` —
gitignored, authenticated probes (`item16_post`, `putfile_blob_post`,
`base64_missing_get`, and the #15 checks), re-runnable per item. (Module
since renamed to `probes/`; this gitignored file no longer exists at this
path.)

## Fix verification (2026-07-29 addendum)

Found and fixed while writing a PHPUnit contract test for dyn-mode models,
then probing `MGR_Model_Dyn`/`MY_Model` live before trusting either in the
field — not part of the original #1-18 sweep.

- **`sync_commit_enabled()` / `replace()` / `get_all_or_like()` /
  `set_override()` all live-probed on Postgres** (`Dyn_join_probe`,
  `Base_model_probe`, both gitignored) — each bug reproduced, fixed, and
  re-verified against the same probe; `get_all_dynamic()`'s OR-clause fix
  additionally spot-checked on MySQL.
- **Closing re-diff (2026-07-29):** every fix re-diffed against its
  baseline in the same session — all fixed-verified, none regressed.
  phpstan/cs-fixer clean; full PHPUnit suite (86 tests) and the new
  `Models` testsuite (22 tests) green.

## Probe assets (keep, reuse) — addendum

`sample/application/modules/probes/controllers/api/Dyn_join_probe.php` and
`Base_model_probe.php` (gitignored, real API key, keyless 403s), plus the
`Dyn_probe` model/migration fixture they share — cover dyn/join edge cases
and the base `MY_Model` surface. `sample/tests/unit/models/DynModelTest.php`
+ `BaseModelTest.php` promote the confirmed regressions to permanent
PHPUnit coverage (shared fixture: `sample/tests/support/DynProbeFixture.php`).
