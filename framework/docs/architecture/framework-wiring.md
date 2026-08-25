# How the framework attaches, boots, and resolves classes

> Scope: how a project attaches `ixaya/manager` (`MGRPATH`, the package
> autoload, module locations, the optional bootstrap hook), how
> `system/third_party/MX/` loads once CodeIgniter boots, and how a
> `MGR_*` class resolves to the project's own `MY_`/`APP_` subclass.

How a project attaches `ixaya/manager` (`MGRPATH`, the package autoload,
module locations, the optional bootstrap hook); how `system/third_party/MX/`
gets loaded once CodeIgniter boots and why `MGR_Config` mirrors itself into
the global `$CFG`; and how a `MGR_*` class resolves to the project's own
`MY_`/`APP_` subclass.

## How a project attaches the framework

`public/index.php` is copied once from `sample/public/` and defines two
constants before CodeIgniter boots, each validated with a 503 exit if the
directory doesn't resolve:

- `MGRPATH` — the absolute path to `vendor/ixaya/manager/system/`. Every
  require, autoload entry, and config include below resolves through this
  constant; nothing in a project may hardcode a framework path of its own.
- `APPMGRPATH` — the same location expressed relative to `APPPATH`, because
  MX's module and hook loaders take paths relative to `APPPATH`/
  `APPPATH/controllers/`, not absolute ones.

Three project config files pull the package in from there:

- `application/config/autoload.php` — `$autoload['packages'] =
  [MGRPATH . 'package']`, so everything under `system/package/` (helpers,
  libraries, models, config) autoloads exactly like a project's own
  `application/` tree.
- `application/config/config.php` — `$config['modules_locations']` adds
  `MGRPATH . 'package/modules/' => '../' . APPMGRPATH . 'package/modules/'`,
  so MX's `Modules::autoload()`/`Modules::find()` resolve `manager/*` module
  controllers (`system/package/modules/manager/`) the same way they resolve
  `application/modules/*`.
- `application/config/constants.php` and `application/config/hooks.php` —
  each `include`s the framework's own copy (`MGRPATH . 'config/constants.php'`
  / `.../hooks.php`) if it exists, folding framework-defined constants
  (`EXIT_*` codes) and any hook registrations the framework ships in
  alongside the project's own.

Optional: `MGR_Bootstrap` (`system/hooks/MGR_Bootstrap.php`) is a
`pre_controller` hook, shipped commented out in `hooks.php`, that
force-loads `MGR_*` libraries before any controller runs — for the rare case
where a library is needed from inside a base controller constructor or
another hook, where lazy-loading via `$this->load->library()` would be too
late.

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

## Class resolution: `MGR_*` → package alias → `MY_`/`APP_`

A `MGR_*` class resolves to the class a project actually instantiates
through one of two shapes, depending on whether it's a core CI class or a
library.

**Core classes** (`Model`, `Controller`, `Rest_Controller`, `Model_Dyn`) go
straight from the framework class to the project's subclass, with no
intermediate alias file. CI3's own `subclass_prefix` mechanism
(`$config['subclass_prefix'] = 'MY_'`, set in `application/config/config.php`)
is what makes this work: CI loads `MY_Model`/`MY_Controller` by name, and
those files simply `extend` the `MGR_*` base directly —
`MY_Model extends MGR_Model` (`system/core/MGR/Model.php`),
`MY_Controller extends MGR_Controller`, `APP_Rest_Controller extends
MGR_Rest_Controller`, `APP_Model_Dyn extends MGR_Model_Dyn`. All four
project-side files live in `application/core/`.

**Libraries** add one more link: `MGR_X_lib` (the real code, in
`system/libraries/`) is extended by an unprefixed alias `X_lib` in
`system/package/libraries/` — a thin shim (`class Jwt_lib extends
MGR_Jwt_lib {}`) that autoloads via the package path
(`$autoload['packages'] = [MGRPATH . 'package']`) so CI resolves `X_lib` by
its bare name. A project that needs to customize further extends that
unprefixed alias with its own `MY_X_lib`, the same `subclass_prefix`
mechanism as the core classes. The auth stack is the fullest instance of
this shape: `BE_Ion_auth` (upstream-tracked fork) → `MGR_Ion_auth` (library
subclass, the code) → `Ion_auth` (unprefixed package alias) → a project's
own subclass, if it has one.

Either way, the chain is why `AGENTS.md`'s "never break the alias chain"
rule exists: renaming a public method or changing a signature on any
`MGR_*` class breaks every project subclass sitting on top of it, and
nothing in the resolution mechanism itself would catch that at build time.

## How MGR_Model composes optional capabilities via traits

Not every method on `MGR_Model` is a first-class citizen of the class body.
Methods every model uses (`get`/`update`/`delete`/`get_all*` and their
internal helpers) stay declared directly on the class; a specialized
capability group that most models never touch — for example the `sync_*`
family, for importing from an external source — lives in its own PHP trait
instead, so reading `Model.php` top to bottom shows what a typical model
actually uses.

A group earns its own trait when it is a coherent capability a model either
wants entirely or not at all, and it is big enough that its absence makes
`Model.php` meaningfully easier to read. Splitting one method out, or
splitting by "these feel related", buys a file and costs a lookup. When in
doubt it stays on the class — this is a readability seam, not a design
boundary, and nothing about the framework depends on where a method lives.

Mechanics:

- Each capability trait is its own file under `system/core/MGR/Model/`
  (e.g. `Model/Sync.php` → `trait MGR_Model_Sync`), carrying the same
  `BASEPATH` guard as every other framework file.
- `Model.php` pulls a trait in with an explicit
  `require MGRPATH . 'core/MGR/Model/Sync.php';` directly below its own
  `BASEPATH` guard, then composes it with `use MGR_Model_Sync;` as the
  first line of the class body. The explicit `require` is necessary:
  nothing scans `system/core/MGR/` or its subdirectories the way
  `MY_Model.php`'s single `require MGRPATH . "core/MGR/Model.php";` pulls
  in `MGR_Model` itself — a trait file left unrequired is simply never
  loaded.
- A composed method is indistinguishable at runtime from one declared
  directly on `MGR_Model`: `$this`, protected properties, and calls to
  other `MGR_Model` methods all resolve normally from inside a trait, so a
  capability's own `MgrDriver::match()`-style per-engine branching can live
  entirely inside its trait, self-contained.

Overriding from a project needs no trait-aware syntax. A trait's methods are
compiled into the class that `use`s it, so from a project's
`MY_Model`/`APP_Model_Dyn` subclass a trait-provided method behaves exactly
like one declared on `MGR_Model` — redeclare it to override, and `parent::`
still reaches the trait's implementation. The alias-chain rule is unaffected:
the public surface a subclass sees is identical either way, which also means
moving a method into a trait is not a breaking change, and moving one back is
not either.

The cost is a lookup: a method absent from `Model.php` means checking the
class's `use` list and following into `Model/<Trait>.php`.

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
manager-added method (`path()`, `read()`).

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
