# Case-sensitive defaults with explicit case-insensitive semantics

New projects compare text exactly — case- and accent-sensitive — on every
engine. Case-insensitivity exists only where a column declares it or a query
asks for it. The framework's identity columns (`user.username`,
`user.email`, `login_attempt.login`) declare it, so login, registration
checks and lockout match case variants on every engine, with stored case
kept.

## The problem

Identity lookups relied on MySQL/MariaDB's case-insensitive default
collations. On PostgreSQL, which compares exactly, a user typing
`Admin@x.com` could not log in as `admin@x.com`, `register()` let `Bob@x.com`
in beside `bob@x.com`, and case variants of one login split the lockout
counter. A fresh PostgreSQL install also had no unique key on `user.email`:
CI3's PostgreSQL forges silently drop `unique` in `modify_column()`, so v3's
key never existed there. No engine had a key on `username`. This blocked
making PostgreSQL the default engine.

## What was built

- **Builder:** `field(..., case_insensitive: true)` on `Char`/`VarChar`/
  `Text`/`MediumText`/`LongText` folds the connection's CI collation into
  the type: MySQL/MariaDB `COLLATE <dbcollatci>`; PostgreSQL the
  nondeterministic ICU collation `manager_case_insensitive`, created and
  validated from `dblocaleci`; SQLite `COLLATE NOCASE`. SQL Server and any
  other type throw. An empty setting throws. The collation survives
  `modify_column()` and `modify_column_cast()`.
- **Builder helpers:** `assert_no_duplicates()` (stop-and-report before a
  unique key or a CI conversion), and `start_ddl_transaction()` /
  `end_ddl_transaction()` (PostgreSQL only).
- **Forge seam:** `MGR_Loader::dbforge()` resolves
  `system/core/MGR/Forge/MGR_DB_*_forge.php` when present. The PostgreSQL
  forges append `CREATE UNIQUE INDEX IF NOT EXISTS {table}_{col}_key` on a
  `modify_column(unique)`.
- **Runner:** `MGR_Migration::version()` rolls back open transactions before
  rethrowing; `MGR_Migration_module_lib`'s version lookup no longer replaces
  `$CI->db`, which had split a migration across two connections.
- **Identity migration** `20261014120000_Manager_ion_auth_v4`: transaction
  start, build the three CI fields (so every loud failure fires first),
  duplicate check on `username` and `email`, `modify_column()` without
  `unique:`, `add_index(unique: true)` on both, `add_index()` on
  `login_attempt (login, ip_address)` and `(time)`, transaction end.
- **Ion Auth:** `register_conflict()` / `update_conflict()` seams in the
  `BE_` fork; `MGR_Ion_auth_model` adds the email check and excludes the
  user's own row.
- **Search:** `mgr_build_like_ci()` (PostgreSQL `ILIKE`, MySQL/MariaDB
  `COLLATE <dbcollatci> LIKE`, SQLite `LIKE`, fallback `LOWER()` both sides,
  all `ESCAPE '!'`); `MGR_Model::get_all_like_ci()` / `get_all_or_like_ci()`;
  `MGR_Model_Dyn_clause::LIKE_CI` / `OR_LIKE_CI` (WHERE only).
- **Scaffold:** `dbcollatci` / `dblocaleci` keys and env templates; exact
  `DB_COLLATION` values; compose `--collation-server` and PostgreSQL
  `POSTGRES_INITDB_ARGS`; the admin user search uses `OR_LIKE_CI` on
  `first_name`/`last_name`; the login-attempt list total counts groups.
- **Tests:** `sample/tests/unit/migrations/` (Migrations suite) and CI-search
  cases in the Models suite.

## Final state

| Engine | Exact default | CI collation | Unique keys on `user` |
|---|---|---|---|
| PostgreSQL 18+ | cluster ICU `und` (new volumes) | `manager_case_insensitive` = `und-u-ks-level2`, nondeterministic | `user_username_key`, `user_email_key` |
| MySQL 8.4+ | `utf8mb4_0900_as_cs` | `utf8mb4_0900_as_ci` | `username`, `email` |
| MariaDB 10.11+ | `utf8mb4_uca1400_nopad_as_cs` | `utf8mb4_uca1400_nopad_as_ci` | `username`, `email` |
| SQLite | BINARY | `NOCASE` (ASCII only) | exact keys (`modify_column()` unsupported) |
| SQL Server | instance default | refused | — |

Where it lives for a reader: `system/docs/upgrading/next.md` (upgrade path),
`sample/docs/development/database.md` "Case sensitivity" (the model and the
engine caveats), the mgr-migrations, mgr-models, mgr-auth and
mgr-helpers-libraries skills (the API). Evidence: `collation-matrix.md`
beside this file. Choices and their reasons: `decisions.md`. Validation:
`review.md`.
