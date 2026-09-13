# Package resource override precedence — final state

All six resource types fixed, live-tested, and closing-reviewed clean as
of 2026-09-12. See `framework/docs/architecture/framework-wiring.md`'s
"Package resource override precedence" section for the per-kind route
table a project actually uses; this file records what shipped, not how to
use it.

## What changed, by file

- `system/core/MGR/Loader.php` — `add_package_path()` / `remove_package_path()`
  overrides (precedence); `helper_fallback()` (per-item helper cascade,
  forward iteration).
- `system/core/MGR/Config.php` — `load_fallback()` / `read()` share one
  cascade (`_cascade_paths()` + `_read_cascade()`), package layers first,
  application last, sequential inclusion into one `$config` scope.
- `system/core/MGR/Lang.php` — `load_fallback()` (per-item language
  cascade, reversed iteration so `APPPATH` lands last, `BASEPATH` base
  preserved).
- `system/third_party/MX/{Config,Lang,Loader}.php` — each gained a
  three-line `load_fallback()`/`helper_fallback()` seam so the `MGR_`
  overrides compose with MX's module-scoped lookup instead of replacing
  it. Deviation record: `framework/docs/development/mx-upstream.md`.
- `system/package/helpers/manager_*_helper.php` (10 files) — all 63
  functions wrapped in `function_exists()` guards.
- `system/models/MGR_<Name>.php` (new, 7 files) + `system/package/models/<Name>.php`
  (rewritten to 9-line shims) — `Attachment`, `Domain`, `Manager_option`,
  `Rest_key_model`, `Rest_user`, `Rest_user_group`, `Theme`. `Ion_auth_model`
  already had this shape.
- `system/third_party/{RestServer,Tettei}/` (new) + `system/libraries/MGR_{Format,Seeder}.php`
  + `system/models/MGR_Rest_key_model.php` — vendored `Format`, `Seeder`,
  `Rest_key_model`, closing the last whole-file-copy-only route.
  `Rest_key_model` sits on `MY_Model` directly (no clean upstream/Ixaya
  separation existed to split); deviation record:
  `framework/docs/development/restserver-upstream.md`.

## Validation

- **Config** — thorough: fixture battery 43/43, real-file override
  (`rest.php`, `ion_auth.php`, `production/ion_auth.php`) 14/14 across both
  directions of override, unset, and whole-sub-array reassignment, plus
  negative and removal controls.
- **Libraries, models, views** — 9/9 (declaration-wins, package-only,
  application-only, per type) via `Resource_cascade.php`.
- **Language** — `seams_get()`'s declaration-wins/package-survives checks,
  plus 28/28 on the BASEPATH-survival case (all 7 CI3-own language files ×
  japanese/spanish).
- **Helpers** — `seams_get()`'s 4 checks; guard count re-verified 63/63.
- Gates: PHPStan level 5 clean and php-cs-fixer clean at every step, full
  repo runs.

## Not carried forward from this campaign

- The `path()`-only config consumers (`lib_mailing`, `lib_jwt`,
  `lib_sendgrid`, `lib_amazon_aws`, `mimes`), `database.php`/`config.php`
  slimming, and the `path_env()` environment-copy defect — spun out as the
  `config-include-bases` proposal, resolved and archived 2026-09-13
  (`framework/docs/workspace/archive/00-proposals/config-include-bases.tar.xz`);
  permanent record split across `framework/docs/architecture/framework-wiring.md`,
  `framework/docs/design/07-database-drivers/decisions.md`,
  `framework/docs/development/mx-upstream.md`, and
  `system/docs/upgrading/next.md`.
- Package resource name collisions — considered, rejected; see
  decisions.md.
