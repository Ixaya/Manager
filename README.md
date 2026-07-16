# Manager — by [Ixaya](https://www.ixaya.com)

HMVC Code Igniter based Framework for creating backends and complete websites

## About this package

**Ixaya Manager** is a set of files, libraries, and modules that allows you to use Code Igniter to build a Backend with Login or a Complete Website if you prefer.

The framework is **always consumed as a Composer dependency**: your project bootstraps once from the included scaffold, and from then on the framework lives in `vendor/ixaya/manager` and upgrades with `composer update`. Framework code is never copied into or edited inside a project.

### Features

- CodeIgniter upgradeable through Composer (always use latest version)
- HMVC, organize your code into self-contained modules, each with its own controllers, models and views
- Modern, typed PHP (8.2+) codebase — enums, `match`, named parameters, readonly value objects
- Support for MySQL, PostgreSQL, MSSQL, Sqlite, or any database that is supported in CodeIgniter 3.
- Different Database connection/technology per Model. (you can have a model that loads a Database from Postgres and another Model that loads a Database from MySQL)
- Typed base model (`MY_Model`) with soft deletes, audit history, tenant scoping, and a dynamic query builder
- REST framework with API-key auth, group/level permissions, and content-negotiated JSON errors
- Cross-engine migration builder — one migration runs on MySQL/MariaDB, PostgreSQL, SQL Server, and SQLite
- Per-module migration versioning with plan/dry-run tooling
- Redis-augmented cache (lists, sets, hashes, pub/sub) and a WebSocket server for real-time notifications
- Responsive Theme (SB Admin 2 Template for the Backend)
- Login protected Admin module
- Examples to create a REST API
- Agent skills included (`system/skills/`) so coding agents follow the framework conventions
- Production Tested
- try { } catch { } logic for errors (an improvement over CodeIgniter's)
- Secured Application Folder from Public.

---

## How to Install

### Requirements

- PHP 8.2+
- Composer

### 1. Install via Composer

```bash
composer require ixaya/manager
```

### 2. Scaffold your project (one time)

Copy the sample application structure from the package into your project root. This is a one-time bootstrap — it copies your application's starting structure (controllers, models, views, config, and entry points), not the framework itself:

```bash
cp -r vendor/ixaya/manager/sample/. .
```

This gives you a complete working structure — controllers, models, views, config, and entry points — ready to customize.

### 3. Configure environment

```bash
cp .env.sample.dev .env
cp .env.sample.priv .env.priv
```

Open both files and fill in the required fields:

- `.env` — general settings: app name, base URL, environment, database credentials, cache, mail.
- `.env.priv` — sensitive secrets: API keys, tokens, private credentials. **Never commit this file.**

### Suggested packages

Depending on the features you need, install one or more of the following:

**Optional — core extensions:**

```bash
composer require aws/aws-sdk-php           # AWS S3, Textract, Bedrock integration
composer require phpoffice/phpspreadsheet  # Excel export/import
```

**Optional — WebSocket server:**

```bash
composer require amphp/websocket-server  # Built-in WebSocket server
composer require amphp/redis             # Redis-backed WebSocket scaling
composer require amphp/log               # Structured logging for async services
composer require adhocore/jwt            # WebSocket authentication
```

---

## Agent skills

The package ships its coding conventions as agent skills (open `SKILL.md` format, usable by any coding agent) in `system/skills/`:

| Skill | Covers |
|---|---|
| `ixaya-code-style` | Style baseline for all code and config: typing, PHPDoc, named parameters, comments |
| `ixaya-models` | `MY_Model` / `APP_Model_Dyn` — any database access |
| `ixaya-rest-controller` | API endpoints, auth, response envelope |
| `ixaya-auth` | Ion Auth stack: login/sessions, lockout, password reset, tenancy, security invariants |
| `ixaya-web-controllers` | Web page controllers, views, theming/layouts |
| `ixaya-migrations` | Schema changes (`MGR_Migration_builder`) |
| `ixaya-cli-modules` | CLI commands, crons, background exec, HMVC modules |
| `ixaya-helpers-libraries` | Utility functions, packaged libraries, creating libraries |
| `ixaya-cache-websockets` | Caching, Redis, pub/sub, websocket notifications |

Link them into your project (run from the project root; re-run after major framework updates):

```bash
for skill in vendor/ixaya/manager/system/skills/*/; do
  name=$(basename "$skill")
  rm -rf ".claude/skills/$name"
  ln -s "../../vendor/ixaya/manager/system/skills/$name" ".claude/skills/$name"
done
```

Project-wide agent instructions belong in your project's `AGENTS.md` (the cross-tool standard); tools that read `CLAUDE.md` can use a one-line `@AGENTS.md` import.

---

## PHP Validations

### PHP Static Code Analysis

Run using PHPStan:

**First time, install PHPStan:**

```bash
composer require --dev phpstan/phpstan
```

**Standard analysis:**

```bash
./vendor/bin/phpstan analyse
```

**With increased memory limit:**

```bash
./vendor/bin/phpstan analyse --memory-limit=512M
```

> **Tip:** Use the memory limit option if you encounter out-of-memory errors during analysis.

### PHP Unit Testing

Run using PHPUnit

**First time, install PHPUnit:**

```bash
composer require --dev phpunit/phpunit
```

**Run all tests:**

```bash
./vendor/bin/phpunit
```

**Run specific test file:**

```bash
./vendor/bin/phpunit application/tests/unit/ExampleTest.php
```

**Run tests with verbose output:**

```bash
./vendor/bin/phpunit --verbose
```

**Run tests in specific group/category:**

```bash
./vendor/bin/phpunit --group unit
```

> **Tip:** Use `--testdox` flag for readable test output, or `--stop-on-failure` to halt execution on the first failed test.

### PHP Code Formatting

Fix using PHP CS Fixer

**First time, install PHP CS Fixer:**

```bash
composer require --dev php-cs-fixer/shim
```

**Fix code formatting:**

```bash
./vendor/bin/php-cs-fixer fix
```

**Dry run (preview changes without applying):**

```bash
./vendor/bin/php-cs-fixer fix --dry-run
```

**Dry run with diff (preview exact changes):**

```bash
./vendor/bin/php-cs-fixer fix --dry-run --diff
```

---

## Docker Setup

The scaffold ships a complete Docker stack under `docker/`: PHP-FPM + Nginx for the website, plus Valkey (cache/sessions), and optional WebSocket, cron, and database (MySQL/MariaDB/PostgreSQL) containers behind profiles.

All operations go through the wrapper script — never `docker compose` directly, since it wires the per-instance env files and secrets the compose file needs:

```bash
# Build the images
./docker_manage.sh -e <instance> build

# Development: full stack with a local database
./docker_manage.sh -e <instance> --profile <mysql|mariadb|postgres> up -d

# Server / deployment: websocket + cron enabled, database is external/managed
./docker_manage.sh -e <instance> --profile ws --profile cron up -d

# Useful commands
./docker_manage.sh -e <instance> logs -f php
./docker_manage.sh -e <instance> exec php bash /var/www/html/bin/cli_run.sh manager/health_checks
```

`<instance>` selects the env files, secrets, and published ports, so multiple instances can run side by side. First-time setup (creating your instance's env files, secrets, and picking a database engine) is documented in the scaffold's `docs/development/docker.md`.

---

## MsgPack Support

This package can use MsgPack for faster cache and payload serialization. While the native PHP MsgPack extension (installed via `pecl` or system packages) offers the best performance, not all servers have it available.

### Install the PHP MsgPack Fallback Library

Add the pure PHP implementation to your project, along the composer patcher:

```bash
composer require rybakit/msgpack
composer require cweagans/composer-patches
```

### Apply the PHP 8.1+ Compatibility Patch

Add the following configuration to your root `composer.json`:

```json
{
  ...
    "extra": {
        "patches": {
            "rybakit/msgpack": {
                "Fix PHP 8.1 chr() deprecation": "vendor/ixaya/manager/patches/msgpack-php81-fix.patch"
            }
        }
    }
  ...
}
```

### Apply the Changes

Run the following command to install dependencies and apply patches:

```bash
composer install
```

---

## Application Structure

### Project Setup

We recommend creating a root folder named `app` and checking out the project inside it. The framework follows an HMVC (Hierarchical Model-View-Controller) architecture based on CodeIgniter.

### Root Directory

```
app/
├── composer.json
├── application/
├── public/
├── private/
├── bin/
└── patches/
```

### Public Directory

The `public/` folder contains all publicly accessible files served by the web server.

```
public/
├── index.php                    # Application entry point (all HTTP + CLI requests)
├── media/                       # User-uploaded files
└── assets/                      # Static assets organized by module
    └── {module}/
        ├── js/                  # JavaScript files
        ├── css/                 # Stylesheets
        ├── images/              # Images
        └── videos/              # Video files
```

### Application Directory

The `application/` folder contains the core application code and global resources.

```
application/
├── cache/                       # Application cache
├── config/                      # Application configuration files
├── controllers/                 # Global controllers
├── core/                        # MY_/APP_ base classes (thin aliases of the framework's MGR_ classes)
├── database/
│   ├── migrations/{connection}/ # App-level migrations (legacy history — new migrations live in their module)
│   └── seeds/                   # Database seeds
├── helpers/                     # Global helper functions
├── hooks/                       # Global hooks
├── language/                    # Global language files
├── libraries/                   # Global libraries
├── models/                      # Global models
├── modules/                     # HMVC modules (see below)
├── third_party/                 # Third-party libraries
└── views/                       # Global views
```

### Modules (HMVC Structure)

The framework uses HMVC architecture, allowing you to organize code into self-contained modules. Each module can have its own MVC structure and resources.

```
application/modules/
└── {module}/
    ├── controllers/             # Module-specific controllers + controllers/api/ for REST endpoints
    ├── models/                  # Module-specific models
    ├── migrations/{connection}/ # Module-specific migrations, versioned independently per module
    ├── views/                   # Module-specific views
    ├── libraries/               # Module-specific libraries
    ├── helpers/                 # Module-specific helpers
    ├── language/                # Module-specific language files
    └── config/                  # Module-specific configuration
```

**Benefits of HMVC:**

- **Modularity**: Each module is self-contained and reusable
- **Organization**: Better code organization for large applications
- **Separation**: Modules can be developed and tested independently
- **Scalability**: Easy to add, remove, or replace modules

**Example Module Structure:**

```
application/modules/blog/
├── controllers/
│   ├── Blog.php                 # extends MY_Controller
│   └── api/
│       └── Posts.php            # extends APP_Rest_Controller
├── models/
│   └── Post.php                 # extends MY_Model
├── libraries/
│   └── Blog_lib.php
├── migrations/default/
│   └── 20260707120000_Post.php  # extends MGR_Migration_builder
└── views/
    ├── index.php
    └── detail.php
```

### Additional Directories

- **`bin/`** - Command-line scripts and utilities
- **`private/`** - Private files not accessible via web
- **`patches/`** - Compatibility patches for dependencies
