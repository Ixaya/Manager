# Database connection handles: one session per group, and the cache owns the session time zone

Every framework consumer that asks for a database handle by group name gets
the same handle, and with it the same server session, for the life of the
process. `''` means the main handle. The handle cache also owns each
session's time zone: it sends PHP's current offset and re-checks it on every
access.

## The problem

Nobody had counted how many server sessions the framework opens. Native
`postgre` hid the answer, because `pg_connect()` returns the existing link for
an identical DSN. On `pdo/pgsql`, `pdo/mysql` and `mysqli`, each uncached
`load->database($group, true)` is its own session. Measured before the change:

- an authenticated REST request held 2 sessions (the REST handle and main), and
  login held 3 (plus `Rest_key_model`'s cached `'default'`);
- `migrate <version> <module_key>` held 2: the runner and the version lookup;
- `plan` opened one session per lookup: 50 lookups in one process, 50 sessions.

No count grew with requests, so nothing leaked. But two handles on one group in
one process don't share a transaction. The second handle does not see the
first's uncommitted rows, and its DDL waits on the first's lock until
`lock_timeout` (PostgreSQL `55P03`) or `lock_wait_timeout` (MySQL family, error
1205). That split is how an exact-version migrate hung on PostgreSQL.

While fixing the count, the migration runner turned out to corrupt a second
database. If a process revisits a connection (a repeated `MGR_MIGRATION_DB`
entry, or `run('a'); run('b'); run('a')` through the library), the memoized
first runner ran on the second connection. It wrote the first group's whole
schema there and overwrote that database's version rows, without any error.

## What was built

- **Handle contract** (`MGR_Loader::database_cache()`):
  - `''` adopts the current `$CI->db`, or loads main, once per process, and
    never follows a later replacement of `$CI->db`;
  - a group name gets its own cached handle, keyed by the raw string;
  - a DSN string or array is never cached.
- **Consumers on the contract:**
  - REST: package default `rest_database_group` `''`; the REST handle comes
    from the cache through a seam in `REST_Controller` (`_load_rest_database()`,
    overridden by `MGR_Rest_Controller`);
  - `MGR_Rest_key_model` falls back to `''`, and `Tools::_log_prune_api()` uses
    the cache;
  - the sample `auth/api/Login` no longer loads the database itself.
- **Migration runner and lookup** both take `database_cache($conn)`: one session
  per connection, shared by object. `_lib()` re-points a memoized runner with
  `set_db_group()` on every hit. The lookup still never builds the runner, so
  `plan` writes nothing.
- **Session time zone, owned by the cache:**
  - PHP's offset (`±HH:MM`, not a zone name) is sent when an entry is created
    or adopted, and re-checked on every access;
  - an access sends nothing unless the offset, the link (a `WeakReference` to
    `conn_id`) or, on native `postgre`, the backend pid changed;
  - `mgr_date_default_timezone_set()` re-sends to every open entry through
    `sync_session_time_zone()`;
  - on PostgreSQL, a send inside a CI transaction drops the entry's record, so
    the next access re-sends;
  - a DSN-string handle gets the offset once, untracked;
  - on a working link, a `SET` the server rejects throws `RuntimeException`; on
    an unusable link it is logged and retried.
- **Timezone writers retired:**
  - `MGR_Model::connect()` and `setup_model()` no longer send `SET`;
  - `set_rest_timezone()` is a deprecated delegator;
  - `set_database_time_zone()` is deprecated: a no-op for `''` or PHP's offset,
    `LogicException` otherwise on MySQL/MariaDB/PostgreSQL, and a no-op on
    SQLite/SQL Server;
  - `rest_time_zone` accepts zone identifiers only, and the helper logs a
    rejected value.
- **Tests:**
  - the Auth tests read Ion Auth's tables through `ion_auth_model`;
  - the tests that call `dbforge()` load main themselves;
  - `CaseInsensitiveColumnsTest` no longer closes the runner's handle.

## Final state

Sessions opened per case (server connection log, from the start of the action;
every session opened was closed, and none remained afterwards):

| Case | `pdo/pgsql`, `pdo/mysql`, `mysqli` (5 combinations) | `postgre` |
|---|---|---|
| Authenticated REST request | 2 → 1 | 1 → 1 |
| 100 REST requests | 200 → 100 | 100 → 100 |
| Login | 3 → 1 | 1 → 1 |
| Model naming `'default'` + `Rest_key_model` | 3 → 2 | 1 → 1 |
| `migrate <version> <module_key>` | 2 → 1 | 1 → 1 |
| `plan` ×50 in one process | 50 → 1 | 1 → 1 |
| Multi-group `migrate` | one at a time → one per group, held to exit | same |

Where it lives for a reader: `system/docs/upgrading/next.md` (upgrade path);
the mgr-models, mgr-rest-controller, mgr-migrations and mgr-helpers-libraries
skills (the API); `framework/docs/development/restserver-upstream.md` (the
`REST_Controller` seam); `framework/docs/development/framework-workflow.md`
(the two-group migration check). Choices and their reasons: `decisions.md`.
Measurement and validation: `review.md`.
