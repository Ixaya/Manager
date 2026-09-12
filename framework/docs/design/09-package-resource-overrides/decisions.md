# Package resource override precedence — decisions

## Precedence: the package registers as a fallback, not an overlay

`MGR_Loader::add_package_path()` inserts the package path before
`BASEPATH` in `_ci_library_paths`/`_ci_helper_paths` (so it still beats the
CI3 core, but no longer beats the application), and appends it in
`_ci_model_paths`/`_ci_view_paths`. `_config_paths` is left untouched —
its array order was already correct for two of its three consumers
(`MX_Config::path_env()`, `CI_Loader::_ci_init_library()`); only
`CI_Config::load()`'s merge direction was wrong, so the config fix lives in
the merge, not the array.

**Removal follows registration.** `CI_Loader::remove_package_path()`'s
no-argument branch assumed the old package-first order (`array_shift`s
index 0). Under the new order that would silently drop the application
path and re-invert the fix while leaving the package registered.
`MGR_Loader::remove_package_path()` translates a no-argument call into the
explicit-path form and delegates to the parent, reimplementing no removal
logic. Zero callers exist in this repository — the override exists for
consuming projects.

## Per-item override for the three collection-shaped types

Config, helpers, and language are collections of named items, not single
artifacts — whole-file shadowing makes a project own every item it didn't
intend to change (`auth_lang.php` is 106 keys). All three now load every
layer and let the project win per item:

- **Helpers** — `function_exists()` is first-wins, so `APPPATH` must
  iterate *first*. Prerequisite: every package helper function had to be
  wrapped in `if (! function_exists(...))` first (63 functions across ten
  files, ~17 already guarded) — otherwise loading both layers can
  `Cannot redeclare`.
- **Language** — array assignment is last-wins, so `APPPATH` iterates
  *last*. The package's own `japanese`/`spanish` translations of CI3's own
  language files must still override `BASEPATH` — that base layer loads
  unconditionally first, only the project's position relative to the
  package moved.
- **Config** — also last-wins, `APPPATH` iterates last. Relitigated twice
  after the initial merge landed (see below); final shape is sequential
  inclusion, not a recursive merge.

Libraries, models, and views are single artifacts with nothing to merge;
their override route is subclassing (libraries/models) or whole-file
replacement (views) — see framework-wiring.md's route table.

## Config's shape: three relitigations, one final answer

1. **Widened from `load()` to `load()`+`read()`.** `config_read('ion_auth')`
   was found to be a second, independent route into the same
   package/application precedence question — single-file-wins, never
   merged — carrying nested keys (`$config['tables']['users']`, etc.) that
   a shallow merge would half-clobber. Both entry points now share one
   cascade.
2. **MX composition, not replacement.** The first cut copied `MX_Config`'s
   `load()` body into `MGR_Config`, which would have silently deleted
   MX's module-scoped config lookup for every consuming project (dead code
   in this repo today, but live, documented MX behavior). Fixed by adding a
   `load_fallback()` seam to `MX_Config`/`MX_Lang`/`MX_Loader` — each parent
   now calls its own fallback method instead of `parent::load()` directly,
   and only the fallback is overridden. Same pattern for `MGR_Loader::helper_fallback()`.
3. **Sequential inclusion replaces the recursive merge.** Each cascade layer
   was originally read into its own scope and combined via an
   associative/list merge heuristic. Replaced with including every layer,
   in order, into one accumulating `$config` scope — ordinary PHP semantics
   (`$config['tables']['users'] = 'x'` edits one leaf and keeps siblings;
   `unset()` removes a package key, which the merge could not; a whole
   sub-array reassignment is literal, matching what a developer would
   expect). One mental model now covers every config file: *your file runs
   after the framework's, against the same variables* — the same relationship
   `ENVIRONMENT/` copies already have to their base.

**Rejected alternatives** (config): package-config shims under
`system/package/config/` (a three-line include of a base moved to
`system/config/`), and a header-comment convention showing the
include-then-override code. Both rest on whole-file shadowing, which fails
open for a from-scratch one-key project file (every other key vanishes)
and freezes a legacy full copy from ever receiving new package keys. Spun
out as its own proposal instead — see `00-shared/proposals.md`,
`config-include-bases` — since it's the right shape for the five
`config->path()`-read files (`lib_*`, `mimes`) the loader's merge
structurally can't reach, not for the 19 merged array files.

**Rejected direction** (mechanism, campaign-wide): a unified
locations-based resolver modeled on `Modules::find()`. Would mean
reimplementing `_ci_load_library()`, `model()`, `helper()`, `_ci_load()`,
and `Lang::load()` (stock branch, `MY_` handling, config discovery, subdir
retry) to arrive at behavior the targeted overrides already produce, then
tracking all five against upstream indefinitely.

## `MY_` is not the package-override mechanism

Raised and withdrawn mid-campaign, not parked: `MY_`-prefixed subclassing
is CI3's *stock*-library/core-class mechanism
(`_ci_load_stock_library()`/`load_class()`, `APPPATH`-only), unrelated to
package-shipped resources. `MY_<helper>`'s hard error on a package helper
is correspondingly left as deliberate, documented behavior — the wrong
shape failing loud beats it failing silent. The one supported route for a
package helper is the same filename under `application/helpers/` plus
`function_exists` guards.

## Package resource name collisions — considered, not fixed

The package loads its own resources by name (`load->model('domain')`,
`load->model('theme')`, `load->library('format')`, etc.). Post-fix, a
project resource that happens to share one of those names — never
intended as an override — now answers the framework's own call from
inside package code. Renaming the package's resources to remove the
hazard was considered and rejected: existing external projects have long
depended on the unprefixed names, and a rename is a breaking public-API
change with no clean migration path for them. Mitigated instead by naming
the shadowable resources explicitly in the upgrade note
(`system/docs/upgrading/next.md`) rather than by a code change.
