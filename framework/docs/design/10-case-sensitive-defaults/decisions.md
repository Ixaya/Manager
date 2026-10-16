# Case-sensitive defaults — decisions

All rulings are the operator's, 2026-10-12 to 2026-10-14.

## Exact by default; case-insensitivity is declared, never inherited

New databases are case- and accent-sensitive. A column is CI only when its
migration says `case_insensitive: true`; a query is CI only when it calls
`like_ci()`. Generic `where()`/`like()` keep their semantics, no shipped
migration is edited, and no existing schema is converted wholesale.

**Rejected: schema-wide parity with MySQL.** The earlier proposal "Postgres
collation parity with MySQL, schema-wide, through the migration builder"
gave every text column on PostgreSQL a nondeterministic `und-u-ks-level1`
collation (case- and accent-insensitive, MySQL `ai_ci` semantics), opt-in
per connection through a `dblocale` key, under a collation named
`manager_collation`, with MySQL/MariaDB a no-op. It was dropped because:

- it spreads the nondeterministic restrictions (regex, `ILIKE`,
  `starts_with`, `LIKE` before 18) onto every text column, tokens and codes
  included, which must stay exact;
- accent-insensitive identity merges `jose` and `José` into one account;
- it moves PostgreSQL toward MySQL's lax default instead of making every
  engine explicit, and leaves MySQL/MariaDB projects with no declared
  semantics at all.

Its probes and the forge-transport, ensure/validation and alter-path
mechanics were kept; only the scope, the default strength, the key name and
the collation name were superseded. The identity proposal "Identity lookups
rely on MySQL's case-insensitive collation" was narrowed the same way: kept
the per-column mechanism, duplicate checks, lockout, the Ion Auth fix and the
`username` key; dropped its five columns (`first_name`/`last_name` stay
exact and are searched with `like_ci()`), its `und-u-ks-primary` strength,
its MySQL/SQL Server no-ops and its "no CI search" exclusion.

## Strength and values

- **Case-insensitive, accent-sensitive** on every engine: PostgreSQL
  `und-u-ks-level2`, MySQL `utf8mb4_0900_as_ci` (the only language-neutral
  `_as_ci`), MariaDB `utf8mb4_uca1400_nopad_as_ci`. These are
  recommendations: no shape check runs on a value; the framework guards only
  against a missing one.
- **NO PAD on MariaDB**, for parity with MySQL and PostgreSQL on trailing
  spaces (`'a' = 'a '` and a unique key on `'x '` after `'x'`).
- **Exact defaults are linguistic, not binary:** `utf8mb4_0900_as_cs` /
  `utf8mb4_uca1400_nopad_as_cs`. `bin` sorts by code point. Accepted:
  `as_cs` equates canonical equivalents and ignorables, where PostgreSQL's
  deterministic default keeps them distinct.
- **The bundled PostgreSQL cluster initializes with ICU `und`:** the pinned
  Alpine image's libc default sorts exactly like `"C"`. New volumes only.
- **Recommended minimums** MySQL 8.4 and MariaDB 10.11 (the LTS lines; the
  names exist from 8.0 / 10.10, only 8.4 and 12.3 were observed). PostgreSQL
  18 is required: plain `LIKE` on a nondeterministic column errors on 17.
- Language-specific collations are the project's call; the exact and CI
  values move together. Accepted: new MySQL/MariaDB `ENUM` columns are exact.

## Builder API

- **A boolean, not a strength enum:** strength lives in config, so one flag
  means "use the connection's CI collation".
- **Types:** the five text types only. `Uuid` (PostgreSQL refuses
  `UUID COLLATE`) and `Enum` (canonicalizes on MySQL's native `ENUM`, only
  compares loosely on the `VARCHAR` engines) throw on every engine.
- **SQLite `NOCASE`, ASCII only:** truly CI, if partially, is not refused.
- **SQL Server throws:** a `COLLATE` that cannot be verified is not emitted.
  So `dbcollatci` is MySQL/MariaDB-only.
- **PostgreSQL collation name `manager_case_insensitive`**, quoted, in
  `current_schema()`, permanent once shipped (columns bind by OID; a rename
  would create a second, incomparable collation). Rejected:
  `manager_collation` (does not say CI), `manager_ci` ("ci" reads as
  CodeIgniter), `mgr_case_insensitive` (`mgr_` is the PHP prefix).
- **Ensure on every CI field, no cache:** `icu_validation_level = error`
  with RESET in `finally`, `IF NOT EXISTS`, then a `pg_collation` definition
  check with no canonicalization. A changed locale throws "different
  definition".
- **COLLATE travels inside `type`** (with the length), because the forge
  appends the length right after the type and drops unknown keys. A
  `base_type` key keeps `modify_column_cast()` casting to the unconstrained
  base type (a cast to `VARCHAR(n) COLLATE …` truncates silently). Rejected:
  a forge-side `_process_fields()` override per engine, with no raw-array
  caller.

## Config keys and their arrival

- `dbcollatci` / `dblocaleci`, env `DB_COLLATION_CI` / `DB_LOCALE_CI`, read
  with `mgr_env(…, '')`: a required read would fail every request and every
  engine that does not use the key.
- **The loud failure fires at the use site** (a CI field, a MySQL/MariaDB
  `like_ci()`) and names the config key and the connection's database, not
  an env variable: a project can have any number of connections, each
  reading its own env names.
- **No auto-fill:** `mgr_apply_pdo_dsn()` cannot tell `DB_` from `DB_SEC_`,
  and a project may own its copy. Arrival is the upgrade note plus the loud
  failure.
- **Legacy MySQL/MariaDB** set the CI key to their identity columns' current
  collation (a semantic no-op) or skip v4 with `version_set` (losing only the
  `username` key). **Legacy PostgreSQL** has no no-op: a CI column must be
  nondeterministic. Accepted: cross-collation `=` raises ERROR 1267 on
  MySQL/MariaDB, so legacy projects keep their collations and are warned.

## Identity migration

- **Order:** transaction start → build fields → duplicate check →
  `modify_column()` without `unique:` → `add_index(unique: true)` → the two
  `login_attempt` indexes → end. On MySQL `MODIFY … UNIQUE` on a keyed
  column adds `email_2`.
- **`login_attempt` indexes:** `(login, ip_address)` serves the lockout
  reads (the prefix covers `trackLoginIpAddress` off); `(time)` serves the
  `OR time < …` arm of `clear_login_attempts()`, which runs on every
  successful login and is ungrouped on purpose. Either alone leaves that
  delete a full scan (measured on a 1M-row table: 64 / 276 / 244 ms on
  PostgreSQL / MySQL / MariaDB, 1 / 12 / 9 ms with both). Rejected:
  `(login, ip_address, time)` — no faster lookups, ~20% larger, and a sort
  step for the last-attempt read on MySQL/MariaDB. Built after the `login`
  collation change, so the table is rebuilt once.
- **Nullability re-stated, never loosened:** `username` false, `email` false,
  `login` true. `v4` also restates `user.ip_address`, `user.created_on` and
  `login_attempt.ip_address` NOT NULL, behind a NULL-row gate — v2 had left
  all four nullable on MySQL/MariaDB. The builder-level default that closes
  it is in `07-database-drivers/decisions.md` ("an unspecified `nullable` …
  is NOT NULL").
- **Duplicate report:** `table.column`, group count, ids per group (first
  20), never values; NULLs ignored. One column per run.
- **`down()`** drops only the `username` key and restates the columns
  without the flag. It keeps `user_email_key` (after the forge fix it is v3's
  on a fresh install) and the collation object (unused, reused by a later
  `up()`).
- **SQLite best effort:** `modify_column()` returns `FALSE` there, so the
  columns stay exact.

## Seams

- **Forge subclass via `MGR_Loader::dbforge()`** (chosen over a
  builder-owned wrapper, which would not cover plain `dbforge->modify_column()`,
  and a Composer patch). It delegates to the vendor loader and derives the
  `MGR_DB_*` name from the vendor class, so no vendor resolution logic is
  copied (the vendor's unassigned-`$class` quirk is never reached); cost: one
  throwaway vendor forge object per load. Open to any engine. The unique
  index is a plain index, not a constraint, so `drop_index()` drops it; its
  name rule is copied from `_index_name()` with reciprocal keep-in-sync
  notes, and agrees only while `dbprefix` is empty (the proposal "Migration
  builder DDL and Forge-derived names diverge under `dbprefix`").
- **DDL transaction pair in the builder, PostgreSQL only:** migrations carry
  no engine branch; MySQL/MariaDB DDL commits implicitly, so a rollback would
  undo nothing.
- **Rollback in `MGR_Migration::version()`** (rejected: per-migration
  try/finally). A direct `up()` call outside the runner is not covered.
- **Ion Auth seam A:** behavior-neutral extractions in the `BE_` fork,
  behavior in `MGR_Ion_auth_model`. The new lookups fire no `extra_where`
  hook (zero listeners exist; the unique keys are global).
- **CI search fragment builder in the db-function helper,** not a query
  builder method: vendor `DB.php` hardcodes `class CI_DB extends
  CI_DB_query_builder`, so there is no seam short of a patch. `LIKE_CI` in a
  join ON throws: CI3's `join()` re-quotes the fragment's identifiers. The
  generalization is the proposal "Case-insensitive LIKE (`LIKE_CI`) inside a
  join's ON clause".
- **MySQL/MariaDB `like_ci()` arm is `COLLATE <dbcollatci>`, not `LOWER()`:**
  same semantics as a CI column, and it matches Greek final sigma, which
  `LOWER()` misses. No `LOWER()` fallback on a missing key. No
  `not_like_ci` variants (no caller).

## Accepted limitations

- `end_ddl_transaction()` does nothing when `trans_enabled` is off (no
  caller turns it off).
- Repeating `modify_column(unique: true)` on MySQL/MariaDB adds a second
  index; no shipped migration does it, and the skill teaches `add_index()`.
- MySQL/MariaDB partial-failure windows (ERROR 1267 on the sample
  `Login_attempt` join while `email` and `login` differ; a table rebuild)
  are documented, not coded around.
- A project `MY_Loader` that does not extend `MGR_Loader` misses the forge
  seam (assumed impossible on 2.x; not verified).
