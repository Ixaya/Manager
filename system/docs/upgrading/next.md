# Upgrading — unreleased

Changes between 2.x releases that alter behavior a project already depends on.
Everything else in a minor release is additive.

### `api_only = false` now renders real HTML error pages — and is properly gated

Previously, setting `MGR_Exceptions::$api_only` to `false` had two problems:
the HTML branch called CI's `show_exception()`/`show_error()`
unconditionally, with no `should_disclose_details()` gate at all — a
production request could leak full exception class/file/line to any client
sending `Accept: text/html` — and `sample/` shipped no
`application/views/errors/` tree, so the include itself failed (an empty
body in production, a PHP warning dump in development).

Both are fixed: the HTML branch now suppresses detail exactly like the JSON
branch already did, and `sample/application/views/errors/html/` ships the
five templates CI's error renderer expects.

**Nothing changes for an existing project on `composer update`** — the new
view templates live in `sample/`, which only bootstraps a brand-new
project. If you already set `$api_only = false` (or want to now), copy
`sample/application/views/errors/html/` into your own
`application/views/errors/` — without it, the HTML branch still fails to
render, just with the safer disclosure gate now in effect underneath it.
