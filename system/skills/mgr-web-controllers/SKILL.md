---
name: mgr-web-controllers
description: Use when creating or editing a web page controller, loading views, working with themes/layouts, or rendering HTML (rather than JSON) error pages in this codebase. Teaches the MY_Controller / MGR_Controller conventions of the ixaya/manager framework — controller-based theming ($_container/$_theme/$_layout), layout resolution, load_view(), domain-driven themes, the $api_only error-rendering switch — instead of vanilla CI3 $this->load->view() calls.
---

# Manager Web Controllers (MY_Controller / MGR_Controller)

> **Prerequisite:** this skill assumes `mgr-code-style` is loaded — invoke it
> before writing any code. It owns naming, typing, PHPDoc, and the comments
> policy; this skill only covers web controllers, views, and theming.

Every web page controller extends `MY_Controller` (alias of `MGR_Controller`,
which extends `CI_Controller`). Theming is configured per controller through
properties, not in a config file, and views render through `load_view()` — a
direct `$this->load->view()` call bypasses layout and theme resolution
entirely. API endpoints are not web controllers; they extend
`APP_Rest_Controller` under different conventions.

Source of truth (only read if something here is insufficient):
- `vendor/ixaya/manager/system/core/MGR/Controller.php` — the implementation:
  theming properties, `resolve_layout()`, `load_view()`, `resolve_theme()`,
  session/language loading
- `vendor/ixaya/manager/system/core/MGR_Site_Controller.php` — the opt-in
  dispatch guard (see "HTML error pages" below)
- `application/core/MY_Controller.php` — project shim (`extends
  MGR_Controller`); project-level overrides and shared defaults go here
- Package `Domain` / `Theme` models
  (`vendor/ixaya/manager/system/package/models/`) — per-domain theming

## Hierarchy

```
CI_Controller
└── MGR_Controller                            (theming, views, language,
    │                                          optional session)
    ├── MY_Controller extends MGR_Controller   (plain web pages)
    └── MGR_Site_Controller extends MGR_Controller  (opt-in dispatch guard)
        └── APP_Site_Controller extends MGR_Site_Controller  (worked example)

REST_Controller
└── MGR_Rest_Controller
    └── APP_Rest_Controller extends MGR_Rest_Controller   (see mgr-rest-controller)
```

Web page controllers extend `MY_Controller` — or `APP_Site_Controller` (or a
project's own subclass of `MGR_Site_Controller`) for the dispatch-guard
behavior in "HTML error pages" below. API controllers extend
`APP_Rest_Controller` — different skill, different conventions.

## Controller-based theming

Theming is configured per controller (not in a config file). Set the
properties before/in the constructor:

| Property | Effect |
|---|---|
| `$_container` | First layout path segment (e.g. a module or site area) |
| `$_theme` | Second segment; also exposed to views as `$module` |
| `$_layout` | Layout view name; defaults to `layout` |
| `$session_enabled` | `true` loads the session library (+ flashdata kind default) |
| `$language_enabled` | `true` loads language file(s) — `$language_file` name(s), defaults to the lowercased class name; `?language=` / `$_SESSION['language']` switch it |

`resolve_layout()` builds the layout path as `{container}/{theme}/{layout}`
(each segment optional). The constructor resolves it once into
`$_layout_path`.

For one-off controllers set the properties directly; when several controllers
share a theme, put them in a shared base controller.
`application/core/APP_Site_Controller.php` is a worked example shipped with
the scaffold — a minimal base controller with no theme, session, or CMS
state, just `$_container` set before `parent::__construct()`. Copy that
pattern for a project's own base controller(s); see
`docs/development/frontend-theming.md`
for adding a real theme (assets, a `$_theme` value, header/footer content) on
top of it. `Admin_Controller` and `Private_Controller` (session-based
admin/private page controllers, gated on an Ion Auth group) have no shipped
example — the `ixaya/manager` source repository's `extras/` tree
(framework repo only, not shipped) has a shape to port, at
`extras/backend/application/core/Admin_Controller.php` and
`extras/site_cms/application/core/Private_Controller.php` respectively,
keeping in mind `extras/` predates this framework's current conventions and
is a shape to port, not a style to copy.

## Loading views

```php
$this->load_view($page, $data);          // renders $page inside the resolved layout
$this->load_clean_view($page, $data);    // same, but with the `layout_clean` layout
$this->load_view($page, $data, $layout_path); // explicit layout override (bare name
                                              // is re-resolved through {container}/{theme}/)
```

The layout view receives `$data` plus `$page` (the content view to render) and
`$module` (the theme). Don't call `$this->load->view()` directly in page
controllers — that bypasses layout/theme resolution.

`json_response($data)` emits a JSON body and dies. Existing pages use it for
small AJAX endpoints — leave those alone, but don't write new ones. A new JSON
endpoint belongs in `APP_Rest_Controller` however small it is (see
mgr-rest-controller).

File uploads from web controllers: use the built-in proxies
(`$this->upload_file()`, `$this->upload_image()`) — see mgr-helpers-libraries
(`upload_lib`).

## Domain-driven theming

Call `resolve_theme()` (typically from a base controller's constructor) to
theme by request host: it looks up `$_SERVER['HTTP_HOST']` in the `Domain`
model, follows `redirect_url` if set, stores `$_domain_id` /
`$domain_client_id`, and overrides `$_theme` from the domain's `Theme` row.

## HTML error pages

`MGR_Exceptions::$api_only` (default `true`) forces every error response to
JSON, even for a browser request. Setting `$api_only = false` in a project's
`MY_Exceptions` renders CI's HTML error views instead for a request whose
`Accept` header contains `text/html`: `application/views/errors/html/`
ships the templates (`error_404`, `error_general`, `error_exception`,
`error_php`, `error_db`). Suppressed 5xx (`should_disclose_details()` false —
production, `display_errors` off) still renders a generic `error_general`
page, never the real detail; 404 is always shown, same as the JSON path.

**An uncaught exception thrown from a plain `MY_Controller` action still
renders nothing at all in that same suppressed configuration** — neither
the generic page above nor anything else, HTTP 500 with an empty body
(still logged). CI3's own top-level exception handler gates the call to
`show_exception()` on `display_errors` before `MGR_Exceptions` ever runs,
and unlike a REST controller (whose dispatch catches the exception
directly — see mgr-rest-controller), a plain web controller has no
equivalent guard.

`MGR_Site_Controller` is that guard, opt-in: extend `APP_Site_Controller`
(or a project's own subclass of `MGR_Site_Controller`) instead of
`MY_Controller` directly to get it. It wraps dispatch in `try`/`catch
(\Throwable)` and calls `MGR_Exceptions::show_exception()` itself,
bypassing CI3's gate the same way `MGR_Rest_Controller` does — a suppressed
exception then renders the same generic `error_general` page a suppressed
`show_error()` already does (requires `$api_only = false`, above), not an
empty body. **Never on `MGR_Controller` or via any project-wide handler** —
this stays scoped to controllers that opt in by extending
`MGR_Site_Controller`. Two things that stay true regardless: a constructor
exception (thrown before `_remap()` ever runs) still isn't covered, and a
project controller that defines its own `_remap()` on top of
`APP_Site_Controller` silently loses this guard — CI3 dispatches only the
nearest `_remap()` in the chain.

## Anti-patterns

```php
// WRONG — bypasses layout and theme resolution
$this->load->view('admin/dashboard', $data);

// WRONG — layout path hand-assembled instead of resolved
$this->load->view($this->_container . '/' . $this->_theme . '/layout', $data);

// RIGHT — properties declare the theme, load_view() resolves the layout
class Dashboard extends MY_Controller
{
    protected ?string $_container = 'admin';
    protected ?string $_theme     = 'default';

    public function index(): void
    {
        $this->load_view('dashboard', ['title' => 'Dashboard']);
    }
}
```
