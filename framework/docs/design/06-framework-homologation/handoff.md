# Framework homologation — final state

## What the code does now

**The `BASEPATH` guard is required in controllers, models, libraries,
helpers, config, migrations and language files; seeds and views are exempt.**
The canonical form is the one-liner `defined('BASEPATH') or exit('No direct
script access allowed');`, placed within the first three lines, directly below
`<?php`. Stated in `AGENTS.md`'s hard rules and mirrored in `mgr-code-style`.
Every in-scope file in `system/` and `sample/application/` now carries it,
including the three boot-path files (`MGR_Bootstrap.php`, `MGR/Exceptions.php`,
`MGR_Env_lib.php`).

**`Rest_key_model` extends `MY_Model`**, runs on `$this->my_db`, and resolves
`connection_name` from `rest_database_group` with `$lazy_connect = true`.
Issuance and validation now agree by construction.

**`Login_attempt::get_list()` is cross-engine**: the `remaining_attempts`
column is a `CASE WHEN` over `mgr_env_int('AUTH_MAX_LOGIN_ATTEMPTS', 3)`
rather than `GREATEST(0, 5 - COUNT(*))`, and every entry in `$allowed_order`
is table-qualified.

**All three CLI controllers refuse an HTTP request identically** —
`is_cli()` guarded in the constructor, refused with `show_error()`, and the
shape is documented in `mgr-cli-modules`.

## Remaining open work

Two standing proposals, plus one mechanical item. Named by title — a promoted
proposal moves, and a path here would need editing back:

- **Rest-key connection coupling** — two questions the model fix left open:
  whether the API keys should follow `AUTH_DB_CONNECTION` (a credential
  belongs with the identity it authenticates) or keep their own knob (keys,
  rate limits and request logs are API plumbing, deliberately separable from
  user data — and only the first of those three is credential-shaped, so the
  answer may be to split the knob); and whether key validation should route
  through a model at all rather than raw builder calls in an upstream-tracked
  file. Both defaults resolve to the same database today, so there is no live
  gap — which is also why the divergence is easy to miss.
- **Web-controller theming consistency** — never checked whether
  `$_container`/`$_theme`/`$_layout` are set consistently across the
  non-`extras` base controllers. A dead `$_theme_kind` property had already
  been found and removed in that corner, which is what made it a candidate.
  Explicitly droppable if the sweep finds nothing.
- **23 files still use the older `if (! defined('BASEPATH')) { … }` block
  form.** They convert on touch by standing policy; there is no dedicated
  pass. Open and undone, not a re-litigation of the style choice.

## One shared-helper defect the fix did not close

`mgr_build_order_by()` (`system/package/helpers/manager_helper.php`) falls back
to the **literal string `'id'`** whenever the requested column is missing or
not in the allowed list — in all three of its branches, independently of what
`$allowed_order` actually contains. On a joined query where more than one
table has an `id`, that produces an ambiguous `ORDER BY id`, which Postgres
rejects.

`Login_attempt::get_list()` no longer trips it on the default path: its
controller passes `default_order_by: 'user.id'` and every entry in
`$allowed_order` is qualified, so an omitted `order_by` resolves to a real
column. **A client-supplied invalid one still trips it.**
`build_list_params()` cannot validate `order_by` — it does not know the
allowed list — so it passes the raw request string through, and the helper
answers "not allowed" by substituting `'id'` rather than rejecting. On this
endpoint `?order_by=<anything invalid>` therefore produces the ambiguous
`ORDER BY id` and a 500 on Postgres, for an authenticated admin, today. The
controller does check the `null` return, so it is a clean failure rather than
a wrong answer.

Note also that `build_list_params()`'s PHPDoc claims it applies defaults for
anything "missing or invalid" — true for `page`, `limit` and `order`, and not
for `order_by`. That sentence is what would persuade a reader the validation
already happened.

This belongs to the helper, not to this model, and is carried by the proposal
on rejecting invalid input rather than substituting for it.

## Recorded surprises

- **`Login_attempt::get_list()` had no caller while it was being fixed** —
  only `get_by_user()` was used, so the cross-engine defect was not reachable
  on a stock install as originally framed. It was fixed anyway as the
  canonical method a project would wire up, and a `Login_attempts` controller
  has since been added that calls it. The fix landed ahead of the endpoint
  rather than behind it.
- **A guard-detection window caused the only damage in the apply pass.** The
  mechanical worker checked only each file's first five lines for an existing
  guard, so eight probe controllers carrying an old-style guard *below* a
  header comment got a second one inserted at line 1. Cleaned up, and the
  position rule was added to the standard specifically so it cannot recur.
- **Two language files had no `<?php` opening tag at all** — a pre-existing
  content gap surfaced by the guard pass, not a guard-placement question.
