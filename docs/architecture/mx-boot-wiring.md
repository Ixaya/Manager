# MX/HMVC boot wiring

How `system/third_party/MX/` gets loaded, and why `MGR_Config` mirrors
itself into the global `$CFG`.

## Load chain

CI3's own `subclass_prefix` mechanism (`application/config/config.php`,
`$config['subclass_prefix'] = 'MY_'`) is the only thing that pulls MX code
in — there is no single entry point:

- `load_class('Config', 'core')` (CodeIgniter.php, runs first) →
  `application/core/MY_Config.php` → `MGR/Config.php` → `MX/Config.php`.
- `load_class('Router', 'core', ...)` (runs after Config) →
  `MY_Router.php` → `MGR/Router.php` → `MX/Router.php`, which itself
  `require`s `Modules.php` — this is where module autoloading
  (`spl_autoload_register('Modules::autoload')`) and `Modules::$locations`
  get set up.
- `load_class('Loader', 'core')` (called lazily from inside the real
  controller's own `__construct()`) → `MY_Loader.php` → `MGR/Loader.php` →
  `MX/Loader.php`, whose bottom-of-file guard
  (`class_exists('CI', false) or require .../Ci.php`) is what defines the
  standalone `class CI` and fires `new CI()` — the only place `CI::$APP`
  gets set in a real boot.

`MX/Base.php` and `MX/Controller.php` are **not** part of this chain and
are unreachable in this fork: `MGR_Controller` extends `CI_Controller`
directly, never `MX_Controller`, so nothing ever requires `Controller.php`
(the only file that requires `Base.php`). Both `Base.php` and `Ci.php`
declare `class CI`, but they can never collide — only `Ci.php` is ever
actually loaded.

## Why MX keeps its own `CI::$APP` / global `$CFG`, not `get_instance()`

CI3's native way to reach the super-object is
`CI_Controller::get_instance()` — but that only helps from an *instance*
method. MX's own module machinery (`Modules::load()`, `Modules::run()`,
`Modules::find()`, `Modules::autoload()`) is entirely **static**, with no
`$this` to call it from, so MX rolled its own static handle (`CI::$APP`)
and, for `Config`/`Lang` specifically, grabs the PHP globals directly via
`global $CFG, $LANG;`.

`CI::$APP` is not a second controller instance — it's a reference to the
exact same object as `CI_Controller::$instance` (assignment, not a clone).
Once a controller exists, `CI::$APP` and `get_instance()` are
interchangeable. The global-`$CFG` grab is not redundant with
`get_instance()->config`, though: `Modules.php`'s own fallback check runs
while the `Router` class is loading, before any controller exists yet —
`get_instance()` would have nothing to return at that point.

## The PHPUnit gotcha

`Modules.php`'s fallback:

```php
if (! $CFG instanceof MX_Config) {
    require_once dirname(__FILE__) . '/Config.php';
    $CFG = new MX_Config();
}
```

only exists because CI3's own bootstrap line —
`$CFG =& load_class('Config', 'core');` in `CodeIgniter.php` — has **no**
`global` keyword. In a normal top-level boot that's fine, because the
script itself runs at true global scope. But `tests/Bootstrap.php` reaches
`CodeIgniter.php` through a chain of `require`s triggered from inside
PHPUnit's own bootstrap-loading method — a function scope — so that bare
assignment lands in a local variable, not `$GLOBALS['CFG']`. `Modules.php`
and `Ci.php`, by contrast, explicitly write `global $CFG;`, which always
binds to the true global regardless of nesting. So under PHPUnit, the real
`$CFG` never got set from CI3's side, `Modules.php`'s `instanceof` check
sees an unset global, and creates a bare `MX_Config` that's missing every
manager-added method (`path()`, `path_env()`, `path_module()`, `read()`).

Confirmed empirically: instrumenting the fallback with a log line showed it
firing on every PHPUnit run and never on a normal web request or CLI
command — consistent with the scope analysis above.

In practice the blast radius was narrow (the poisoned global `$CFG` is
only ever read by `Modules.php` itself, via `->item('modules_locations')`,
which exists on plain `MX_Config` too — `get_instance()->config` and
`CI::$APP->config` resolve through CI3's own `load_class()` cache, a
function-static that isn't affected by the same scope-nesting problem, so
they stayed correctly `MY_Config` throughout). But it was real, reproducible
drift, not just a theoretical concern.

## The fix

`MGR_Config::__construct()` mirrors `$this` into the true global `$CFG` the
moment CI3 constructs it, using `global $CFG; $CFG = $this;` inside the
method body — which, unlike CodeIgniter.php's bare assignment, always binds
to `$GLOBALS['CFG']` regardless of what scope the constructor call is nested
in. By the time `Modules.php`'s check runs, `$CFG` is already the real
`MY_Config`/`MGR_Config` instance, so the fallback never fires. Verified by
re-running the same log-line instrumentation after the fix: zero fallback
hits across repeated PHPUnit runs, web requests, and CLI commands.
