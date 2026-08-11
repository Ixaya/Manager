# Database drivers — decisions

Operator decisions with rationale. Entries are dated and append-only: a
ruling that later changed keeps its original entry and gains a superseding
one, so the reasoning that held at the time stays readable.

## Driver choice

- **2026-08-04: `DB_DRIVER=pdo/<engine>` is a real, first-class
  configuration**, resolved by `MgrDriver::fromCI()`'s new `subdriver`
  parameter and built by the sample's `mgr_apply_pdo_dsn()`. No new env
  vars — it reuses the existing `DB_HOST`/`DB_PORT`/`DB_NAME`.
- **2026-08-04: `mgr_apply_pdo_dsn()` stays sample-local, not promoted to a
  skill or an architecture note.** It's config-file plumbing for one file's
  shape, not a cross-cutting convention other code needs to follow — a
  project that doesn't want it can set the `dsn` directly instead. Re-ships
  with every scaffold refresh same as the rest of `database.php`.
- **2026-08-05 (closing review): `mgr_apply_pdo_dsn()` sets
  `PDO::ATTR_EMULATE_PREPARES => true`, scoped to the `pgsql` subdriver.**
  Without it, `pdo_pgsql` prepares every statement server-side — the entire
  measured cost gap (below). Scoped rather than unconditional because
  `pdo_sqlite` throws on the attribute at construction and `pdo_mysql`
  already defaults it to `true`. No security cost: this framework binds no
  parameters through any driver — it escapes and splices them into the SQL
  string — so the server-side prepare was guarding nothing.
- **2026-08-09 (correction to the entry above): `pdo_sqlite` does not throw
  on `PDO::ATTR_EMULATE_PREPARES`.** Measured directly: passing it to the
  constructor is accepted, `setAttribute()` returns `false` silently, and
  only `getAttribute()` throws — which nothing in the driver layer calls.
  Keeping the option scoped to `pgsql` remains correct, for the other
  reason already given (`pdo_mysql` defaults it on, `pdo_sqlite` gains
  nothing either way) — the scoping decision stands, its stated rationale
  was wrong.
- **2026-08-05 (FINAL): native (`mysqli`/`postgre`) ships as the default;
  `pdo/mysql` and `pdo/pgsql` are fully supported, opt-in alternatives at
  performance parity, not fallbacks.** Native ships by default because it
  matches CI3's long-standing stringify-everything contract, which is what
  every existing project's API clients expect; a project that wants PDO's
  typed fetches, or is starting fresh with no contract to preserve, adds the
  one subdriver it needs and rebuilds. Nothing here is a one-way door.
- **2026-08-09 (supersedes the ruling above, for new projects only):
  `pdo/mysql` is the shipped default.** `sample/.env.sample`'s `DB_DRIVER`
  and `database.php`'s own `mgr_env()` fallback both moved off `mysqli`. An
  upgrading project is untouched — `composer update` never rewrites a
  project's `.env` or `database.php` — so native remains fully supported and
  no upgrade is forced; it is just no longer the recommendation. Note that
  preserving a string contract is no longer a reason to stay native, since
  `STRINGIFY_FETCHES` does that on PDO (next entry): what is left is a host
  without the subdriver, and wanting the exact client behavior a project
  already runs. What changed is which side a project with no contract
  to preserve starts on: native's stringify-everything behavior is a CI3
  inheritance, not a design goal, and a REST API emitting `"id":11` rather
  than `"id":"11"` is the more correct contract for its consumers. The
  company also builds an increasing number of small single-purpose
  services; starting each on typed fetches keeps them off a migration
  nobody wants to run later.
- **2026-08-09: an upgrading project has two supported paths, and the
  compatibility bridge is a commented line, not an API.** Stay native, or
  move to PDO with `PDO::ATTR_STRINGIFY_FETCHES` set so every column keeps
  arriving as a string. The flag ships as a commented `$options +=` line
  inside `mgr_apply_pdo_dsn()`, next to the `EMULATE_PREPARES` line it
  mirrors — deliberately not a named parameter or an env var. It exists to
  be deleted once a project's clients accept native types; a named flag
  would present a temporary bridge as a standing configuration choice, and
  an env var can be set once and forgotten. Verified live rather than
  assumed: uncommenting it restores string ids end to end, and every
  primary-key write path stays correct with it on. What the flag does not
  promise is that PDO is otherwise byte-identical to the native driver.
  Native and PDO pre-initialize their own client defaults, and this
  framework has equalized them one at a time as they surfaced —
  `ATTR_EMULATE_PREPARES` was found by benchmarking, not by reading
  documentation, and the measured `Bool` value shift on Postgres is the
  other known one. Treat "equalized" as "every difference found so far",
  which is the honest reason a project needing exact parity stays on the
  driver it already runs.
- **2026-08-09 (supersedes the no-`pdo_*` ruling): `pdo_mysql` and
  `pdo_pgsql` ship in the image; `pdo_dblib` deliberately does not.** The
  default driver can no longer be an extension the image lacks. SQL Server
  is the exception on purpose: its only Alpine-viable subdriver is FreeTDS,
  which cannot complete this framework's own migrations against real vendor
  bugs, so shipping it would invite projects into known-broken territory.
  The compose `mssql` profile stays, carrying a comment that the image has
  no driver yet, so whichever remediation lands only has to reinstate the
  extension.
- **2026-08-05: `pdo/*` variants are NOT engine-matrix rows.** A routine
  parity or regression pass runs the native drivers only — including `pdo/*`
  would double the verification surface of every DB fix. Exercise a PDO
  variant when a change touches the PDO path or is asked for by name.
  Recorded in `docs/development/framework-workflow.md`'s "Cross-engine
  verification" and `docs/development/spec-campaigns.md`'s live-testing
  list.
- **2026-08-05: one `$this->db->query()` call per statement**, everywhere in
  the framework and consuming projects. CI's PDO driver uses the prepared
  protocol, which rejects multiple commands in one call; native `postgre`'s
  simple-query protocol silently tolerated it. Recorded in the
  `mgr-migrations` skill.
- **2026-08-05 (pending gate): matrix-breadth closed as sufficient.**
  MariaDB has the benchmark and fetch-type matrix, which is already more PDO
  exposure than the non-matrix-row ruling requires; SQL Server was never
  promised and stays unrun. `fromCI()`'s `subdriver` param is additive, so
  neither gap blocks anything above.
- **2026-08-09: `DB_CHAR_SET` has no shared default, and `pdo/pgsql` gets it
  through the DSN.** The single `'utf8mb4'` fallback was MySQL-flavored and
  applied to every engine, so a Postgres project that never set the variable
  inherited a value its server rejects. There is no engine-neutral default
  worth guessing, so there is now none — a wrong value fails at connect
  instead of being silently patched. `pdo_pgsql` has no `_db_set_charset()`
  anywhere in the driver layer, which made a configured charset silently
  inert; libpq's `options` conninfo keyword in the DSN is the only
  mechanism that works without patching the driver, and
  `mgr_apply_pdo_dsn()` now emits it. Note the split is by driver *family*,
  not by engine: native `mysqli`/`postgre` error on an empty charset, while
  every PDO subdriver and both SQLite drivers accept unset and take the
  database's own default.
- **2026-08-09: `stricton` defaults to `true` for new projects.** It is read
  only by the three MySQL-family drivers and is inert everywhere else, so
  setting it in the shared config group is safe regardless of engine. A new
  project should not start out accepting zero-dates and silent truncation;
  an existing one is unaffected until it edits its own config, which is
  where the audit belongs.
- **2026-08-09: two long-standing "PDO gaps" closed as non-issues rather
  than fixed.** Both looked PDO-specific and neither is. `reconnect()` is
  unimplemented on the PDO path, but it does not reconnect anything on any
  driver — it only invalidates the handle so the next query reconnects
  lazily — and nothing in this codebase holds a connection across a loop or
  calls it at all. `data_seek()` is likewise unimplemented on
  `CI_DB_pdo_result`, and its only callers are three guarded lines inside
  `DB_result` whose guard is unreachable on every driver, native included,
  because a row-fetch always fully populates the result cache first. Each
  has a standing write-up of what a real fix would cost, kept because both
  are more expensive than they look: the first needs no vendor change at
  all, the second needs a Composer patch *and* a per-subdriver cursor
  design.
- **2026-08-09: a write that returns a primary key re-derives it through a
  real query, and `replace()` was split by meaning.** `insert_id()` on
  MySQL/MariaDB/SQLite reads a raw client API whose type ignores the
  connection's fetch mode, so an inserted id could disagree with the type
  `get()` returned for the same column — including on native `mysqli`,
  independently of PDO. Those three drivers now re-derive it with a
  `SELECT`, which inherits whatever the fetch pipeline does; the drivers
  whose `insert_id()` already runs a real query are left alone. In the same
  pass `replace()` was retired: it had been dispatching to a different SQL
  mechanism per driver, and those mechanisms disagree about what an omitted
  column means, so one method name carried two behaviors. It is now
  `replace_pk()` (delete the row holding the key, then insert — every
  engine) and `upsert_atomic()` (one insert-or-merge statement against a
  named unique key — Postgres only, throwing elsewhere rather than
  emulating). `upsert()` also stopped treating an unknown id as an insert
  target, which was reporting success while writing nothing.
- **2026-08-11 (partial reversal of the entry above): `replace()` is back,
  scoped to MySQL/MariaDB/SQLite only.** The original method carried two
  behaviors because it dispatched across all five drivers, some of which
  (Postgres `ON CONFLICT`, SQL Server `MERGE`) don't share REPLACE INTO's
  full-row delete-then-insert semantics. Restricted to the three drivers
  that do share that mechanism, the disagreement the retirement was
  guarding against doesn't arise, so the method is reinstated rather than
  redesigned. `replace_pk()` and `upsert_atomic()` are unchanged and still
  the only options on the other two engines.
- **2026-08-09: fixes to CI3's database layer go through its public API or
  its config, never through an override.** Those classes live in a Composer
  dependency with no subclass seam, so "override the driver method" always
  costs a patch. Both gaps closed above, the charset fix, and the
  connection-attribute work all landed without one — the durable version of
  that reasoning is in the `mgr-helpers-libraries` skill, since it applies
  to consuming projects too, and is not repeated here.
- **2026-08-09 (follow-up to the same session's `MGR_Exceptions::_parse_db_error()`
  rewrite — position-based message/query parsing, driven by this objective's
  SQL Server debugging, never itself logged here): the message/query split
  still mis-filed on an empty driver message.** The flag gating which slot is
  `message` only advanced past an empty part, so a driver returning `''` for
  its message — a real branch in `pdo_driver.php::error()`, taken whenever
  `PDO::errorInfo()[2]` is unset — let the *next* part, the SQL, land in
  `message` while `error.query` never appeared. Fixed by advancing the flag
  unconditionally once the message slot is reached, regardless of whether it
  was empty. A live sweep of six real MySQL/Postgres failure classes plus a
  connection killed mid-query found none that reach the empty-message state
  on either shipped engine, so this is defense-in-depth, not a reproduced
  regression — confirmed instead by feeding `MGR_Exceptions::show_error()`
  the constructed array through its real, unmodified rendering pipeline.

**Verification status of the above.** MySQL, MariaDB, PostgreSQL and SQLite
are exercised live on the PDO path; the primary-key write paths are
additionally confirmed on MariaDB with `STRINGIFY_FETCHES` on. SQL Server is
the standing gap — the DSN shape, driver resolution and compose profile
exist, but nothing beyond a clean connection error has ever run against a
real server from this repo, and the one migration that did run was driven by
hand on the operator's own machine. SQLite's authentication suite is blocked
by an unrelated `drop_column()` no-op that reproduces identically on the
native driver.

## The methodology correction (worth keeping as a decision, not just a story)

- **2026-08-04: `pdo/pgsql` measured ~1.7-2x native `postgre` on
  many-small-statement workloads** (get()×25, insert×10, update×10, bulk
  load) — bulk single-statement reads were level. Explained at the time as
  "`PDO_PGSQL` has no emulated-prepare mode, so it issues the extended query
  protocol." **That explanation was wrong**, corrected below.
- **2026-08-05 (closing review): the ~2x was a togglable default, not a
  property of PDO.** `pdo_pgsql` does support `PDO::ATTR_EMULATE_PREPARES`;
  nothing in CI's database tree ever set it. With it enabled, all three
  engines measure at parity (1.03-1.08x, within run-to-run noise) — see
  `driver-matrix-benchmark.md`. Every doc that had published the ~2x figure
  and the wrong mechanism was corrected in the same session
  (`docs/development/docker-decisions.md`,
  `sample/docs/development/docker.md`). The standing lesson, worth repeating
  to whoever runs the next PDO benchmark: **before explaining a number, try
  to make it go away** — one `setAttribute()` call collapsed the entire
  story.
- **2026-08-05: benchmark/matrix probes must carry provenance per figure —
  Model-layer numbers and raw client-library numbers must never share a
  table.** The original ~2× figure was defensible data, measured correctly;
  the risk this campaign actually hit was a reader (a future agent) trusting
  a number without knowing which layer produced it. Generalized as a rule
  for any future comparison probe in the `mgr-live-probes` skill, not
  repeated here as campaign-specific advice.

## Fetch types — a real, permanent divergence (not a bug)

- **2026-08-05: PDO's native fetch types (integers, floats, and on Postgres,
  booleans) are a client-visible API contract, not something to normalize
  away.** `PDO::ATTR_STRINGIFY_FETCHES` is deliberately not set. This is one
  of the four grounds native still ships as the default — flipping it would
  silently change the JSON type of every integer field in every consuming
  project's API responses. Full matrix: `driver-matrix-types.md`.
- **2026-08-09: `STRINGIFY_FETCHES` restores every divergent type, measured
  across PostgreSQL, MySQL and MariaDB — which is what makes the
  compatibility path above honest rather than hopeful.** Every column that
  converts under plain PDO (`TinyInt` through `BigInt`, `Float`, `Double`,
  the primary key, and `Bool` — the one that is not even uniform across
  engines under plain PDO) comes back a string with the flag set. The type
  contract is restored; **the value contract is not, on one cell.**
  PostgreSQL's native driver returns `'t'` for a true `Bool` and
  PDO+stringify returns `'1'`, so a project comparing `=== 't'` still
  breaks. MySQL and MariaDB match native exactly. This is a narrower gap
  than a type change and worth naming precisely, since "set the flag and
  nothing changes" is otherwise the natural reading.
- **2026-08-09: the sample's own REST surface was run end to end on native
  PDO types before the default flipped**, every routable auth and admin
  action, under full error reporting. Nothing in the scaffold's own code
  depends on a fetched value being a string. That is evidence about this
  scaffold, not about any consuming project's controllers — which is
  exactly why the flip is scoped to new projects and the compatibility path
  exists.
- **2026-08-05: native Postgres's `'f'`-string-for-false is a live trap**,
  independent of any driver decision here — `'f'` is truthy in PHP. This is
  why the `mgr-migrations` skill already directs `SmallInt` over `Bool` for
  `0`/`1` flag columns; the guidance holds under PDO too and was not
  duplicated a third time.

## Schema type mapping (MgrFieldType)

Absorbed from `docs/workspace/22-timestamp-timezone-mapping/` at
distillation — a different axis than driver choice above (`MgrFieldType`'s
own cross-engine SQL, exercised identically on native and PDO), landed here
rather than a separate initiative because it is the same subsystem
(`MGR_Migration_builder`) and the same reader.

- **2026-08-10: `MgrFieldType::Timestamp` repointed from a bare `TIMESTAMP`
  literal on every engine to PostgreSQL `TIMESTAMPTZ` / SQL Server
  `DATETIMEOFFSET`.** A plain `TIMESTAMP` column does not re-derive under a
  session-timezone shift the way `TIMESTAMPTZ` does — live-confirmed: a row
  written at `12:00:00+00` under one session offset, re-read after shifting
  the session to `-05:00`, still read back `12:00:00` (unshifted) on plain
  `TIMESTAMP`, but `07:00:00-05` (correctly shifted) on `TIMESTAMPTZ`. SQL
  Server's own `TIMESTAMP` keyword is not a datetime type — it is a
  `ROWVERSION` synonym — so `DATETIMEOFFSET` was the substitute there
  regardless; see `docs/development/database.md` for that naming collision
  and the PostgreSQL trigger's now-disappeared implicit cast. This is a
  breaking change for any deployment with live `Timestamp` columns —
  `system/docs/upgrading/2.3.1.md` carries the `ALTER COLUMN` path. SQL Server's half is
  code-only, unvalidated, pending `pdo-dblib-vendor-gaps`.
- **2026-08-10: SQL Server gets no read-time conversion parity with
  PostgreSQL/MySQL for `Timestamp`, and that is architecturally forced, not
  an oversight.** SQL Server has no session-timezone concept at all
  (`MGR/Model.php:596`; `set_rest_timezone()` has no SQL Server branch).
  `DATETIMEOFFSET` stores whatever offset a write carried, verbatim, with no
  session-driven re-derivation the way PostgreSQL's `TIMESTAMPTZ` or MySQL's
  native `TIMESTAMP` (both converting via a session GUC/variable) get.
  Nothing to build here — documented so a future reader doesn't mistake
  "as far as this type can ever go" for "not yet finished."
- **2026-08-10: `mgr_create_date_time()` now normalizes every return path to
  the app-configured timezone** (`->setTimezone()` on both the
  `createFromFormat` success path and the raw-constructor path) — a
  prerequisite for the `Timestamp` repoint above to read back safely; an
  offset-suffixed string parsed without it stayed locked to its own parsed
  offset instead of converging on the app's.
- **2026-08-10: three field-type corrections, all code-only/live-tested per
  the applicable engine, and the docblock table amended:** `Text` gained the
  SQL Server branch `MediumText`/`LongText` already had (`NVARCHAR(MAX)`,
  Microsoft's documented replacement for the deprecated `TEXT` type — it had
  been falling through to a bare, deprecated `TEXT` literal purely because no
  case existed for it, not for any functional reason). `Float` (4-byte) now
  gets PostgreSQL `REAL` and SQL Server `FLOAT(24)` instead of both engines'
  bare `FLOAT` silently defaulting to 8-byte double precision — live-verified
  on PostgreSQL/MySQL before fixing, since that default was the one fact
  resting on recollection rather than a code read. SQL Server's `TinyInt`
  caveat (unsigned-only, 0-255, no signed 1-byte type exists there) has no
  schema-level fix — documented on the enum case and in the `mgr-migrations`
  skill instead.
- **2026-08-10: an `unsigned: true` field on PostgreSQL or SQL Server is
  honored by re-dispatching `MgrFieldBuilder::_resolveColumn()` to the
  next-widest `MgrFieldType`** (`SmallInt`→`Int`, `Int`→`BigInt`,
  `Float`→`Double`, PostgreSQL-only `BigInt`→`Decimal`), not by passing
  `unsigned` through to CI3's own dbforge attribute. The original finding
  claimed the cross-engine docblock table's "ignored" row for `UNSIGNED` on
  PostgreSQL/SQL Server was wrong in isolation; validating it surfaced the
  real defect instead — CI3's own vendored `postgre_forge`/`sqlsrv_forge`
  `_attr_unsigned()` is a no-op on both engines (upstream bug), so the
  request was doing nothing at all, not merely being reported wrong.
  SQL Server's `BigInt` and both engines' `Decimal` stay capped (no wider
  type exists) — `default => null` in the widen `match`, deliberately
  untouched. Full mechanism in `docs/development/database.md`; the
  `unsigned-widen-noop` proposal narrowed to the raw-CI3-bypass case this
  fix doesn't reach (a caller going around `MgrFieldBuilder` straight to
  dbforge).
- **2026-08-10: `Uuid`'s case-folding and sort-order divergence gets no
  normalization helper — documented on the enum case instead.** PostgreSQL's
  native `UUID` lowercases on read; MySQL/MariaDB's `CHAR(36)` round-trips
  whatever case was written; SQL Server's `UNIQUEIDENTIFIER` additionally
  sorts by mixed-endian byte order, not lexicographically. No framework
  knowledge is hidden behind the fix — it is `strtolower($raw_value)`, exactly
  as clear inline as a named wrapper would be — so nothing was built.
- **2026-08-10: a new helper, `mgr_format_date_time_iso()`, normalizes a
  `Timestamp` column's raw read-back to ISO-8601 across engines** — built
  because, unlike `Uuid`, there IS hidden framework knowledge to encapsulate:
  a caller has to already know to route through the timezone-aware
  `mgr_create_date_time()` to avoid a naive-looking string on MySQL diverging
  silently from an offset-suffixed one on PostgreSQL/SQL-Server. Optional, at
  controller-response time — never automatic. Documented in the
  `mgr-helpers-libraries` skill.
- **2026-08-10: the write-side counterpart — whether
  `mgr_get_now_date_time_sql_format()` should emit an offset-explicit literal
  instead of its current naive `Y-m-d H:i:s` — stays parked, not built.**
  Redundant on PostgreSQL/MySQL (both now store the instant as UTC
  internally regardless of the literal's display format); the one engine
  where it would close a real gap is SQLite, which has no timezone-aware
  storage at all. Blocked on an unproven fact: whether MySQL's strict-mode
  literal parser even accepts a `T`-separated, offset-suffixed string.
  Full write-up: `docs/workspace/00-proposals/timestamp-write-format-atom/spec.md`.
