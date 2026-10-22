---
name: mgr-migrations
description: Use when creating or editing a database migration, adding or altering tables, columns, column types, indexes, foreign keys or primary keys, or running/troubleshooting migrations in this codebase. Teaches the MGR_Migration_builder pattern — typed field() columns, cross-engine type mapping, and the index/key/type-change helpers that close CI3's per-engine forge gaps — instead of the legacy CI_Migration/dbforge-array style.
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
- `vendor/ixaya/manager/system/package/modules/manager/migrations/default/` —
  `*_Manager_attachment.php` (create table); `*_Manager_ion_auth_v2.php`
  (additive) + `_v3.php` (destructive), split so a shared older database can
  stop at the additive one

## File placement and naming

```
application/modules/{module}/migrations/{connection}/{YmdHis}_{Name}.php  # where NEW migrations go
application/database/migrations/{connection}/...   # app-level — frozen legacy history: don't add, never renumber
```

`{connection}` is the DB group name (`default`, etc.). `{Name}` is
module-qualified — `{Module}_{table}` (a `billing` module's `invoice`
table → `Billing_invoice`) — so two modules never pick the same class name.
If that name already exists in any of the module's migrations directories,
append `_v2`, `_v3`, …; never edit an applied migration (see "Rules").

Class name is `Migration_{Name}` with only the first word capitalized (file
`20260213175009_Manager_ion_auth_v3.php` → `class Migration_Manager_ion_auth_v3`).

`manager/tools/migration_file` applies this rule and prints a
`cat > ... <<'MGR_EOF'` command to paste into your host shell (a container
cannot persist a write to `application/`). A first version is pre-filled (id
+ timestamps); a later one is empty, since a fabricated sample would succeed
silently against an existing table. A 4th arg `1` (`force_modification`)
makes the first migration on a table with no module-qualified history a
`_v2` modification instead of a create. `migration_path` prints just the
derived name and directory as JSON.

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

`field()` returns `[name => spec]`, so specs are **spread (`...`)** into the
dbforge array. Named parameters:

```php
$this->field(
    name: 'price', type: MgrFieldType::Decimal,
    constraint: 191,      // CHAR/VARCHAR length
    unsigned: true,       // numeric types only (validated)
    nullable: false,      // true = NULL, false = NOT NULL (the default, on create, add and modify alike)
    unique: true,
    auto_increment: true, // int types only (validated)
    default: 0,           // scalar or null; omit for no DEFAULT clause
    new_name: 'new_col',  // renames — for modify_column only
    precision: 10, scale: 2,          // Decimal
    enum_values: ['active', 'inactive'], // Enum (required)
    case_insensitive: true, // Char/VarChar/Text/MediumText/LongText only — see "Case-insensitive columns"
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

Use `Bool` only for true boolean semantics. `0`/`1` flag columns use
`SmallInt`: `Bool` maps to Postgres `BOOLEAN`, which does **not** cast an
integer on insert (*"column is of type boolean but expression is of type
integer"*). That includes the `enabled` + `deleted` pair a model with
`$soft_delete = true` needs (see mgr-models) — no shorthand, declare both
(`unsigned`, defaults `1` and `0`).

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

`unsigned: true` on PostgreSQL and SQL Server widens an integer to the type
holding its unsigned range: `SmallInt` to 4 bytes, `Int` to `bigint`, `BigInt`
to `numeric(20,0)` (`DECIMAL(20,0)` on SQL Server); PostgreSQL `TinyInt` stays
`smallint`. It is a range change, not a constraint: the column still accepts
negatives. `Float`, `Double` and `Decimal` are not widened, so an unsigned
`Float` is a plain `Float` there — use `Double` for more precision. On
PostgreSQL an `auto_increment` on `BigInt` (or on an unsigned `Int`, which
`field_id()` is) is a `bigint` column with a `GENERATED BY DEFAULT AS
IDENTITY` sequence, a signed `Int` an `integer` one. `modify_column()` with
`auto_increment` works there too: it sets `NOT NULL`, adds the identity and
positions the sequence past the highest value, and on a column that is already
an identity it only changes the type (the sequence follows). A `NULL` row fails
the `NOT NULL`; rows holding `0` stay `0` — `add_auto_increment()` numbers
those. An explicit id never advances the sequence (unlike MySQL): after
importing rows with ids, position it with `setval()`.

## Case-insensitive columns

New databases compare text exactly (case- and accent-sensitive). A column that
must match case-insensitively says so with `case_insensitive: true`; nothing
else changes comparison semantics, and every other column emits no `COLLATE`.

```php
...$this->field(name: 'username', type: MgrFieldType::VarChar, constraint: 191, case_insensitive: true),
```

It is accepted on `Char`, `VarChar`, `Text`, `MediumText` and `LongText`; any
other type (`Uuid` and `Enum` included) throws. The collation comes from the
connection's config group in `database.php` — `dbcollatci` on MySQL/MariaDB,
`dblocaleci` on PostgreSQL; SQLite uses `NOCASE` (ASCII only), SQL Server
throws. Recommended values per engine: `docs/development/database.md`. An
empty key throws; there is no fallback to an exact column. On PostgreSQL
(18+, since `LIKE` on a nondeterministic collation errors before that) the
builder creates the collation `manager_case_insensitive` on first use and
throws if one by that name exists with another definition.

`modify_column()` and `modify_column_cast()` keep the collation when the field
is declared `case_insensitive: true` again; restating it without the flag
resets it to the exact default, which is how a `down()` undoes it.

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

$this->add_index(table: 'login_attempt', columns: ['login', 'ip_address']);  // composite: cross-engine, name-length safe
$this->drop_index(table: 'user', columns: ['email'], unique: true);          // the only way to remove a unique
```

Derived index names, shared by `add_index()`, `drop_index()` and
`dbforge->add_key()`: PostgreSQL `{table}_{columns}_idx` plain and
`{table}_{columns}_key` unique (the names the engine gives itself, in the
exact case given, cut its way past 63 bytes); SQL Server `{table}_{columns}`
and `{table}_{columns}_key`; MySQL/MariaDB `{columns}` for both. `drop_index()` takes the `unique` the
index was made with: on PostgreSQL and SQL Server omitting it looks for the
plain name and returns `false`, while on MySQL/MariaDB the same call drops
either kind — test a `down()` on PostgreSQL.

A unique on a new column is the field's `unique` at `create_table` or
`add_column`; on an existing column it is `unique: true` in `modify_column()`,
which adds nothing when a unique already holds the derived name and throws when
a plain index does. `unique: false` or omitting `unique` never removes one —
removal is always `drop_index()`. `add_index()` is for composite, prefix-length
and non-unique indexes. On PostgreSQL a field's `unique` is a constraint and
`add_index(unique: true)` an index; each finds the other by name.

Renaming a column that already has a unique: `modify_column()` with `new_name`
and `unique: true` throws, because the old unique would stay beside the new one
under its old name. Drop it first — `drop_index(table:, columns: <old>,
unique: true)` — then rename with `unique: true` in one `modify_column()`.
Inside the DDL transaction that is atomic on PostgreSQL; on MySQL/MariaDB
uniqueness is not enforced between the two steps.

On a populated table, call `assert_no_duplicates(table:, column:, case_insensitive:)`
first — before `add_index(unique: true)`, a `modify_column()` with `unique: true`
(on MySQL/MariaDB the column change has already run when the unique fails), or
one that makes the column case-insensitive. It throws with the duplicate groups'
ids (never their values; NULLs ignored), grouping under the CI collation when
`case_insensitive: true`.

`add_index()`/`drop_index()` and `add_foreign_key()`/`drop_foreign_key()`
take an optional `name` for an index/FK this builder didn't create. All four
are idempotent and never throw on a no-op: they return `true` if they
created/dropped something, `false` if it already existed (`add_*`) or was
absent (`drop_*`). One exception: `add_index(unique: true)` throws when a
plain index already holds its name (MySQL/MariaDB, where plain and unique
share one, or an explicit `name`) — drop the plain one first. A plain
`add_index()` over an existing unique stays `false`: the unique serves it.
`drop_index()` also drops a PostgreSQL unique constraint; an index that
backs a primary key throws — use `drop_primary_key()`.

With `db_debug` off, every DDL statement the builder's helpers run — those four,
the primary-key helpers, `add_auto_increment()`, `modify_field_timestamp()` (its
`IF EXISTS` cleanups aside) and `modify_column_cast()` — throws
`RuntimeException` with the engine's error when the engine rejects it. With it
on, CI's own error handling halts at the failing statement instead, which is
what happens under the runner. A plain `dbforge` call in a
migration follows CI's convention and returns `false` with `db_debug` off.

Tightening `nullable: true` to `false` fails while any row still holds
`NULL` — a `default` in the same call does not backfill. `UPDATE` those rows
first, then `assert_no_nulls(table:, columns:)` before the `modify_column()`:
it throws with the row ids (never values), where MySQL/MariaDB with strict
mode off would silently rewrite the NULLs to `''`/`0`.

Restating a column in `modify_column()` or `add_column()` re-declares its
nullability: omitting `nullable:` means NOT NULL.

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
call; its `null`, `default` and rename are applied for you. On PostgreSQL it
sets the forge's `cast` attribute, which adds `USING "col"::type` to the type
change; a raw `$this->dbforge->modify_column()` can set `cast => true` itself.
It converts nothing the engine would reject: every stored value must already
parse as the new type, so normalize text labels (`'pending'`, `'paid'`) with an
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
manager/tools/migrate {version} {module_key} [1]  # single target to an exact version; a DOWNGRADE runs down() and is refused unless the 3rd arg (confirm_downgrade) is 1
manager/tools/version_list   # list version_list commands per target
manager/tools/version_set {version} {app|module:key} {conn}  # record version WITHOUT running (adopting existing DBs)
manager/tools/migration_file {name} {module} {database} [1]  # scaffold (see "File placement")
```

`migrate` prints each `[FAIL]` line (a refused downgrade included) to stderr and
exits 1 after the remaining targets have run; `[WARN] … not found` stays on
stdout with exit 0.

`RUN_MIGRATIONS=true` on one instance migrates on startup.

`plan`, `version_list`, `version_set` and `migrate` force `db_debug` on for
the connection they touch, so a failed statement halts with the real SQL
error instead of being recorded as applied. Never use `database_cache('')`
inside a migration: the runner has replaced `$CI->db`, so the cache would
adopt the migration's connection as main.

Version tracking: the app sequence is one row in `migrations`, each module
its own row in `migrations_path`. Targets are auto-discovered (the app dir
plus every module with a `migrations/{conn}/` dir, vendor ones included);
`plan`/`version_list` label each with the `module_key` to pass — `:` for
`/`, full offset for a vendor module
(`vendor:ixaya:manager:system:package:modules:manager`).

## Multi-step migrations (PostgreSQL transaction)

PostgreSQL rolls DDL back; MySQL/MariaDB commit it implicitly. A migration
whose steps must all land or none brackets them with the builder's pair — a
transaction on PostgreSQL, a no-op elsewhere, so no engine branch:

```php
$this->start_ddl_transaction();
// ... modify_column / add_index steps
$this->end_ddl_transaction();   // commits, or rolls back and throws if a statement failed
```

Call the pair in `down()` too. The runner rolls back a migration that throws
between the two. On MySQL/MariaDB nothing rolls back, so make each step
idempotent and re-run `migrate` after fixing the cause; the version only
advances once `up()` returns.

## Rules

- One concern per migration; never edit an applied migration — add a new one.
  Tracking is keyed by timestamp alone, so the exceptions are: a pure rename
  (filename + class together), a refactor emitting byte-identical DDL, a fix
  confined to `down()`, or a non-unique index nothing in code depends on,
  added to the table's create migration (existing installs adopt it with
  their own `add_index` migration). Either `add_key()` or `add_index()`
  works there: both derive one name.
- Migrations run through dbforge/`$this->db` on the connection being migrated
  — don't load models inside migrations.
- Engine-neutral DDL only — `MgrFieldType` and the index helpers exist for
  it. Raw `$this->db->query()` DDL is a last resort and must handle each
  `MgrDriver` case (pattern: `modify_field_timestamp()` in the builder).
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
