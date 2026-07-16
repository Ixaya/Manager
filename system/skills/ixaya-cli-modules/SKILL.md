---
name: ixaya-cli-modules
description: Use when writing CLI commands or cron jobs, running background tasks, creating a new HMVC module, or loading models/libraries across modules in this codebase. Teaches the CLI controller pattern, async_exec_lib background dispatch, and HMVC module conventions of the ixaya/manager framework.
---

# Ixaya CLI Tools & HMVC Modules

> **Prerequisite:** this skill assumes `ixaya-code-style` is loaded — invoke it
> before writing any code. It owns naming, typing, PHPDoc, and the comments
> policy; this skill only covers CLI controllers, crons, and HMVC modules.

Source of truth (read for full signatures):
- `vendor/ixaya/manager/system/package/modules/manager/controllers/Tools.php` — reference CLI controller (migrate/plan/scaffolding commands)
- `vendor/ixaya/manager/system/libraries/MGR_Async_exec_lib.php` — background CLI dispatch
- `vendor/ixaya/manager/system/third_party/MX/` — HMVC (Modular Extensions) implementation
- `vendor/ixaya/manager/README.md` — module structure overview
- Cron example: `references/cron-example.md` (incremental-sync checkpoints via
  `manager_option`)

## CLI execution model

Everything routes through the single entry point — there are no standalone
PHP scripts. Invoke via `bin/cli_run.sh` (wraps php with the correct binary
path and `nice`), never plain `php public/index.php`:

```bash
bin/cli_run.sh {module}/{controller}/{method} [arg1] [arg2] ...
# in the Docker stack:
./docker_manage.sh -e <instance> exec php bash /var/www/html/bin/cli_run.sh manager/tools/migrate
```

URI segments map to method arguments (`manager/tools/migrate 20240101000000 reports`
→ `Tools::migrate('20240101000000', 'reports')`). `CI_ENV` comes from the environment.
Never write a new bootstrap script or call the framework outside `public/index.php`.

## CLI-only controllers

CLI commands are plain `CI_Controller`s guarded against HTTP access. Cron jobs
(module `cron`, classes `Crons_*`) use the exact same pattern — they're invoked by
the system scheduler through the CLI:

```php
<?php

/**
 * @property Report_lib $report_lib          // document loaded libs/models for PHPStan
 * @property Manager_option $manager_option
 */
class Crons_reports extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        if (!$this->input->is_cli_request()) {
            exit('Direct access is not allowed. This is a command line tool');
        }

        $this->load->library('reports/report_lib');  // cross-module load: {module}/{lib}
    }

    public function sync_entries($days = 7)
    {
        // output with echo/PHP_EOL — this runs in a terminal, not a browser
    }
}
```

Conventions:
- Guard in the constructor with `is_cli_request()`; exit with a plain message.
- Output via `echo ... . PHP_EOL` (see `Tools.php`); no views, no `$this->response()`.
- Add `@property` docblocks for everything loaded via `$this->load` — that's how
  PHPStan resolves CI3 magic properties in this repo.
- Migration/seed/scaffold commands already exist in `manager/tools` (see the
  ixaya-migrations skill) — don't reinvent them.
- Health/status checks: `manager/health_checks` already exists (CLI and
  `manager/api/health_checks` variants) — extend it instead of building new
  status endpoints.

## Background execution — async_exec_lib

To run something fire-and-forget (long imports, report generation, anything an
HTTP request shouldn't wait for), do NOT call `exec()`/`shell_exec()` with a
hand-built command. Use the library, which spawns a detached
`php public/index.php ...` process with proper escaping and logging:

```php
$this->load->library('async_exec_lib');   // load at point of use

$this->async_exec_lib->cli_run_uri('manager/tools/plan');            // run a controller URI
$this->async_exec_lib->cli_run_uri('reports/sync/full', [$client_id]);   // with args
$this->async_exec_lib->cli_run_lib('reports', 'report_lib', 'build', $id);
// ^ runs {module}'s {library}::{function}($identifier) in a background process
//   via the manager/tools/cli_exec bridge
```

## HMVC modules

Each module under `application/modules/{name}/` is self-contained:

```
{module}/
├── controllers/        # web + controllers/api/ for REST endpoints
├── models/
├── views/
├── libraries/
├── helpers/
├── language/
├── config/
└── migrations/{conn}/  # per-connection, versioned independently (see ixaya-migrations)
```

The only module shipped by the framework is `manager` (CLI tools, migrations
runner, seeds, health checks, websockets), inside the vendor package at
`vendor/ixaya/manager/system/package/modules/manager/`. Everything else under
`application/modules/` is project-specific — the inventory lives in the
project's AGENTS.md module table (or a project-owned `project-modules` skill,
if the project maintains one). Never assume a module exists beyond `manager`;
check the project's table or the directory itself.

Cross-module loading — prefix with the module name:

```php
$this->load->model('admin/user');          // -> $this->user
$this->load->model(['user', 'user_key']);  // same-module (or app-level), multiple at once
$this->load->library('reports/report_lib');
Modules::run('module/controller/method', $args);  // embed another module's controller output (rare)
```

Routing: `/{module}/{controller}/{method}` resolves automatically; `controllers/api/`
adds the `api` segment (`/{module}/api/{controller}/{method}`). Vendor-package
modules (e.g. `manager`) are registered as an extra module location in
`application/config/config.php` and are reachable the same way — the app can
shadow/extend them with a module of the same name.

When creating a new module: create only the directories you need (plus
`migrations/{conn}/` if it owns tables), follow the structures above, and add
language files per locale dir (`english`, `spanish`, `japanese`) if it has UI text.
Available locales come from `MGR_LANGUAGES` (`manager.php` config); users switch
via the framework's `language/change/{locale}` controller — don't build a new one.
