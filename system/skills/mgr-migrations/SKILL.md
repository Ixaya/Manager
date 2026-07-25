---
name: ixaya-migrations
description: Use when creating or editing a database migration, adding/modifying tables or columns, or running/troubleshooting migrations in this codebase. Teaches the MGR_Migration_builder pattern (field(), MgrFieldType, cross-engine columns) of the ixaya/manager framework — the legacy CI_Migration/dbforge-array style is deprecated.
---

# Ixaya Migrations (MGR_Migration_builder)

> **Prerequisite:** this skill assumes `ixaya-code-style` is loaded — invoke it
> before writing any code. It owns naming, typing, PHPDoc, and the comments
> policy; this skill only covers migrations and schema changes.

New migrations extend **`MGR_Migration_builder`** and declare columns with the
typed `field()` builder. Do NOT extend `CI_Migration` with hand-written dbforge
arrays — that is the legacy style (still visible in older projects under the
root `application/database/migrations/` folder and, misleadingly, in the
template that `manager/tools/migration` scaffolds; rewrite that template
output if you use it).
The builder validates fields at construction time and translates types per DB
engine (MySQL/MariaDB, PostgreSQL, SQL Server, SQLite) automatically.

Source of truth (read for full signatures/translation tables):
- `vendor/ixaya/manager/system/libraries/MGR_Migration_builder.php` — `field()`, shorthands, index helpers, `MgrFieldType` enum, cross-engine translation matrix
- `vendor/ixaya/manager/system/libraries/MGR/Migration.php` — runner (per-module version tracking); app alias `application/libraries/MY_Migration.php`
- `vendor/ixaya/manager/system/libraries/MGR_Migration_module_lib.php` — plan/run/version API used by the CLI
- Canonical examples: `vendor/ixaya/manager/system/package/modules/manager/migrations/default/20250820111900_Attachment.php` (create table), `.../20260213175009_Ion_auth_v2.php` (modify/add/drop columns, rename, indexes)

## File placement and naming

```
application/modules/{module}/migrations/{connection}/{YmdHis}_{Name}.php  # where NEW migrations go
application/database/migrations/{connection}/...   # app-level — legacy history only, don't add here
```

`{connection}` is the DB group name (`default`, etc.). Class name is
`Migration_{Name}` with only the first word capitalized (file
`20260213175009_Ion_auth_v2.php` → `class Migration_Ion_auth_v2`). Generate a
timestamp by running `manager/tools/generate_migration_timestamp Name` via
`bin/cli_run.sh` (see "Running migrations" below for the invocation form).

## Creating a table

```php
<?php

class Migration_Attachment extends MGR_Migration_builder
{
    public function up()
    {
        $this->dbforge->add_field([
            ...$this->field_id('id'),                     // unsigned INT PK, auto_increment
            ...$this->field(name: 'title', type: MgrFieldType::VarChar, constraint: 100),
            ...$this->field(name: 'model_name', type: MgrFieldType::VarChar, constraint: 32),
            ...$this->field(name: 'model_hash', type: MgrFieldType::VarChar, constraint: 32),
            ...$this->field_timestamps(),                 // create_date (NOT NULL) + last_update (NULL)
        ]);

        $this->dbforge->add_key('id', true);              // primary key
        $this->dbforge->add_key(['model_hash', 'model_name']); // composite index
        $this->dbforge->create_table('attachment');

        // make last_update auto-update on row changes (per-engine trigger/modifier)
        $this->modify_field_timestamp('attachment');
    }

    public function down()
    {
        $this->dbforge->drop_table('attachment');
    }
}
```

If the model sets `$soft_delete = true` (see `ixaya-models`), the table needs a
`deleted` + `enabled` pair — the model filters `WHERE deleted = 0` on reads and
sets `deleted = 1, enabled = 0` on delete. There is no shorthand; declare both
explicitly as `0`/`1` flag columns:

```php
...$this->field(name: 'enabled', type: MgrFieldType::SmallInt, unsigned: true, default: 1),
...$this->field(name: 'deleted', type: MgrFieldType::SmallInt, unsigned: true, default: 0),
```

`field()` returns `[name => spec]`, so specs are **spread (`...`)** into the
dbforge array. Named parameters:

```php
$this->field(
    name: 'price', type: MgrFieldType::Decimal,
    constraint: 191,      // CHAR/VARCHAR length
    unsigned: true,       // ints/decimals only (validated)
    nullable: false,      // true = NULL, false = NOT NULL, omit = CI default
    unique: true,
    auto_increment: true, // int types only (validated)
    default: 0,           // scalar or null; omit for no DEFAULT clause
    new_name: 'new_col',  // renames — for modify_column only
    precision: 10, scale: 2,          // Decimal
    enum_values: ['active', 'inactive'], // Enum (required)
)
```

`MgrFieldType` values: `TinyInt SmallInt Int BigInt Decimal Float Double Char
VarChar Text MediumText LongText Blob MediumBlob LongBlob Bool Date Time DateTime
Timestamp Year Json Uuid Enum`. Pick the semantic type and let the builder map it
(e.g. `Json` → JSONB on Postgres, `Bool` → TINYINT(1) on MySQL / BOOLEAN on
Postgres, `Uuid` → CHAR(36) on MySQL / native UUID on Postgres). Invalid
combinations throw `InvalidArgumentException` at construction — no silent bad DDL.

Use `Bool` only for true boolean semantics (`true`/`false` values). For `0`/`1`
flag columns (`enabled`, `deleted`, …) use `SmallInt`/`TinyInt`: `Bool` maps to
Postgres `BOOLEAN`, which does **not** implicitly cast an integer `1`/`0` on
insert, so `INSERT ... enabled = 1` fails with *"column is of type boolean but
expression is of type integer"*. `SmallInt` is portable across all engines.

```php
...$this->field(name: 'is_verified', type: MgrFieldType::Bool, default: false),   // boolean semantics
...$this->field(name: 'enabled', type: MgrFieldType::SmallInt, unsigned: true, default: 1), // 0/1 flag
```

## Altering tables

Use `modify_column` to change a column's type, constraint, or default — never
drop+add an existing column. Drop+add works on an empty table but silently
loses data on a live one and obscures intent (a reader can't tell a type change
from a column removal).

```php
$this->dbforge->add_column('user', [
    ...$this->field(name: 'remember_selector', type: MgrFieldType::VarChar, constraint: 255, nullable: true, unique: true),
]);
$this->dbforge->modify_column('user', [
    ...$this->field(name: 'email', type: MgrFieldType::VarChar, constraint: 254, unique: true),
    ...$this->field(name: 'last_activity_date', type: MgrFieldType::Timestamp, nullable: true, new_name: 'last_api_date'), // rename
]);
$this->dbforge->drop_column('user', 'salt');

$this->add_index(table: 'user', columns: ['email'], unique: true);  // cross-engine, name-length safe
$this->drop_index(table: 'user', columns: ['email']);
```

`down()` must reverse `up()` (see `Ion_auth_v2.php` for a full symmetric example).

## Running migrations

Always via `bin/cli_run.sh` (wraps php with the correct binary path and
`nice`), never plain `php public/index.php`. In the Docker stack, through
`docker_manage.sh`:

```bash
./docker_manage.sh -e <instance> exec php bash /var/www/html/bin/cli_run.sh manager/tools/migrate

# the manager/tools commands (same URI args through cli_run.sh):
manager/tools/plan           # dry-run: current/latest/pending per target
manager/tools/migrate        # everything forward, all connections in $config['migration_db']
manager/tools/migrate {version} {module_key}  # single target to version — DOWNGRADES run down()!
manager/tools/version_list   # list version_list commands per target
manager/tools/version_set {version} {app|module:key} {conn}  # record version WITHOUT running (adopting existing DBs)
```

`RUN_MIGRATIONS=true` on one instance migrates on startup.

Version tracking: the application sequence lives in the `migrations` table
(single row); each module tracks independently in `migrations_path` (one row per
module key). Module keys in CLI use `:` for `/` (e.g. `manager:tools`). Targets
are auto-discovered: the app dir plus every module with a `migrations/{conn}/` dir
— including modules shipped inside the vendor package.

## Rules

- One concern per migration; never edit an applied migration — add a new one.
- Migrations run through dbforge/`$this->db` on the connection being migrated —
  don't load models inside migrations.
- Legacy files under the root `application/database/migrations/` folder are
  frozen history: never imitate them, never renumber them. New migrations live
  in their module (`application/modules/{module}/migrations/{connection}/`).
- Write engine-neutral DDL: no raw `ENUM(...)` strings, no MySQL-only column
  clauses — that's what `MgrFieldType` and the index helpers are for. Raw
  `$this->db->query()` DDL is a last resort and must handle each `MgrDriver` case
  (see `modify_field_timestamp()` in the builder for the pattern).
