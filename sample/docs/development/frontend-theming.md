# Frontend theming — adding a real theme on top of the shipped skeleton

> Scope: turning `application/core/APP_Site_Controller.php` and
> `application/views/site/` (plain HTML, no assets) into a real themed
> frontend. For the theming contract itself ($_container/$_theme/$_layout,
> `load_view()`, domain-driven theming), see the `mgr-web-controllers` skill.

The shipped `APP_Site_Controller` sets only `$_container = 'site'` and ships a
4-file view split — `layout.php`, `header.php`, `footer.php`, `content.php` —
with no CSS, no JS, no theme segment. That's deliberate: it's a starting
point to write a basic PHP/HTML frontend against the API, not a themed
product.

## Adding `$_theme`

Set `$_theme` alongside `$_container` (before `parent::__construct()`, same
as `$_container`) to add a second layout path segment:

```php
class APP_Site_Controller extends MGR_Site_Controller
{
    public function __construct()
    {
        $this->_container = 'site';
        $this->_theme      = 'default';
        parent::__construct();
    }
}
```

Move the view files from `views/site/` to `views/site/default/` to match —
`resolve_layout()` builds the path as `{container}/{theme}/{layout}`. Several
themes under the same container just need their own `{theme}/` directory;
`APP_Site_Controller` (or a subclass) picks the one to use.

## Adding assets

There's no asset pipeline shipped — wire in whatever the project already
uses (a bundler's output, a CDN, plain static files served from
`public/`). The two integration points are `header.php` (styles, head
scripts) and `footer.php` (body-end scripts). A simple shape: an array
property per asset type on the controller, looped in the matching view —

```php
// controller
public array $_css_files = [];
public array $_js_files = [];
```

```php
// header.php — $this is the controller instance inside a loaded view
<?php foreach ($this->_css_files as $item): ?>
    <link rel="stylesheet" href="<?= $item ?>">
<?php endforeach; ?>
```

— populated per-controller (or in a shared base) before `load_view()` runs.

## Domain-driven theme selection

To pick a theme per request host instead of hard-coding it, call
`resolve_theme()` from the constructor — see the `mgr-web-controllers` skill's
"Domain-driven theming" section.
