---
name: mgr-cli-modules
description: Use when writing CLI commands or cron jobs, running background tasks, creating a new HMVC module, or loading models/libraries across modules in this codebase. Teaches the CLI controller pattern, async_exec_lib background dispatch, and HMVC module conventions of the ixaya/manager framework — instead of hand-rolling exec()/shell_exec() background dispatch or a plain-CI3 module layout.
---

# Manager CLI Tools & HMVC Modules

> **Prerequisite:** this skill assumes `mgr-code-style` is loaded — invoke it
> before writing any code. It owns naming, typing, PHPDoc, and the comments
> policy; this skill only covers CLI controllers, crons, and HMVC modules.

CLI commands, crons, and background jobs are all controllers, and every
feature area lives in a self-contained HMVC module. There are no standalone
PHP scripts — everything routes through the framework's single entry point,
invoked by `bin/cli_run.sh`.

Source of truth (only read if something here is insufficient):
- `vendor/ixaya/manager/system/package/modules/manager/controllers/Tools.php`
  — reference CLI controller (migrate/plan/scaffolding commands)
- `vendor/ixaya/manager/system/libraries/MGR_Async_exec_lib.php` — background
  CLI dispatch
- `vendor/ixaya/manager/system/third_party/MX/` — HMVC (Modular Extensions)
  implementation
- `vendor/ixaya/manager/README.md` — module structure overview
- Cron example: `references/cron-example.md` (incremental-sync checkpoints via
  `manager_option`)

## CLI execution model

Invoke via `bin/cli_run.sh` (wraps php with the correct binary path and
`nice`), never plain `php public/index.php`:

```bash
bin/cli_run.sh {module}/{controller}/{method} [arg1] [arg2] ...
# in the Docker stack:
./docker_manage.sh -e <instance> exec php bash /var/www/html/bin/cli_run.sh manager/tools/migrate
```

URI segments map to method arguments (`manager/tools/migrate 20240101000000
reports` → `Tools::migrate('20240101000000', 'reports')`). `CI_ENV` comes from
the environment.

## CLI-only controllers

CLI commands are plain `CI_Controller`s guarded against HTTP access. Cron jobs
(module `cron`, classes `Crons_*`) use the exact same pattern — they're
invoked by the system scheduler through the CLI:

```php
<?php

/**
 * @property Report_lib $report_lib
 * @property Manager_option $manager_option
 */
class Crons_reports extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        if (!is_cli()) {
            show_error('Direct access is not allowed. This is a command line tool, use the terminal');
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
- Guard in the constructor with `is_cli()`; refuse with `show_error(...)` —
  it routes through `MGR_Exceptions` and answers with a proper HTTP status,
  unlike a bare `exit()` string on a 200.
- Output via `echo ... . PHP_EOL` (see `Tools.php`); no views, no
  `$this->response()`.
- Add `@property` docblocks for everything loaded via `$this->load` — that's
  how PHPStan resolves CI3 magic properties.
- A command that generates a project file (a scaffold, e.g.
  `migration_file`/`model_file`) prints a `cat > ... <<'MGR_EOF'` command
  instead of writing the file itself — `application/` is read-only under
  this stack's live-code dev bind (and non-persistent without it), so only
  the developer's own host shell can create the file (see mgr-migrations).
- Migration/seed/scaffold commands already exist in `manager/tools` (see
  mgr-migrations) — don't reinvent them.
- Health/status checks: `manager/health_checks` already exists (CLI and
  `manager/api/health_checks` variants) — extend it instead of building new
  status endpoints.

## Background execution — async_exec_lib

To run something fire-and-forget (long imports, report generation, anything an
HTTP request shouldn't wait for), do NOT call `exec()`/`shell_exec()` with a
hand-built command. Use the library, which spawns a detached `php
public/index.php ...` process with proper escaping and logging:

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
└── migrations/{conn}/  # per-connection, versioned independently (see mgr-migrations)
```

The only module shipped by the framework is `manager` (CLI tools, migrations
runner, seeds, health checks, WebSocket), inside the vendor package at
`vendor/ixaya/manager/system/package/modules/manager/`. Its complete command
list — every command with its required (`<arg>`) and optional (`[arg]`)
arguments and a one-line description — is printed at runtime by
`manager/tools/help`; read that instead of relying on any doc's enumeration:

```bash
./docker_manage.sh -e <instance> exec php bash /var/www/html/bin/cli_run.sh manager/tools/help
```

Everything else under `application/modules/` is project-specific — the
inventory lives in the project's AGENTS.md module table (or a project-owned
`project-modules` skill, if the project maintains one). Never assume a module
exists beyond `manager`; check the project's table or the directory itself.

Cross-module loading — prefix with the module name:

```php
$this->load->model('admin/user');          // -> $this->user
$this->load->model(['user', 'user_key']);  // same-module (or app-level), multiple at once
$this->load->library('reports/report_lib');
Modules::run('module/controller/method', $args);  // embed another module's controller output (rare)
```

Routing: `/{module}/{controller}/{method}` resolves automatically;
`controllers/api/` adds the `api` segment
(`/{module}/api/{controller}/{method}`). Vendor-package modules (e.g.
`manager`) are registered as an extra module location in
`application/config/config.php` and are reachable the same way — the app can
shadow/extend them with a module of the same name.

When creating a new module: create only the directories you need (plus
`migrations/{conn}/` if it owns tables), follow the structures above, and add
language files per locale dir (`english`, `spanish`, `japanese`) if it has UI
text. Available locales come from `MGR_LANGUAGES` (`manager.php` config);
users switch via the framework's `language/change/{locale}` controller — don't
build a new one.

## Anti-patterns

```php
// WRONG — hand-built background execution
exec("php public/index.php reports/sync/full > /dev/null 2>&1 &");

// WRONG — CLI controller with no HTTP guard
class Crons_reports extends CI_Controller
{
    public function sync_entries($days = 7) { /* runs over HTTP too */ }
}

// RIGHT
$this->load->library('async_exec_lib');
$this->async_exec_lib->cli_run_uri('reports/sync/full');
// in the constructor:
if (!is_cli()) show_error('Direct access is not allowed. This is a command line tool, use the terminal');
```
