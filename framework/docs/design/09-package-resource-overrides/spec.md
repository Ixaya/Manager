# Package resource override precedence

Every resource under `$autoload['packages'] = [MGRPATH . 'package']` —
`system/package/{config,libraries,models,helpers,views,language}/` — was
unconditionally winning over a same-named project override, for all six
resource types.

## Root cause

`CI_Loader::add_package_path()` registers the package path as a
**higher-priority overlay**: unshifted ahead of `APPPATH` in
`_ci_library_paths`/`_ci_helper_paths`/`_ci_model_paths`, prepended in
`_ci_view_paths`. First-match search loops (`_ci_load_library()`, `model()`,
`helper()`, `_ci_load()`) then find the package copy before the
application's. `_config_paths` appends the package path instead
(`[APPPATH, package]`, already the correct polarity for two of its three
consumers), but `CI_Config::load()`'s `array_merge()` walks that array
last-wins, so the package's later-loaded copy always clobbers the
project's. Same root cause, one polarity error, six manifestations — not
four independent bugs. (Views and language were missed on the first pass
and share the identical shape.)

A project file at the documented override path — same filename under
`application/{libraries,models,views,helpers,language}/` or
`application/config/` — is CI3's own convention, not a workaround, so this
was a correctness defect, not a documentation gap.

## What was built

- `MGR_Loader::add_package_path()` / `remove_package_path()` — the package
  now registers as a **fallback**, not an overlay (see decisions.md).
- Per-item cascades for the three collection-shaped types (config, helpers,
  language): a project file declares only what it changes, instead of
  shadowing every item in the package's file.
- The seven package models that carried implementation directly were split
  into `system/models/MGR_<Name>.php` + a 9-line package shim, so every
  package model follows the same subclass-override convention as
  libraries.
- `Format`, `Seeder`, `Rest_key_model` — the three package classes with no
  `MGR_` base — vendored into `system/third_party/` with new `MGR_` bases,
  closing the one remaining whole-file-copy-only override route.

## Out of scope, confirmed correct

HMVC module resolution (`Modules::$locations` / `Modules::find()`) already
resolves project-then-package via an explicit ordered locations list —
untouched by this work.

## Full route table and rationale

The per-kind override route table lives in
`framework/docs/architecture/framework-wiring.md` ("Package resource
override precedence") — read there, not duplicated here. Decision
rationale, including three relitigated config shapes and the rejected
alternatives, is in `decisions.md` beside this file.
