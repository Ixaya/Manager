# REST error contract — final state

## What the code does now

**Disclosure splits on one predicate**, `MGR_Exceptions::should_disclose_details()`
— `is_cli() || <CI's display_errors test>`, not `ENVIRONMENT`. Disclosed, a
5xx renders `{status: 0, message, error: {…}}` with the diagnostics nested per
path. Suppressed, every 5xx is the same 54-byte
`{status: 0, message: 'An unexpected error occurred.'}` from
`build_generic_error()`. **4xx is never suppressed** in either mode.

**Every dispatched failure is logged before it is rendered.**
`REST_Controller::_handle_dispatch_throwable(\Throwable $ex)` is a pure
extraction of the old catch body (the only change in `system/third_party/`);
`MGR_Rest_Controller` overrides it to `log_exception()` in the format CI's own
handler uses, then render. `MGR_Rest_Controller::_remap()` wraps dispatch in
`catch (\Throwable)` so `Error`/`TypeError` reach the same hook.
`MGR_Exceptions::show_error()` logs its own 5xx (excluding `error_db`, which
the driver logs).

**`status` is binary**, `1`/`0` as integers, and must agree with the HTTP
class. `-1` is emitted nowhere.

**Reads distinguish failure from emptiness.** `MGR_Model::execute_list()`
returns `?array` — `null` on a failed query, `[]` on no rows — and the eight
`get_all*` methods plus `count_all(): ?int` follow. No throw was added.

**Driver statement-failure warnings do not render**, so CI's `db_debug` report
renders instead: `is_non_fatal_statement_warning()` matches
`$statement_warning_prefixes` (`pg_query()`, `SQLite3::query()`,
`SQLite3::exec()`) on non-fatal severities only. `_parse_db_error()` runs its
path through `clean_file_path()` and its message through
`clean_postgres_message()`, which strips libpq's `\nLINE N:` echo — already
carried separately in `error.query` — and only for messages starting with a
Postgres severity label, so no other engine's text can be touched.

**`manager/tools/log_check`** reports the resolved log paths, ownership, mode
and writability, and performs a real append test.

## Remaining open work

Three standing proposals, none blocking. Named by title — a promoted proposal
moves, and a path here would need editing back:

- **Correlation id** — chosen (stamp the id on every log line), deferred. It
  edits this envelope a second time, deliberately. Two prerequisites recorded
  there: a migration path for newly-introduced core overrides, since a new
  `MY_Log` does not reach projects bootstrapped from an older scaffold; and a
  decision on changing the shipped log-line format.
- **`api_only = false` html errors** — the seam answers `200` on a failed
  request.
- **Model failure signals** — `activate()`, `add_to_group()`,
  `clear_login_attempts()` et al. cannot distinguish "no match" from
  "succeeded", so the sample controllers correctly discard those returns. The
  sample teaches the wrong thing until this is answered.

## Accepted residuals

Ruled, not oversights — do not re-raise:

- **A body-less production 500 on two paths**: a throw in a controller
  constructor, and a fatal only the shutdown handler sees. Both are logged.
  In development the fatal is *worse* — HTTP 200 with an HTML dump, and only
  `Cannot modify header information` in the app log, with the real cause on
  container stderr. That signature means output was already sent, which in
  practice means the request ran away: an infinite loop, a runaway query, or
  building something large past the memory limit.
- **`db_debug` off answers `200` with a success envelope on a failed query**,
  in both environments. That is mode (ii)'s contract.
- **A duplicate log line** where a caller logs and then calls `show_error()`.
- **Three rare `error_db` siblings** stay unlogged
  (`db_unsupported_function`, `db_invalid_query`,
  `db_unable_to_set_charset`) — all developer-error conditions.
- **404, unrouted 404 and 405 write no app-log entry**, deliberately.
- **`Sysusers::index_get` entries cached under the pre-fix envelope** survive
  until they expire. No cache-busting was built into a sample.
- **Postgres `errno` is `null`** where MySQL populates it — a pre-existing
  driver limitation, untouched. Never assert on it.
