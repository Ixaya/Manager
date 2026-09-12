# MX (Modular Extensions / HMVC): upstream-deviation record

`system/third_party/MX/` is Wiredesignz's Modular Extensions HMVC library
(v5.5, © 2015), kept in-tree per AGENTS.md's `system/third_party/` hard
rule — surgical fixes only, no style sweeps, no refactors. This file
tracks every deliberate behavioral edit made to it, so a future upstream
merge knows what to re-apply. Style-only/reformatting commits (brace
style, `array()` → `[]`, PHP-version-compat syntax swaps) are omitted.

## `Config.php`

- **`load()` rewritten** (2025, `5dce21e`) — from stock MX's
  `Modules::load_file()`-based body to an `include()` + `is_loaded`-array
  shape closer to CI3's own `CI_Config::load()`.
- **`path()` / `path_env()` / `path_module()` / `read()` / `read_path()`
  split** (2026-07, `25d2cdd` → `0cd4f00`) — `path()` and `read()` (the
  public, manager-authored API) settled onto `MGR_Config`; `path_env()`,
  `path_module()`, and `read_path()` (MX's own module-lookup internals)
  stayed on `MX_Config`, made `protected` (`14b1bc6`, previously `private`)
  so `MGR_Config` can call into them without reimplementing MX's
  module-lookup search.
- **`load_fallback()`** (2026-09, `503f22f`, `Config.php:100-103`) — a
  one-line `return parent::load(...)` that `load()`'s two
  `parent::load(...)` call sites (module empty; module file not found) now
  go through instead of calling the parent directly. Same reasoning as the
  `path()`/`read()` split above: it gives `MGR_Config` one method to
  override for its own cascade logic, without deleting MX's module-scoped
  lookup by becoming the most-derived `load()`.

## `Loader.php`

- **`database()` override removed** (2024, `a448cb2`) — MX no longer
  intercepts `database()`; calls fall through to CI3's own
  `CI_Loader::database()`.
- **`_ci_get_component()` / `_ci_load()` overrides removed** (2025,
  `5dce21e`) — both fall through to CI3's own core `Loader`
  implementations now.
- **`__get()` magic proxy removed** (2025, `08bf98a`) — unrelated to the
  still-open `__get`-in-libraries item tracked in
  `framework/docs/design/02-system-fixes/handoff.md` #4 (that one is
  about `MGR_Upload_lib`/`MGR_Attachment_lib`, a different class).
- **`helper_fallback()`** (2026-09, `503f22f`, `Loader.php:97-102`) — same
  shape as `Config.php`'s `load_fallback()`: a one-line `return
  parent::helper(...)` that `helper()`'s one `parent::helper(...)` call
  site (module lookup miss) goes through, so `MGR_Loader` can override the
  fallback alone instead of replacing `helper()` itself.

## `Modules.php`

- **Boot-time `$CFG` fallback guard** (2025, `a9c4f0a` refined by
  `751b9ff`):
  ```php
  if (! $CFG instanceof MX_Config) {
      require_once dirname(__FILE__) . '/Config.php';
      $CFG = new MX_Config();
  }
  ```
  Why it exists and how `MGR_Config::__construct()` neutralizes it is
  covered in full by `framework/docs/architecture/framework-wiring.md`'s
  "Why MX keeps its own `CI::$APP` / global `$CFG`, not `get_instance()`",
  "The PHPUnit gotcha", and "The fix" — read there, not here.

## `Lang.php`

- **`load_fallback()`** (2026-09, `503f22f`, `Lang.php:46-49`) — same
  shape as the other two: `load()`'s one `parent::load(...)` call site
  (module lookup miss) redirects here, so `MGR_Lang` overrides the
  fallback alone.

## Re-applying after an upstream MX merge

MX has no active upstream release cadence (last touched 2015), so a merge
is unlikely — if one ever happens, re-apply every edit above, in this
order:

1. `Config.php`: `image_url()`; the `load()` rewrite (or confirm the
   merged upstream already resolves module lookups compatibly); the
   `path()`/`read()` split with `path_env()`/`path_module()`/`read_path()`
   staying `protected`; then `load_fallback()`, redirecting `load()`'s
   `parent::load(...)` call sites to it.
2. `Loader.php`: confirm whether `database()`, `_ci_get_component()`, and
   `_ci_load()` still need their MX overrides removed (the merged upstream
   may have changed them too — diff before assuming); then
   `helper_fallback()`, redirecting `helper()`'s `parent::helper(...)`
   call site to it.
3. `Modules.php`: the `$CFG instanceof MX_Config` boot fallback guard.
4. `Lang.php`: `load_fallback()`, redirecting `load()`'s `parent::load(...)`
   call site to it.
5. Confirm `MGR_Config`/`MGR_Lang`/`MGR_Loader` still compile against the
   (possibly renamed) fallback method signatures.
