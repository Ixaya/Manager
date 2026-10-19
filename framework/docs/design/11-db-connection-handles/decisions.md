# Database connection handles — decisions

All rulings are the operator's, 2026-10-16 to 2026-10-18.

## The handle contract: `''` is main, a name is its own handle (2026-10-16)

`''` is the canonical name for the main handle, and the framework's own
consumers ask for it. An explicit group name always means its own cached
handle, keyed by the raw string, so `'default'` and `''` are two sessions even
when they name the same group. Projects that set `rest_database_group =
'default'` or name `'default'` in a model keep a separate session until they
switch to `''`. That cost on projects was accepted because both alternatives
are more complex and more likely to break:

- **Rejected: resolve `''` to the active group name** by copying `DB()`'s
  `$active_group` include chain. That duplicates vendor logic.
- **Rejected: deduplicate by connection fingerprint.** It has to connect to
  find out that a handle is a duplicate.

`database_cache()` dates from manager 1.0. Models got their own `$my_db`
because re-pointing `$this->db` across engines did not always switch identifier
escaping, and the cache was added only to stop that opening duplicate
connections. Nothing beyond Models had been tested on it.

## `''` is adopted once and never follows `$CI->db` (2026-10-16)

The first `database_cache('')` stores the current `$CI->db` if it is loaded,
and otherwise loads main and stores that. After that, the cache returns its own
entry, so a later replacement of `$CI->db` (the migration runner does one)
cannot move framework consumers.

**Rejected: an independent `''` handle.** CI-native readers of `$CI->db` would
add a session and get no session time zone.

Known consequence: inside a migration, the runner has already replaced
`$CI->db`, so a `database_cache('')` there adopts the migration's connection as
main. Nothing in the framework calls it there. The skill says not to use `''`
inside a migration.

## The cache owns the session time zone (2026-10-16, revised 2026-10-18)

The last `SET` on a session wins, and once handles are shared, any consumer
sending its own `SET` changes it for everyone. So the cache is the only writer:

- it sends the offset of `date_default_timezone_get()`, as `±HH:MM`, not a zone
  name (MySQL/MariaDB accept names only when their tz tables are loaded);
- `mgr_date_default_timezone_set()` re-sends to every cached entry;
- `Model::connect()`, `setup_model()` and the REST constructor stopped sending
  their own `SET`.

**Rejected: a time zone in the cache key.** Under the default REST config, the
REST handle opens before PHP's time zone changes, so every later Model would
miss the key and open a second session on the same group. That brings the
split back by default. A per-group or per-call time zone different from PHP's
has no caller and is parked as the proposal "A session timezone different from
PHP's".

**Revised at closing review (2026-10-18).** Applying on creation alone missed
reconnects, a raw `date_default_timezone_set()`, and DST. Every access now
re-checks, and sends only when something changed:

- **Link identity is a `WeakReference` to `conn_id`, not `spl_object_id()`.**
  CI's `close()` drops the only reference to the link, and PHP reuses a freed
  object's id. Observed: after a reconnect the new link reused the old id on
  4 of 6 combinations (`pdo/*`, `postgre`). An id record would have missed all
  four; the `WeakReference` caught all six.
- **Native `postgre` also records the backend pid** (`pg_get_pid()`,
  client-side). `reconnect()` there goes through `pg_ping()`, which can reset
  the same link object to a new backend on the server default time zone.
- **On PostgreSQL, a send inside a CI transaction drops the entry's record.** A
  rollback undoes a `SET`, and a zone changed and changed back inside one
  transaction would otherwise keep the in-between offset. MySQL and MariaDB
  need nothing: `SET SESSION` is not transactional there. A raw `BEGIN` is not
  tracked by CI and stays uncovered.
- **Failure split.** A failed `SET` on an unusable link (`conn_id` false, or
  `SELECT 1` failing) is logged at `error`, nothing is recorded, and the next
  access retries. A `SET` that fails on a working link throws
  `RuntimeException`, naming the offset and the connection. The throw branch
  exists because a live check found MariaDB 12.3.3 rejecting real-zone offsets:
  `Pacific/Kiritimati` +14:00 and `Pacific/Chatham` +13:45 in its summer.
  Logging and moving on would have left the session silently wrong.
- **A DSN-string or array handle gets PHP's offset once, untracked.** That is
  the same outcome as the released `Model::connect()` `SET`, since each such
  connect opened a new handle anyway. The log label is `dsn-generic`, so the
  DSN, which carries the password, never reaches a log line.

## Retiring the per-model time-zone writers (2026-10-16, revised 2026-10-18)

- **`MGR_Model::set_database_time_zone()`** is deprecated, and its signature
  stays `string`. Widening it to `?string` would fatal every project subclass
  that overrides it with `string` (parameter contravariance), and `null` already
  raised a `TypeError` for every caller. Behavior: a no-op for `''` or PHP's
  offset; any other value, including one that resolves to no offset, throws
  `LogicException`, which names `mgr_date_default_timezone_set()` and a separate
  group. Revised 2026-10-18: a no-op on SQLite and SQL Server for every value,
  as before, because they have no session time zone to desync. Tier 3 was fixed
  statically, without a live run.
  - **Rejected: delegate to `mgr_date_default_timezone_set()`.** A model call
    would change PHP's process time zone.
  - **Rejected: a DB-only `SET`.** It recreates the desync behind the cache's
    back.
  - **Rejected: a silent no-op.** It substitutes a different behavior without
    saying so.
  - **Rejected: removal.** It breaks the alias chain, and the fatal gives no
    guidance.
- **`set_rest_timezone()`** stays as a deprecated delegator that re-syncs, and
  ignores its argument.
- **`rest_time_zone`** takes zone identifiers only.
  `mgr_date_default_timezone_set()` logs a rejected value at `error` instead of
  ignoring it silently.

## The REST handle comes from the cache through a `REST_Controller` seam (2026-10-16)

The one `system/third_party/` edit the campaign made: the block that loads
`rest->db` moved verbatim into `REST_Controller::_load_rest_database()`, and
`MGR_Rest_Controller` overrides it to return
`database_cache(rest_database_group)`. Record and re-verify step:
`framework/docs/development/restserver-upstream.md`.

- **Rejected: an `early_checks()` override.** The upstream line runs after
  `early_checks()` and overwrites an explicit group with an uncached load, so
  the override would cover only the default config.
- **Rejected: a one-line edit to the upstream `load->database()` call.** It
  couples the upstream file to an MGR loader method.

`Rest_key_model`'s upstream-derived `?? 'default'` fires only when the config
key is absent. It was homologated to `''` in `MGR_Rest_key_model`, so it gives
no wrong impression; the `RS_` file is untouched.

**Accepted consequence.** REST key, limit and log queries, and
`process_api_user()`'s `user` update, now run on the main session, so they
share the application's transaction state. For example, the destructor's
`api_log` update can land inside an open, or (on PostgreSQL) aborted,
transaction. Native `postgre` has always shared that one link. Inferred from
code, not probed.

## The migration runner and lookup share the cached handle; the "separate handles" rule is superseded (2026-10-16)

The runner's `$CI->db` override is load-bearing, not only `dbforge`. The
vendor parent constructor rebuilds `dbforge` on `$CI->db` with no argument, and
every runner, builder and `__get` read of `$this->db` resolves to `$CI->db` at
call time. So the runner keeps assigning `$CI->db`, but takes it from
`database_cache($conn)`.

The rule before this campaign was: "the runner and the lookup stay on separate
handles". It existed because building the runner through `_lib()` runs
`_setup_database_table()`, which creates tables, so a read-only
`plan`/dry-run that reused the runner would write. It is replaced by: **separate
objects on the same cached handle.** The lookup takes `database_cache($conn)`
and still never calls `_lib()`, so `plan` writes nothing. Before/after
snapshots confirmed this on every combination, with one and with two groups.

- **Rejected: leave the lookup uncached.** It was the last uncached framework
  handle, and the two-session split stays.
- **Rejected: reuse the runner for lookups.** That writes.

`_force_db_debug` on the shared handle reaches only handles asked for by that
group name in the same `manager/tools` process. Models running inside a
migration are out of scope: there is no current use.

## A revisited runner is re-pointed (2026-10-16)

V1 (`MGR_MIGRATION_DB=default,secondary`, `manager/tools/migrate`) was correct
on the released code: each database held only its own tables and version rows.
V2 reproduced silent corruption on the released code:

- **V2b**, a duplicate entry (`default,secondary,default`): the third pass ran
  on the secondary database's backend. That database went from 3 to 28 tables
  (the default group's whole schema), and its `modules/probes` version row was
  overwritten.
- **V2a**, the library API `run('default'); run('secondary'); run('default')`:
  step 3's memoized runner reported the secondary database, the same object and
  backend as step 2, and wrote there. Reproduced on `pdo/pgsql` and `postgre`.
  The cause is not engine-specific: `_libs` memoization plus `$CI->db` read at
  call time.

Fix: `_lib()` calls the memoized runner's existing `set_db_group($conn)` on
every hit. After the fix, V1/V2a/V2b were correct on all six combinations.

- **Rejected: deduplicate `MGR_MIGRATION_DB`.** It covers the duplicate entry,
  not the library API.
- **Rejected: leave it and document it.** The failure is silent wrong-database
  DDL and version-row corruption.

## No two-group migration test (2026-10-16, confirmed 2026-10-18)

The sample ships no second database, in its docker init or its test env.
Projects would have to remove it, or would forget and carry it. A sample test
that ran only when a second group is configured was rejected too: in a project
it would build runners against the real second database.

Instead, an agent sets up a second group temporarily whenever the migration
machinery changes fundamentally:
`framework/docs/development/framework-workflow.md`, "Two migration groups".
A framework-only suite that never ships is parked as the proposal
"Framework-only tests for scenarios with no sample shape".

## `MX_Controller` is discouraged (2026-10-16)

`MX_Controller` clones the loader and re-references only `CI_Loader`'s own
properties, so each MX controller gets its own `_db_cache`, and with it extra
sessions. No class in `system/` or `sample/` extends it. The skill and the
upgrade notes discourage it.

## `db_select` on a shared handle: documented, not fixed (2026-10-16)

A model's `$database_name` switches the database for every user of its group's
cached handle. On the PostgreSQL family, CI's `db_select()` is a no-op. No
model in the repo sets it, and one consuming project does, on a SQL Server
secondary. The recommendation is a separate group. Parked as the proposal "A
model's `database_name` switches the database for every user of its cached
handle".

## Known issues, accepted (2026-10-18)

- **A sibling cache entry on native `postgre`.** The PostgreSQL transaction
  guard reads the CI object's own transaction depth. Two entries whose groups share main's DSN are
  one `PgSql\Connection`, so a transaction opened through one entry is invisible
  to the other. Package defaults never create such an entry.
- **`pg_get_pid()` on an unusable link.** It returns 0 after a detected kill,
  which costs one extra log line per access. On a link closed through a sibling
  entry, it throws `Error`, one call earlier than the first query would have.
  Inferred, not probed.

Both are parked as the proposal "Session-timezone mirroring: residual edges on
native `postgre`".

Two consequences of the failure split are recorded rather than fixed:

- A throw from `mgr_date_default_timezone_set()` stops its sync loop. PHP's
  time zone is already changed, and the remaining entries re-send on their
  next access, which re-throws on the same engine.
- A killed session does not recover by itself. CI reconnects only when
  `conn_id` is false, so every access retries and logs on the dead link until
  something calls `close()`.

## Leaks: none (2026-10-16)

No count grew on any combination: opened equals closed, the maxima stayed flat
over 100 REST requests and 50 in-process lookups, and no session remained
afterwards. Cached handles live to process end, at most one per group name,
plus one per group already visited in a multi-group migrate. That is bounded,
not growing.
