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

## `REST_Controller` — local edits

`system/third_party/REST_Controller.php` is upstream-tracked: fixes go in
`MGR_Rest_Controller`, and the file stays close to upstream so a merge stays
clean. It carries five deliberate edits, below: the `rest->db` seam, the
`rest_language` fallback, the dispatch failure hook, the dispatch resolver,
and the `auth_override` lookup seam. The only other difference is cosmetic: named-argument spacing
in the `_apply_cors_headers()` call.

**This record is complete from commit `954d3e0` onward** (`git diff
954d3e0^ -- system/third_party/REST_Controller.php` lists every difference).
Divergence from before that commit is unreconciled, among it a local rewrite
of `_auth_override_check()`. It gets reconciled by diffing the file against
upstream's own version at the next upstream merge.

### The `rest->db` seam

Landed in commit `3d0c173`.

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

### The `rest_language` fallback

Landed in commit `4658e74`.

In the constructor's language block, a null `rest_language` now falls back to
`$this->config->item('language')` where upstream falls back to `'english'`:

```php
$language = $this->config->item('rest_language');
if ($language === null) {
	$language = $this->config->item('language');
}
```

`config['language']` is the request language that `MGR_Controller` resolves
in its constructor (the parent call at the top of this constructor), so with
`REST_LANGUAGE` unset the REST controller's own messages follow it. The edit
has to live here: this constructor runs `$this->load->config('rest')` after
the parent call, which resets `rest_language` from the environment, so a
value written earlier by the resolver would be overwritten, and the fallback
is the only point that reads the item after that.

**After any upstream merge, re-check that the null branch still reads
`config['language']`.** If it reverts to `'english'`, REST messages stop
following the request language when `REST_LANGUAGE` is unset, with no error.
A locale in `MGR_LANGUAGES` also needs its own `rest_controller_lang.php`
(the package ships `english`, `spanish`, `japanese`); a missing one is a 500
on every REST call in that locale.

### The dispatch failure hook

Landed in commit `954d3e0`.

The body of the `catch (Exception $ex)` in `_remap()` moved verbatim into
`protected function _handle_dispatch_throwable(\Throwable $ex)`, placed right
after `_remap()`; the catch now calls it. A docblock was added, nothing else
changed. The parameter is typed `\Throwable` on purpose: PHP forbids narrowing
a parameter type in an override. `MGR_Rest_Controller` overrides it to log,
then render. The reasoning is in the error-contract design record.

**After any upstream merge, re-verify that both the method and the catch's
call to it survived.** If either is lost, an `Exception` thrown by an action
is still rendered but no longer logged, with no error.

### The dispatch resolver

`_remap()`'s inline resolution of the method to run moved into
`_resolve_controller_method(string $object_called, ?string &$segment = null, ?bool &$fallback = null): string`,
placed right before `_remap()`. It strips the format suffix (the regex
unchanged), appends the verb, and returns `index_{verb}` when that method
doesn't exist. The stripped segment and whether it fell back come out through
the two optional references. `_remap()` calls it, and shifts `$segment` onto
`$arguments` when `$fallback` is true, where upstream reassigned
`$object_called`. Dispatch and arguments are unchanged.

`MGR_Rest_Controller` calls it from its `_remap()` for the `group_methods` key
and from `_find_auth_override()` with `segment:`, so those lookups key on
exactly what `_remap()` dispatches to.

**After any upstream merge, re-verify the method and its call in `_remap()`.**
If `_remap()` goes back to inline resolution, nothing errors and dispatch
keeps working, but a later change to the strip or fallback rules then applies
to dispatch and not to the gates, and a gate can again be skipped.

### The `auth_override` lookup seam

The block in `_auth_override_check()` that finds the configured override
(`methods['*']`, `methods[<segment>]`, `methods[<segment>][<verb>]`) moved,
verbatim, into `protected function _find_auth_override()`, placed right before
`_auth_override_check()`. Its first line, `$auth_override_found = null;`, moved
with it, and the method ends with a `return`. `_auth_override_check()` now
reads `$auth_override_found = $this->_find_auth_override();` and keeps the
dispatch on the found value (`none`, `allow`, `basic`, `digest`, `session`,
`whitelist`) untouched. The surrounding function is the local rewrite that
predates the baseline above.

`MGR_Rest_Controller` overrides `_find_auth_override()` and does not call the
parent. The parent's block reads the raw `router->method`, so a format suffix
(`/x.json`) skipped the per-method entries. The override reads the same
entries against the suffix-stripped segment and adds
`methods[<controller method>]` (e.g. `index_get`) on top, later entries
winning; both keys come from the dispatch resolver above.

**After any upstream merge, re-verify the method and the call in
`_auth_override_check()`.** If either is lost, the override silently stops
applying: legacy per-method entries that tighten access under a public `'*'`
do not apply under a suffix, and `methods['x_get']` entries are ignored.

**When the deprecated `methods[<segment>]` and `methods[<segment>][<verb>]`
shapes are removed,** delete their two lines from the override. This edit stays
as a harmless default.
