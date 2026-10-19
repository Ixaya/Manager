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

`MGR_Format` is an empty-body shim, so the seam exists ahead of the first
thing that needs it, same reasoning as the model-shim work's empty
`Attachment`/`Domain` shims. `MGR_Rest_key_model` carries one override: its
constructor falls back to `''` (the main handle) when `rest_database_group`
is absent, where the `RS_` file falls back to `'default'`.

## `RS_Format`

Verbatim move of `Format` (Phil Sturgeon, Chris Kacerguis, @softwarespot,
DBAD license), loaded by `REST_Controller::response_via_format()`
(`REST_Controller.php:421`, `$this->load->library('format')`). Carries one
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

## `REST_Controller` — one local edit: the `rest->db` seam

`system/third_party/REST_Controller.php` is upstream-tracked: fixes go in
`MGR_Rest_Controller`, and the file stays close to upstream so a merge stays
clean. It carries one deliberate edit, which landed in commit `113fc86`.

The constructor's block that loads `rest->db` moved, verbatim, into
`protected function _load_rest_database()`, placed right after
`early_checks()` (`REST_Controller.php:379`). The two `$this->rest->db = …`
assignments became `return …`, and a trailing `return null;` was added. The
constructor calls it at the same point as before
(`$this->rest->db = $this->_load_rest_database();`, `:525`). Nothing else
changed: no docblock, no return type, no style fix.

`MGR_Rest_Controller::_load_rest_database()` overrides it. With keys or
logging on, it returns `database_cache(rest_database_group)`, so the REST
handle is the shared cached handle (main under the package default `''`).
Otherwise it defers to the parent.

**After any upstream merge, re-verify that both the method and its call site
survived.** If either is lost, the override silently stops applying, and the
REST handle goes back to an uncached second session on every request.
Upstream's own block reads `rest_database_group` directly, so an upstream
change to it has to be carried into the method, not restored inline.
