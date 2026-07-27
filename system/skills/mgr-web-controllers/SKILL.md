---
name: mgr-web-controllers
description: Use when creating or editing a web page controller, loading views, or working with themes/layouts in this codebase. Teaches the MY_Controller / MGR_Controller conventions of the ixaya/manager framework — controller-based theming ($_container/$_theme/$_layout), layout resolution, load_view(), domain-driven themes — instead of vanilla CI3 $this->load->view() calls.
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
- `application/core/MY_Controller.php` — project shim (`extends
  MGR_Controller`); project-level overrides and shared defaults go here
- Package `Domain` / `Theme` models
  (`vendor/ixaya/manager/system/package/models/`) — per-domain theming

## Hierarchy

```
CI_Controller
└── MY_Controller extends MGR_Controller     (web pages: theming, views,
    │                                         language, optional session)
    └── APP_Rest_Controller extends MGR_Rest_Controller   (see mgr-rest-controller)
```

Web page controllers extend `MY_Controller`. API controllers extend
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
share a theme, put them in a shared base controller. Legacy base controllers
of that pattern (`Site_Controller`, `Admin_Controller`, `Private_Controller` —
session-based site/admin/private page controllers) are not part of the
scaffold and are not shipped in the Composer package. They live in the
`ixaya/manager` source repository under `extras/`
(`extras/site_cms/application/core/`, `extras/backend/application/core/`),
which is excluded from the package — browse or clone the repository at
`github.com/Ixaya/Manager` to port them if the project needs them.

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
