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
`base64_missing_get`, and the #15 checks), re-runnable per item.
