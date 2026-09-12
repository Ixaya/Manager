# RestServer-derived third-party code

Two files under `system/third_party/RestServer/` derive from
`chriskacerguis/codeigniter-restserver` — the same upstream `REST_Controller`
itself comes from (`system/third_party/REST_Controller.php`), but tracked in
their own directory since the model shim and library shim each need their own
`system/{libraries,models}/MGR_<Name>.php` layer, per the standard alias
chain (see AGENTS.md's `system/third_party/` hard rule).

| File | Class | Chain |
|---|---|---|
| `system/third_party/RestServer/Format.php` | `RS_Format` | → `system/libraries/MGR_Format.php` → `system/package/libraries/Format.php` (`Format`) → project subclass |
| `system/third_party/RestServer/Rest_key_model.php` | `RS_Rest_key_model extends MY_Model` | → `system/models/MGR_Rest_key_model.php` → `system/package/models/Rest_key_model.php` (`Rest_key_model`) → project subclass |

Both `MGR_` layers are empty-body shims today — nothing Ixaya-specific has
been layered on either one, so the seam exists ahead of the first thing that
needs it, same reasoning as the model-shim work's empty `Attachment`/`Domain`
shims.

## `RS_Format`

Verbatim move of `Format` (Phil Sturgeon, Chris Kacerguis, @softwarespot,
DBAD license), loaded by `REST_Controller::response_via_format()`
(`REST_Controller.php:406`, `$this->load->library('format')`). Carries one
local edit that travels with any future re-vendoring:
`/** @phpstan-consistent-constructor */` above the class — required because
`factory()` calls `new static(...)`.

## `RS_Rest_key_model`

**Not a clean split, unlike `BE_Ion_auth_model`.** Upstream never shipped a
Keys *model* — only a Keys *controller* — so there is no pristine upstream
shape to keep separate from Ixaya's own additions. The conversion (credited
to `ho <ixaya.com>` in the file's own docblock) rewrote every method against
`MY_Model`'s multi-connection API (`check_connect()`, `$this->my_db`,
`$connection_name`), which is itself an Ixaya extension of CI3, not vanilla
Active Record. Splitting it into a `CI_Model`-based upstream layer plus an
`MGR_` layer re-implementing the same methods against `my_db` would double
every method body for no real separation. `RS_Rest_key_model extends
MY_Model` directly instead — precedented in this repo already by
`REST_Controller extends MY_Controller` (`system/third_party/REST_Controller.php:17`),
another third-party file coupling straight to the project-facing `MY_` base
rather than the CI3 stock class.

**This doc is attribution, not a merge checklist.** `auth-upstream.md` exists
because Ion Auth has a live upstream to periodically re-merge from.
`codeigniter-restserver`'s Keys controller has no model equivalent to ever
pull a new version of, so there is nothing to diff against on a future
upstream bump — this record's only job is to preserve provenance
(Phil Sturgeon, Chris Kacerguis, MIT license) and explain why the file sits
on `MY_Model` instead of `CI_Model`.
