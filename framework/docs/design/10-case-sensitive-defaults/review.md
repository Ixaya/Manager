# Case-sensitive defaults — validation record

## How it was validated

Code baselines re-read against the absorbed proposals at the start (every
vendor citation reached). Engine facts observed live on PostgreSQL 18.6,
MySQL 8.4.11 and MariaDB 12.3.3 (pinned images, non-superuser app role), plus
a PostgreSQL 17.11 control and SQLite 3.53.4 through `pdo_sqlite`. Each fix
was live-tested at the end of its batch through authenticated probes and the
CLI runner, then a final verification pass ran the whole contract on fresh
volumes at the committed tree.

## Final verification (per engine)

| Check | PostgreSQL | MySQL | MariaDB |
|---|---|---|---|
| Fresh migrate: only the three identity columns off the exact default; unique keys incl. `user_email_key` at v3; linguistic ORDER BY | ✔ | ✔ | ✔ |
| Existing schema with the old CI default: one change (the `username` key), nothing else | — (no no-op path) | ✔ | ✔ |
| Seeded case-variant duplicates: stop, report ids, nothing altered; PostgreSQL legacy state with exact duplicate emails | ✔ | ✔ | ✔ |
| REST: variant login, duplicate registration, capitalization-only update, conflicting update, one lockout counter, exact `X-API-KEY` | ✔ | ✔ | ✔ |
| Search: admin search ignores case, accents kept, wildcards literal, generic `where()`/`like()` exact, tokens exact | ✔ | ✔ | ✔ |
| Index plans | ✔ | ✔ | ✔ |
| Migrations under `db_debug` on: invalid locale, duplicate stop before the collation exists, down/up | ✔ | — | — |
| PHPStan, php-cs-fixer, sample PHPUnit (132 tests) | ✔ | ✔ | ✔ |

After the closing comment pass and the `mgr_build_like_ci()` fold, PHPStan
and php-cs-fixer ran clean and the Models + Migrations suites passed on
PostgreSQL and MySQL.

Regression coverage left behind: `CaseInsensitiveColumnsTest` (eight cases;
mutation-checked: removing `core/MGR/Forge/`, the `version()` override, or
the `base_type` cast each fails a test) and the CI-search cases in
`BaseModelTest` / `DynModelTest`.

## Never run

- SQL Server: code path only (no profile, no `pdo_dblib`).
- SQLite: the manager module cannot migrate there (pre-existing
  `modify_field_timestamp()` refusal), so the `NOCASE` fold and the
  unsupported `modify_column()` are code-path facts.
- A web request with `db_debug` on (`like_ci()` on a CI PostgreSQL column
  would show a database error page instead of returning null).
- A secondary connection group; more than 20 duplicate groups; the
  MySQL/MariaDB partial-failure window; a real pre-change data volume (the
  legacy check simulated one); MariaDB 10.11.

## Found and fixed along the way

- CI3's PostgreSQL forges drop `unique` in `modify_column()` (v3's `email`
  key never existed on PostgreSQL).
- An exact-version `migrate` ran builder and forge SQL on two connections;
  inside v4's transaction on PostgreSQL it hung.
- `Login_attempt::get_list()` reported the first group's attempt count as the
  total.
- `LIKE_CI` in a join ON emitted re-quoted, broken SQL; now refused.
