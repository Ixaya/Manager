# Upgrading — unreleased

Changes between 2.x releases that alter behavior a project already depends on.
Everything else in a minor release is additive.

### New: `log_prune` prunes the `api_log` table — it grew without bound before this

Additive. The `api_log` table (one row per REST call, request parameters
included) previously grew without bound; `manager/tools/log_prune` now prunes
it as a fourth stream, `api`, so the nightly line you already schedule picks
it up with no change. Like the other two delete stages it stays off (`0`)
until you set `MGR_LOG_PRUNE_API_DELETE_AFTER_DAYS`; `.env.sample` recommends
60. That is longer than `app/`/`cli/`'s 30 / 3 on purpose: besides the debug
logs, `api_log` is the record of which `uri` received which GET/POST, so size
it to how far back you review it.

New installs get an index on `api_log.time`; existing ones don't need it —
`docs/development/docker.md`'s "Retention" section covers adding it
(including a non-blocking PostgreSQL build) and reclaiming disk after the
first prune, which deleting rows alone does not do.
