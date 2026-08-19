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
  `vendor/ixaya/manager/system/package/modules/manager/migrations/default/20250820111900_Attachment.php`
  (create table), `.../20260213175009_Ion_auth_v2.php` (modify/add/drop
  columns, rename, indexes — filename predates the module-qualified naming
  below; copy the column operations, not the class-name shape)

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
edit an applied migration in place (see "Rules" below). Check the module's
existing filenames by hand — no tooling required.

Class name is `Migration_{Name}` with only the first word capitalized (file
`20260213175009_Ion_auth_v2.php` → `class Migration_Ion_auth_v2` — this one
predates module-qualification, hence the bare name; copy its capitalization
mechanics, not its naming).

`manager/tools/migration_file <name> <module> [database]` (via
`bin/cli_run.sh`, needs the Docker stack up — see "Running migrations"
below) applies this rule and writes the file for you: it derives the
qualified/versioned name and pre-fills `up()`/`down()` with a
safe-to-run-unedited starting point (id + timestamps) on the first version,
empty on a later one — a fabricated sample against an already-existing
table would succeed silently if left un-edited, so nothing is pre-filled;
write the real `add_column`/`modify_column`/`drop_column` calls per
"Altering tables" below. `manager/tools/migration_path <name> <module>
[database]` prints the same derived name and destination directory as JSON
without writing anything, for wiring into a migration authored by hand.

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

If the model sets `$soft_delete = true` (see mgr-models), the table needs a
`deleted` + `enabled` pair — the model filters `WHERE deleted = 0` on reads
and sets `deleted = 1, enabled = 0` on delete. There is no shorthand; declare
both explicitly as `0`/`1` flag columns:

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
VarChar Text MediumText LongText Blob MediumBlob LongBlob Bool Date Time
DateTime Timestamp Year Json Uuid Enum`. Pick the semantic type and let the
builder map it (e.g. `Json` → JSONB on Postgres, `Bool` → TINYINT(1) on MySQL
/ BOOLEAN on Postgres, `Uuid` → CHAR(36) on MySQL / native UUID on Postgres,
`Timestamp` → DATETIMEOFFSET on SQL Server, where the `TIMESTAMP` keyword means
something else entirely — a `ROWVERSION` counter, one per table, not a
datetime). Invalid combinations throw `InvalidArgumentException` at
construction — no silent bad DDL.

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

`Enum` is enforced on MySQL only. The builder emits a native `ENUM` there but
`VARCHAR(max_len)` on PostgreSQL, `NVARCHAR(max_len)` on SQL Server and plain
`TEXT` on SQLite — on three of the four engines the column accepts any value,
so the constraint you think you declared does not exist. Use `VarChar` and
validate in application code unless the table is MySQL-only by design.

`Text` maps to `NVARCHAR(MAX)` on SQL Server — Microsoft's documented
replacement for the deprecated `TEXT` type. MySQL/PostgreSQL/SQLite keep
their own plain `TEXT`, already correct there.

`Float` (4-byte, single precision) maps to MySQL/MariaDB `FLOAT`, PostgreSQL
`REAL`, and SQL Server `FLOAT(24)` — three engine-specific spellings of the
same width; a bare `FLOAT` on PostgreSQL/SQL Server otherwise defaults to
8-byte double precision. SQLite has no true single-precision float — every
value stores as 8-byte IEEE regardless of declared type.

`TinyInt` is unsigned-only on SQL Server (0-255, no signed 1-byte type
exists there) — a column meant to hold negative values fails to store what
MySQL's signed `TINYINT` (-128..127) can. Use `SmallInt` instead if the
column needs negative values and must stay portable to SQL Server.

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
index/FK this builder didn't create itself. `drop_index()` and
`drop_foreign_key()` return `bool`, not `void`: `true` if a matching
index/FK existed and was dropped, `false` if it didn't (never throws).

Tightening `nullable: true` to `false` fails while any row still holds
`NULL` — a `default` in the same call sets the column default, it does not
backfill. `UPDATE` those rows first.

`down()` must reverse `up()` (see `Ion_auth_v2.php` for a full symmetric
example).

### Cross-family type changes

A type change with no automatic cast between the old and the new type —
string to numeric is the common case — fails on PostgreSQL with *"column
... cannot be cast automatically ... You might need to specify USING"*. Use
`modify_column_cast()` there instead of `$this->dbforge->modify_column()`.
It is safe to reach for on any engine: outside PostgreSQL it delegates to
`$this->dbforge->modify_column()` unchanged, and it never converts a value
the engine would otherwise reject — an overlong or out-of-range value fails
the migration exactly as a plain `modify_column()` would.

```php
$this->modify_column_cast('supplier_invoice', $this->field(
    name: 'fiscal_status', type: MgrFieldType::TinyInt, constraint: 4, nullable: false, default: 0,
));
```

Pass one column's `field()` output directly — not spread, and not several
columns at once; `null`, `default` and a rename on that column are applied
for you.

The cast assumes every stored value already parses as the new type — check
the live data first. A `VarChar` column holding text labels (`'pending'`,
`'paid'`) has to be normalized before the type change, with an ordinary
`UPDATE`. There is no per-call cast override: an expression passed to
PostgreSQL's `USING` clause would convert nothing on the other engines, and
this is the form that behaves the same everywhere.

```php
$this->db->query(
    "UPDATE supplier_invoice SET fiscal_status = "
    . "CASE fiscal_status WHEN 'pending' THEN '1' WHEN 'paid' THEN '2' ELSE '0' END"
);
$this->modify_column_cast('supplier_invoice', $this->field(
    name: 'fiscal_status', type: MgrFieldType::TinyInt, constraint: 4, nullable: false, default: 0,
));
```

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

`add_index()`'s `prefix_lengths` throws on SQL Server. `add_foreign_key()`,
`drop_foreign_key()`, `add_primary_key()`, and `drop_primary_key()` all throw
on SQLite — retrofitting any of these onto an existing table needs SQLite's
recreate-table procedure, which none of these helpers build. Engine
mechanics for all: `docs/development/database.md`'s "Cross-engine quirks"
section.

`add_foreign_key()` takes the same optional `name` as `add_index()` (see
"Altering tables" above), and `drop_foreign_key()` returns `bool` the same
way `drop_index()` does.

### Moving the primary key onto a composite key

Keep the `id` column. Models address rows by a single scalar id
(`get($id)`, `update($data, $id)`, `delete($id)`), so a table without one
drops out of the model API entirely — the composite key goes *alongside*
`id`, never instead of it.

`id` still needs a key of its own once the primary key moves off it:
MySQL/MariaDB refuse to leave an AUTO_INCREMENT column unkeyed even
momentarily, failing with *"Incorrect table definition; there can be only
one auto column and it must be defined as a key."* An index satisfies that
without touching the column, so its values and counter are never disturbed.

```php
// up()
$this->add_index(table: 'user_client', columns: ['id'], unique: true);
$this->drop_primary_key('user_client');
$this->add_primary_key(table: 'user_client', columns: ['user_id', 'client_identifier']);

// down()
$this->drop_primary_key('user_client');
$this->add_primary_key(table: 'user_client', columns: ['id']);
$this->drop_index(table: 'user_client', columns: ['id']);
```

The index is created first and dropped last — it stands in for the primary
key for as long as `id` isn't one, so dropping it any earlier fails the same
way. `unique: true` keeps the uniqueness the primary key used to enforce,
which is also what guarantees `down()`'s `add_primary_key(['id'])` can't hit
a duplicate.

### Restoring an AUTO_INCREMENT column

`add_column()` cannot add an AUTO_INCREMENT column to an existing table on
MySQL/MariaDB — the engine rejects the column unless it is keyed in the same
statement. Add it as a plain column, key it, then let `add_auto_increment()`
number the rows. Reversing a migration that dropped the surrogate key
entirely:

```php
$this->drop_primary_key('user_client');
$this->dbforge->add_column('user_client', $this->field(
    name: 'id', type: MgrFieldType::Int, unsigned: true, nullable: false, default: 0
));
$this->add_index(table: 'user_client', columns: ['id']);   // plain: every row still holds 0
$this->add_auto_increment('user_client', 'id');
$this->add_primary_key(table: 'user_client', columns: ['id']);
$this->drop_index(table: 'user_client', columns: ['id']);
```

The index has to be plain and has to come first: `add_auto_increment()`
needs the column keyed before it can number anything, and a unique key
would reject the placeholder zeros it hasn't replaced yet.

`add_auto_increment()` numbers every row holding `0` or `NULL` and leaves
the rest alone, then positions the counter past the highest existing value —
so it both fills a fresh column and resumes a populated one. On Postgres it
builds the sequence under the name a `SERIAL` column would have gotten
(`{table}_{column}_seq`). It throws on SQL Server and SQLite, which cannot
add `IDENTITY`/`AUTOINCREMENT` to an existing column at all.

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

`migrate`/`migrate_database`/`version_set` force `db_debug` on for the
connection they touch, regardless of the app's own setting — a failed DDL
statement halts the CLI with the query/file/line instead of silently
recording the migration as applied. `plan`/`version_list` stay read-only and
don't force it.

Version tracking: the application sequence lives in the `migrations` table
(single row); each module tracks independently in `migrations_path` (one row
per module key). Module keys in CLI use `:` for `/` (e.g. `manager:tools`).
Targets are auto-discovered: the app dir plus every module with a
`migrations/{conn}/` dir — including modules shipped inside the vendor
package.

## Rules

- One concern per migration; never edit an applied migration — add a new one.
- Migrations run through dbforge/`$this->db` on the connection being migrated
  — don't load models inside migrations.
- Legacy files under the root `application/database/migrations/` folder are
  frozen history: never imitate them, never renumber them. New migrations live
  in their module (`application/modules/{module}/migrations/{connection}/`).
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
