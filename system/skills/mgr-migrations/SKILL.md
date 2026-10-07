---
name: mgr-migrations
description: Use when creating or editing a database migration, adding/modifying tables or columns, changing a column's type, adding a foreign key, a primary key, or a key-prefix-length index, or running/troubleshooting migrations in this codebase. Teaches the MGR_Migration_builder pattern of the ixaya/manager framework — typed field() columns, cross-engine type mapping, and the index/key/type-change helpers that close CI3's per-engine forge gaps — instead of the legacy CI_Migration/dbforge-array style.
---

# Manager Migrations (MGR_Migration_builder)

> **Prerequisite:** this skill assumes `mgr-code-style` is loaded — invoke it
> before writing any code. It owns naming, typing, PHPDoc, and the comments
> policy; this skill only covers migrations and schema changes.

New migrations extend **`MGR_Migration_builder`** and declare columns with the
typed `field()` builder. Do NOT extend `CI_Migration` with hand-written
dbforge arrays — that is the legacy style, still visible in older projects
under the root `application/database/migrations/` folder. The builder
validates fields at construction time and translates types per DB engine
(MySQL/MariaDB, PostgreSQL, SQL Server, SQLite) automatically.

Source of truth (only read if something here is insufficient):
- `vendor/ixaya/manager/system/libraries/MGR_Migration_builder.php` —
  `field()`, shorthands, index helpers, `MgrFieldType` enum, cross-engine
  translation matrix
- `vendor/ixaya/manager/system/libraries/MGR/Migration.php` — runner
  (per-module version tracking); app alias
  `application/libraries/MY_Migration.php`
- `vendor/ixaya/manager/system/libraries/MGR_Migration_module_lib.php` —
  plan/run/version API used by the CLI
- `vendor/ixaya/manager/system/package/modules/manager/controllers/Tools.php` —
  `migration_file()`/`migration_path()`, the scaffolding + auto-versioning
  commands below
- Canonical examples:
  `vendor/ixaya/manager/system/package/modules/manager/migrations/default/20250820111900_Manager_attachment.php`
  (create table), `.../20260213175005_Manager_ion_auth_v2.php` (additive) +
  `.../20260213175009_Manager_ion_auth_v3.php` (destructive) — split so a
  database shared with an older deployment can stop at the additive one
- Primary-key surgery on a populated table (composite key, restoring
  AUTO_INCREMENT): `references/key-surgery.md`

## File placement and naming

```
application/modules/{module}/migrations/{connection}/{YmdHis}_{Name}.php  # where NEW migrations go
application/database/migrations/{connection}/...   # app-level — legacy history only, don't add here
```

`{connection}` is the DB group name (`default`, etc.). `{Name}` should be
module-qualified — `{Module}_{table}` (a `billing` module's
`invoice` table → `Billing_invoice`) — so two modules can never pick the
same migration class name. If that exact qualified name already exists
anywhere in the module's own migrations directories (any connection), append
`_v{n}` — `_v2` for the second one, `_v3` for the third, and so on; never
edit an applied migration in place (see "Rules" below).

Class name is `Migration_{Name}` with only the first word capitalized (file
`20260213175009_Manager_ion_auth_v3.php` → `class Migration_Manager_ion_auth_v3`).

`manager/tools/migration_file <name> <module> [database]` applies this rule
and prints a `cat > ... <<'MGR_EOF'` command — paste it into your host
shell, since a container cannot persist a write to `application/`. A first
version is pre-filled (id + timestamps); a later one is empty, because a
fabricated sample would succeed silently against an existing table.
`migration_path` prints just the derived name and directory as JSON. A 4th
arg `1` (`force_modification`) makes the first migration on a table with no
module-qualified history a modification (`_v2`) instead of a create.

## Creating a table

```php
<?php

class Migration_Manager_attachment extends MGR_Migration_builder
{
    public function up()
    {
        $this->dbforge->add_field([
            ...$this->field_id('id'),                     // unsigned INT PK, auto_increment
            ...$this->field(name: 'title', type: MgrFieldType::VarChar, constraint: 100),
            ...$this->field(name: 'model_name', type: MgrFieldType::VarChar, constraint: 32),
            ...$this->field(name: 'model_hash', type: MgrFieldType::VarChar, constraint: 32),
            ...$this->field(name: 'create_date', type: MgrFieldType::Timestamp, nullable: true),
            ...$this->field(name: 'last_update', type: MgrFieldType::Timestamp, nullable: true),
        ]);

        $this->dbforge->add_key('id', true);              // primary key
        $this->dbforge->add_key(['model_hash', 'model_name']); // composite index
        $this->dbforge->create_table('attachment');

        // last_update auto-updates on every UPDATE; create_date only defaults on INSERT
        $this->modify_field_timestamp(table: 'attachment', column: 'last_update');
        $this->modify_field_timestamp(table: 'attachment', column: 'create_date', on_update: false);
    }

    public function down()
    {
        $this->modify_field_timestamp(table: 'attachment', column: 'last_update', on_update: false, default: false);
        $this->dbforge->drop_table('attachment');
    }
}
```

**A `down()` that drops a table reverses every
`modify_field_timestamp(..., on_update: true)` first**, as above. On
PostgreSQL that call creates a trigger function, and `DROP TABLE` cascades the
trigger but not the function, which survives as an inert
`set_<table>_<column>()`. A column called with `on_update: false` created no
function and needs no reversal.

If the model sets `$soft_delete = true` (see mgr-models), the table needs an
`enabled` + `deleted` pair — the model filters `WHERE deleted = 0` on reads
and sets `deleted = 1, enabled = 0` on delete. There is no shorthand; declare
both as `0`/`1` flag columns (`SmallInt`, `unsigned`, defaults `1` and `0`;
see `Bool` below for why not `Bool`).

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
VarChar Text MediumText LongText Blob MediumBlob LongBlob Bool Date Time
DateTime Timestamp Year Json Uuid Enum`. Pick the semantic type and let the
builder map it (`Json` → JSONB on Postgres, `Uuid` → native UUID on Postgres,
`Timestamp` → DATETIMEOFFSET on SQL Server, whose `TIMESTAMP` keyword is a
rowversion counter, not a datetime). The builder's DocBlock carries the full
matrix. Invalid combinations throw `InvalidArgumentException` at construction
— no silent bad DDL.

Use `Bool` only for true boolean semantics (`true`/`false` values). For
`0`/`1` flag columns (`enabled`, `deleted`, …) use `SmallInt`/`TinyInt`:
`Bool` maps to Postgres `BOOLEAN`, which does **not** implicitly cast an
integer `1`/`0` on insert, so `INSERT ... enabled = 1` fails with *"column is
of type boolean but expression is of type integer"*. `SmallInt` is portable
across all engines.

```php
...$this->field(name: 'is_verified', type: MgrFieldType::Bool, default: false),   // boolean semantics
...$this->field(name: 'enabled', type: MgrFieldType::SmallInt, unsigned: true, default: 1), // 0/1 flag
```

`Enum` is enforced on MySQL only; the other three engines get a plain
string column that accepts any value, so the constraint silently does not
exist. Use `VarChar` and validate in application code unless the table is
MySQL-only by design.

`TinyInt` is unsigned-only on SQL Server (0-255) — use `SmallInt` for a
column that must hold negative values and stay portable.

## Altering tables

Use `modify_column` to change a column's type, constraint, or default — never
drop+add an existing column. Drop+add works on an empty table but silently
loses data on a live one and obscures intent (a reader can't tell a type
change from a column removal).

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

`add_index()`/`drop_index()` (and `add_foreign_key()`/`drop_foreign_key()`,
below) take an optional `name` to override the derived one — needed for an
index/FK this builder didn't create itself. All four are idempotent and
return `bool`: `true` if the call created/dropped something, `false` if a
match already existed (`add_*`) or didn't exist (`drop_*`). None throws on
that.

Tightening `nullable: true` to `false` fails while any row still holds
`NULL` — a `default` in the same call sets the column default, it does not
backfill. `UPDATE` those rows first.

`down()` must reverse `up()` (see `Manager_ion_auth_v3.php` for a full symmetric
example).

### Guarding a table's down()

`has_data(string $table, int $min = 0): bool` guards a destructive `down()`
on a table the operator has judged essential — business criticality the
schema doesn't show, so add it when asked (or flag the risk and ask), never
on your own. Check it first and throw before the destructive calls
(`if ($this->has_data('invoice')) { throw new RuntimeException(...); }`);
raise `$min` for a table a fresh install seeds. No config override exists —
delete the check once a destructive downgrade is confirmed intentional.

### Cross-family type changes

A type change with no automatic cast — string to numeric is the common case
— fails on PostgreSQL with *"column ... cannot be cast automatically ... You
might need to specify USING"*. Use `modify_column_cast()` instead of
`$this->dbforge->modify_column()`; outside PostgreSQL it delegates to
`modify_column()` unchanged, so it is safe on any engine:

```php
$this->modify_column_cast('supplier_invoice', $this->field(
    name: 'fiscal_status', type: MgrFieldType::TinyInt, constraint: 4, nullable: false, default: 0,
));
```

Pass one column's `field()` output directly — not spread, one column per
call; its `null`, `default` and rename are applied for you. It converts
nothing the engine would reject: every stored value must already parse as
the new type, so normalize text labels (`'pending'`, `'paid'`) with an
`UPDATE ... CASE` first. There is no per-call `USING` override.

## Key-prefix-length indexes, foreign keys, and primary keys

Always call these after the target table exists — there is no
`CREATE TABLE`-time form for any of them (a PK declared at creation time is
`$this->dbforge->add_key()` instead — see "Creating a table" above).

```php
$this->add_index(table: 'cfdi_cat_tax', columns: ['description'], prefix_lengths: ['description' => 768]);

$this->add_foreign_key(
    table: 'bank_movement', column: 'bank_account_id',
    ref_table: 'bank_account', ref_column: 'id',
    on_delete: 'CASCADE',   // one of RESTRICT (default) / CASCADE / SET NULL / SET DEFAULT / NO ACTION
);
$this->drop_foreign_key('bank_movement', 'bank_account_id');

$this->add_primary_key(table: 'user_client', columns: ['user_id', 'client_identifier']);
$this->drop_primary_key('user_client');
```

`prefix_lengths` throws on SQL Server; the four FK/PK helpers throw on
SQLite, which needs its recreate-table procedure (not built here). Engine
mechanics: `docs/development/database.md`'s "Cross-engine quirks" section.
The drop helpers return `bool` like `drop_index()`. `add_primary_key()`
breaks the no-op convention: an existing primary key (on any columns) throws
`RuntimeException` — drop it first to replace it.

Moving the primary key onto a composite key keeps the `id` column — models
address rows by one scalar id, so the composite key goes *alongside* `id`,
never instead of it. That move, and restoring an AUTO_INCREMENT column, need
a specific call order; MySQL/MariaDB reject the wrong one with *"Incorrect
table definition; there can be only one auto column and it must be defined
as a key."* Both recipes, and `add_auto_increment()`, are in
`references/key-surgery.md`.

## Running migrations

Always via `bin/cli_run.sh` (wraps php with the correct binary path and
`nice`), never plain `php public/index.php`. In the Docker stack, through
`docker_manage.sh`:

```bash
./docker_manage.sh -e <instance> exec php bash /var/www/html/bin/cli_run.sh manager/tools/migrate

# the manager/tools commands (same URI args through cli_run.sh):
manager/tools/plan           # dry-run: current/latest/pending per target
manager/tools/migrate        # everything forward, all connections in $config['migration_db']
manager/tools/migrate latest {module_key}  # single target, forward to its own latest — never runs down()
manager/tools/migrate {version} {module_key}  # single target to an exact version — DOWNGRADES run down()!
manager/tools/version_list   # list version_list commands per target
manager/tools/version_set {version} {app|module:key} {conn}  # record version WITHOUT running (adopting existing DBs)
manager/tools/migration_file {name} {module} {database} [1]  # scaffold; 4th arg = force_modification, positional
```

`RUN_MIGRATIONS=true` on one instance migrates on startup.

`migrate`/`migrate_database`/`version_set` force `db_debug` on for the
connection they touch, so a failed DDL statement halts with the
query/file/line instead of being recorded as applied. `plan`/`version_list`
are read-only and don't.

Version tracking: the app sequence is one row in `migrations`; each module
tracks its own row in `migrations_path`. Targets are auto-discovered (the app
dir plus every module with a `migrations/{conn}/` dir, vendor ones
included), and `plan`/`version_list` label each with the exact `module_key`
to pass — `:` for `/`, full offset for a vendor module
(`vendor:ixaya:manager:system:package:modules:manager`).

## Rules

- One concern per migration; never edit an applied migration — add a new one.
  Exceptions, since tracking is keyed by timestamp alone: a pure rename
  (filename + class together), a refactor that provably emits byte-identical
  DDL, a fix confined to `down()` (it runs only on an explicit downgrade,
  which then gets the corrected reversal), or a pure non-unique index added
  to a table's create migration when nothing in code depends on it: new
  installs get it, existing ones adopt it with their own `add_index`
  migration, and the drift is harmless by construction. Use `add_index()`
  there, never `dbforge->add_key()` — the two name the index differently on
  Postgres/SQLite, so a later `add_index()` would not see it and would
  create a duplicate.
- Migrations run through dbforge/`$this->db` on the connection being migrated
  — don't load models inside migrations.
- Legacy files under the root `application/database/migrations/` folder are
  frozen history: never renumber them.
- Write engine-neutral DDL: no raw `ENUM(...)` strings, no MySQL-only column
  clauses — that's what `MgrFieldType` and the index helpers are for. Raw
  `$this->db->query()` DDL is a last resort and must handle each `MgrDriver`
  case (see `modify_field_timestamp()` in the builder for the pattern).
- One statement per `$this->db->query()` call — CI's pdo driver prepares every
  statement and the prepared protocol rejects multiple commands, so SQL that
  works under a native driver fails under `pdo/*`.

## Anti-patterns

```php
// WRONG — drop+add to change an existing column (silently loses data on a live table)
$this->dbforge->drop_column('user', 'email');
$this->dbforge->add_column('user', [
    ...$this->field(name: 'email', type: MgrFieldType::VarChar, constraint: 254, unique: true),
]);

// WRONG — same change, raw MySQL-only DDL (breaks on Postgres, SQL Server, SQLite)
$this->db->query("ALTER TABLE user MODIFY email VARCHAR(254) NOT NULL UNIQUE");

// RIGHT
$this->dbforge->modify_column('user', [
    ...$this->field(name: 'email', type: MgrFieldType::VarChar, constraint: 254, unique: true),
]);
```
