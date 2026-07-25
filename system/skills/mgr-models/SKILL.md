---
name: ixaya-models
description: Use when creating or editing a model, or writing any database query (select, insert, update, delete, joins, dynamic filters) in this codebase. Teaches the MY_Model / APP_Model_Dyn API of the ixaya/manager framework so you never write raw $this->db queries or vanilla CI3 model code.
---

# Ixaya Models (MY_Model / APP_Model_Dyn)

> **Prerequisite:** this skill assumes `ixaya-code-style` is loaded — invoke it
> before writing any code. It owns naming, typing, PHPDoc, and the comments
> policy; this skill only covers models and database queries.

Every model extends `MY_Model` (alias of `MGR_Model`, which extends `CI_Model`).
Never query `$this->db` directly from controllers, and never hand-write SQL when a
model method exists. The base model handles connection selection, tenant scoping,
soft deletes, and `last_update` stamping automatically — raw queries bypass all of it.

Source of truth (only read if something here is insufficient):
- `vendor/ixaya/manager/system/core/MGR/Model.php` — base model
- `vendor/ixaya/manager/system/core/MGR_Model_Dyn.php` — dynamic query model
- `application/core/MY_Model.php`, `application/core/APP_Model_Dyn.php` — app aliases (empty subclasses)
- Canonical example: `vendor/ixaya/manager/sample/application/modules/admin/models/User.php`
  (the vendor sample is the reference — models inside `application/` may predate current conventions)

## Creating a model

File: `application/modules/{module}/models/{Name}.php` — singular class name, maps to
a snake_case table (auto-generated as `strtolower(get_class($this))` if `table_name` not set).

```php
<?php
(defined('BASEPATH')) or exit('No direct script access allowed');

class Invoice extends MY_Model
{
    public function __construct()
    {
        // Overrides go BEFORE parent::__construct() — the parent connects immediately.
        //$this->connection_name = 'reports'; // non-default DB group
        //$this->table_name = 'invoices_v2';  // default: lowercase class name
        //$this->primary_key = 'uuid';        // default: 'id'
        //$this->override_column = 'client_id'; // tenant scoping (see below)
        //$this->soft_delete = true;
        //$this->save_history = true;
        //$this->lazy_connect = true;         // defer DB connect until first use

        parent::__construct();
    }
}
```

Load from controllers with the module prefix: `$this->load->model('admin/invoice');`
then use as `$this->invoice->get_all()`.

Don't name a model after a REST verb (`get`, `post`, `put`, `delete`). Loaded
into a REST controller it collides visually with the input methods: `$this->post`
is the `Post` model but `$this->post('title')` is `REST_Controller::post()`
reading a body field. PHP tells property access from a method call so there's no
runtime error, but it invites mistakes — name it `article`/`blog_post` instead.

## Configuration properties

| Property | Default | Effect |
|---|---|---|
| `$table_name` | lowercase class name | Target table |
| `$primary_key` | `'id'` | Used by `get()`, `update()`, `delete()` |
| `$connection_name` | `''` (default group) | DB group from `application/config/database.php`; connections are cached/shared via `database_cache()` |
| `$database_name` | `''` | Selects a specific database on the connection |
| `$soft_delete` | `false` | `delete()` sets `deleted=1, enabled=0` instead of DELETE; all reads add `WHERE deleted=0` |
| `$save_history` | `false` | Audit trail |
| `$use_last_update` | `true` | Every write stamps `last_update = now` |
| `$override_column` / `$override_id` | `null` | Tenant scoping: all reads/writes get `WHERE table.col = id`; inserts get the column added. `override_id` falls back to `$_SESSION[$override_column]` |
| `$lazy_connect` | `false` | Skip connect in constructor; methods call `check_connect()` |
| `$legacy_mode` | `false` | Single-row reads return objects instead of arrays — never enable in new code |

Rows are returned as **associative arrays** (`row_array()` / `result_array()`), not objects.

## Automatic behaviors (why you don't hand-roll queries)

Applied to every read: `where_override` (tenant filter) + `deleted = 0` (if soft_delete).
Applied to every write: `where_override`, `last_update` stamp. Applied to inserts:
override column value. `delete()` under soft_delete is an UPDATE.

## Read methods

All return `?array` (single row) or `array` (lists, empty array on no results/failure).
`$fields` is a comma-separated select list or array; `null` = `SELECT *`.
`$limit` accepts `int`, `'10'`, or `[limit, offset]`.

```php
get(int|string $id, $fields = null): ?array
get_where(array $where, $fields = null): ?array
by_hash(string $hash, string $field = 'hash'): ?array
get_min_max(string $field, array $where = [], ?string $field_alias = null): ?array
    // returns ['min_{field}' => ..., 'max_{field}' => ...]

get_all($fields = null, array $where = [], $limit = null, ?string $order_by = null, ?string $group_by = null): array
get_all_join($fields, $where, $limit, $order_by, $group_by,
             ?string $join_table = null, ?string $join_where = null, string $join_method = 'left'): array
get_all_like($fields, array $where, ...): array      // WHERE col LIKE %val%
get_all_or_like($fields, array $where, ...): array   // OR LIKE
get_all_in(string $field, array $values, $fields = null, $limit = null, ...): array
get_all_updated(string $last_update, $fields = null, array $where = [], ...): array
    // rows where last_update > $last_update — for sync/polling

count_all(?array $where = null): int
```

`$where` uses CI3 query-builder syntax: `['status' => 1, 'amount >' => 100]`.
Never pass user input into `$order_by` / `$group_by` / `$fields` — they are raw SQL fragments
(use `mgr_build_order_by()` from `manager_helper` to whitelist sortable columns).

## Write methods

```php
insert(array $data): int|string|bool           // insert_id or false
insert_bulk(array $rows): int                  // affected rows
update(array $data, int|string|array $id): bool   // array $id => WHERE IN
update_where(array $data, array $where): bool     // refuses empty $where
upsert(array $data, int|string|null $id = null): int|string|bool
    // $id given => update (returns $id); null => insert (returns insert_id)
upsert_where(array $data, array $where, array $insert_data = []): int|string|bool
    // row matching $where exists => update; else insert(data + where + insert_data)
replace(array $data): int|string|bool          // SQL REPLACE
delete(int|string $id): bool                   // soft or hard per $soft_delete
delete_where(array $where): bool               // refuses empty $where
```

Note `create_date` is NOT set automatically — set it explicitly on insert
(`$data['create_date'] = date('Y-m-d H:i:s');`), matching existing controllers.

## Sync methods (external-source imports: time trackers, invoicing APIs, bank feeds…)

```php
sync_update_insert(array $data, array $where, bool $insert = true, bool $add_sync = false,
                   bool $add_import = true, array $extra_data = [], bool &$modified = false): int|string|false
    // Diff-aware upsert keyed by $where: updates only changed columns, inserts if
    // missing (stamping import_date), optionally flags sync_enabled=1. $modified
    // reports whether anything was written. Returns the row's primary key.
sync_update(int|string $id, array $data, bool $timestamp = true, ?array $row = null, int $default_count = 0): bool
    // Diff-aware update; pass the current $row to skip no-op writes.
sync_update_enabled(int|string|null $id, int $status): bool  // set sync_enabled flag ($id null = all rows)
sync_commit_enabled(): bool  // enabled = sync_enabled, deleted = !sync_enabled — commit a sync pass
```

## Utilities

```php
query(string $sql, ?array $args = null): array|int|false  // last resort; ? placeholders. SELECT => rows, write => affected_rows
empty_row(?array $properties = null, bool $include_id = true): array   // blank row from table columns (for create forms)
empty_object(...): object
get_hash(int $length = 13): string
get_unique_hash(int $length = 13, string $field = 'hash'): ?string    // retries until unused in $field
clean_string(string $text): string             // accent-strip + snake-safe identifier
debug_query(bool $return = false): ?string     // last executed SQL
set_override(int|string|null $id = null): void / set_override_column(string $col) / del_override()
reconnect_database(string $connection_name, string $database_name, bool $generate_table_name = false): void
set_database_time_zone(string $time_zone): void
```

## Dynamic queries — APP_Model_Dyn

When a query needs runtime-composed filters or joins (search screens, report builders),
extend `APP_Model_Dyn` instead of building SQL strings. It extends `MY_Model`, so
everything above still applies.

```php
class Report extends APP_Model_Dyn { ... }
```

### get_all_dynamic

```php
get_all_dynamic($fields = null, array $where = [], array $join = [],
                $limit = null, ?string $order_by = null, ?string $group_by = null): array
```

`$where` is keyed by `MGR_Model_Dyn_clause` constants:

| Clause | SQL |
|---|---|
| `EQUAL` / `OR_EQUAL` | `col = value` (escaped) |
| `LIKE` / `OR_LIKE` | `col LIKE %value%` |
| `WHERE_IN` / `OR_WHERE_IN` | `col IN (...)` — **throws on empty list** |
| `GROUP` / `OR_GROUP` | parenthesized group of inner clauses |
| `EQUAL_COL` / `OR_EQUAL_COL` | column = column (identifiers validated; security-relevant in joins) |

Two formats, mixable — assoc (one entry per kind) or list (repeats allowed):

```php
$where = [
    MGR_Model_Dyn_clause::EQUAL => ['invoice.status' => 1],
    MGR_Model_Dyn_clause::GROUP => [
        MGR_Model_Dyn_clause::LIKE     => ['client.name' => $search],
        MGR_Model_Dyn_clause::OR_LIKE  => ['invoice.folio' => $search],
    ],
];
// or list format to repeat a kind:
$where = [
    [MGR_Model_Dyn_clause::GROUP => [...]],
    [MGR_Model_Dyn_clause::GROUP => [...]],
];
```

### Joins

```php
$joins = [
    $this->build_join(
        table: 'client',
        type: MGR_Model_Dyn_join_type::Left,   // Inner | Left | Right | Join
        on: [MGR_Model_Dyn_clause::EQUAL_COL => ['client.id' => 'invoice.client_id']],
    ),
];
$rows = $this->get_all_dynamic(
    fields: 'invoice.*, client.name',
    where: $where,
    join: $joins,
    limit: [25, 0],
    order_by: 'invoice.create_date DESC',
);
```

Join `on:` accepts the same clause kinds (`EQUAL_COL` for column=column, `EQUAL` for
column=literal, `LIKE`, `WHERE_IN`). Identifiers are regex-validated and throw on
anything unsafe; unknown clause kinds throw instead of being silently dropped.

Gotchas:
- Mixed AND/OR in a join ON clause is emitted flat (SQL precedence applies) — split
  into separate joins if you need grouping.
- `FULL`/`CROSS` join types don't exist (CI3 silently degrades them).
- Cross-driver SQL functions: use `$this->build_field_select(name, MgrFunctionType, args)`
  / `build_function()` instead of writing MySQL-only expressions in `$fields`.

### Canonical list-endpoint pattern (`get_list`)

Paginated/searchable lists follow this shape (from the vendor sample `User` model).
The controller passes `build_list_params()` output straight in; the model returns
`['data' => rows, 'total' => count]`:

```php
class User extends APP_Model_Dyn
{
    public function get_list(array $params)
    {
        $fields = [
            'id', 'email', 'first_name', 'last_name',
            $this->build_field_select('created_on', MgrFunctionType::FromUnixtime),
        ];

        $where = [];
        if (!empty($params['search'])) {
            $search[MGR_Model_Dyn_clause::OR_LIKE] = [
                'first_name' => $params['search'],
                'last_name'  => $params['search'],
                'email'      => $params['search'],
            ];
            if (is_numeric($params['search'])) {
                $search[MGR_Model_Dyn_clause::OR_EQUAL] = ['id' => (int)$params['search']];
            }
            $where[MGR_Model_Dyn_clause::OR_GROUP] = $search;
        }

        // whitelist sortable columns — never pass request input into order_by raw
        $allowed_order = ['email', 'first_name', 'last_name', 'created_on'];
        $limit_page = mgr_build_limit_page($params['limit'], $params['page']);
        $order_by   = mgr_build_order_by($params['order_by'], $params['order'], $allowed_order);

        $rows  = $this->get_all_dynamic(fields: $fields, where: $where, limit: $limit_page, order_by: $order_by);
        $count = $this->get_all_dynamic(fields: 'count(*) AS count', where: $where);

        return ['data' => $rows, 'total' => $count[0]['count'] ?? 0];
    }
}
```

## Anti-patterns

```php
// WRONG — raw db access in a controller
$q = $this->db->query("SELECT * FROM invoice WHERE client_id = $id");

// WRONG — string-concatenated dynamic SQL
$sql = "SELECT * FROM invoice WHERE 1=1" . ($status ? " AND status = $status" : "");

// RIGHT
$this->load->model('billing/invoice');
$rows = $this->invoice->get_all(where: ['status' => $status]);
```
