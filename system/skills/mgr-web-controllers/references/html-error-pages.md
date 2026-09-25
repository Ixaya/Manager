# HTML error pages and the site dispatch guard

## Rendering HTML instead of JSON

`MGR_Exceptions::$api_only` (default `true`) forces every error response to
JSON, even for a browser request. Setting `$api_only = false` in a project's
`MY_Exceptions` renders CI's HTML error views instead for a request whose
`Accept` header contains `text/html`: `application/views/errors/html/`
ships the templates (`error_404`, `error_general`, `error_exception`,
`error_php`, `error_db`). Suppressed 5xx (`should_disclose_details()` false —
production, `display_errors` off) still renders a generic `error_general`
page, never the real detail; 404 is always shown, same as the JSON path.

## Why a plain web controller returns an empty 500

**An uncaught exception thrown from a plain `MY_Controller` action still
renders nothing at all in that same suppressed configuration** — neither
the generic page above nor anything else, HTTP 500 with an empty body
(still logged). CI3's own top-level exception handler gates the call to
`show_exception()` on `display_errors` before `MGR_Exceptions` ever runs,
and unlike a REST controller (whose dispatch catches the exception
directly — see mgr-rest-controller), a plain web controller has no
equivalent guard.

## The opt-in guard: `MGR_Site_Controller`

Extend `APP_Site_Controller` (or another subclass of it — see the
mgr-web-controllers skill's "Hierarchy" section for why never
`MGR_Site_Controller` directly) instead of `MY_Controller` directly to get
it. It wraps dispatch in `try`/`catch (\Throwable)` and calls
`MGR_Exceptions::show_exception()` itself, bypassing CI3's gate the same way
`MGR_Rest_Controller` does — a suppressed exception then renders the same
generic `error_general` page a suppressed `show_error()` already does
(requires `$api_only = false`, above), not an empty body. **Never on
`MGR_Controller` or via any project-wide handler** — this stays scoped to
controllers that opt in by extending `APP_Site_Controller` or a further
subclass of it. Two things that
stay true regardless: a constructor exception (thrown before `_remap()` ever
runs) still isn't covered, and a project controller that defines its own
`_remap()` on top of `APP_Site_Controller` silently loses this guard — CI3
dispatches only the nearest `_remap()` in the chain.
