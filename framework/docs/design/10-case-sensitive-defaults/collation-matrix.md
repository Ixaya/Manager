# Collation evidence (observed)

Facts observed live, kept so a later change does not re-probe them.
PostgreSQL 18.6 (ICU, `postgres:18.6-alpine3.24`), MySQL 8.4.11, MariaDB
12.3.3, a PostgreSQL 17.11 control (`postgres:17-alpine`), SQLite 3.53.4 via
`pdo_sqlite`; all as a non-superuser app role. SQL Server never ran.

## Catalogs

| Engine | Exact | CI, accent-sensitive | CI, accent-insensitive | Notes |
|---|---|---|---|---|
| MySQL 8.4 | `utf8mb4_0900_as_cs` (NO PAD), `utf8mb4_0900_bin` (NO PAD), `utf8mb4_bin` (PAD SPACE) | `utf8mb4_0900_as_ci` (NO PAD), the only language-neutral `_as_ci` | `utf8mb4_0900_ai_ci` (charset default) | no PAD SPACE `_as_ci`; `general_ci`/`unicode_ci`/`unicode_520_ci` are PAD SPACE; Spanish: `es_0900_as_cs`/`_ai_ci`, `es_trad_*`, no `es_*_as_ci` |
| MariaDB 12.3 | `uca1400_as_cs` (PAD SPACE), `uca1400_nopad_as_cs`, `bin`, `nopad_bin` | `uca1400_as_ci` (PAD SPACE), `uca1400_nopad_as_ci` | `uca1400_ai_ci` (charset default), `uca1400_nopad_ai_ci` | full `uca1400_spanish[2]_[nopad_]a[is]_c[is]` set plus MySQL's `es_0900_*`; `SHOW COLLATION` lists uca1400 names charset-less, `information_schema.COLLATION_CHARACTER_SET_APPLICABILITY` has the full names |

## Semantics (`=` / `LIKE`, then UNIQUE)

| Pair | MySQL `0900_as_ci` | MariaDB `uca1400_as_ci` | MariaDB `uca1400_nopad_as_ci` | PG `und-u-ks-level2` |
|---|---|---|---|---|
| a = A | 1 / 1 | 1 / 1 | 1 / 1 | t / t |
| jose = José | 0 / 0 | 0 / 0 | 0 / 0 | f / f |
| strasse = Straße | 0 / 0 | 0 / 0 | 0 / 0 | f / f (`STRAẞE` U+1E9E → t) |
| a = 'a ' | 0 / 0 | **1** / 0 | 0 / 0 | f / 0 rows |
| UNIQUE rejects after `a`/`x` | A | A, `'x '` | A | A |

Accent-insensitive (`0900_ai_ci`, `uca1400_ai_ci`, PG `und-u-ks-level1`):
also `jose = José` and `strasse = Straße` for `=` and UNIQUE; `LIKE` misses
`Straße` on MySQL/MariaDB but matches on PG level1. PAD SPACE collations
equate `a`/`'a '` and reject `'x '` after `'x'`.

`GROUP BY col COLLATE <target> HAVING count(*) > 1` on an exact column groups
exactly as the target's UNIQUE rejects, on all three engines (the basis of
`assert_no_duplicates()`).

## Exact defaults (ORDER BY and code-point-distinct pairs)

Set `alice Alice ALICE Ángel angel Bob bob zoe Zoe éclair eclair ñandu nube oso _x 10 9`:

| Collation | Order |
|---|---|
| MySQL `0900_bin`, MariaDB `nopad_bin`, PG libc default on Alpine (`en_US.utf8`), PG `"C"` | `10 9 ALICE Alice Bob Zoe _x alice angel bob eclair nube oso zoe Ángel éclair ñandu` |
| MySQL `0900_as_cs`, MariaDB `uca1400_nopad_as_cs`, PG `und-x-icu`, PG cluster with ICU `und` default | `_x 10 9 alice Alice ALICE angel Ángel bob Bob eclair éclair ñandu nube oso zoe Zoe` |
| Spanish (`es_0900_as_cs`, `uca1400_spanish_nopad_as_cs`, ICU `es`) | as above, except `nube ñandu` |

| Pair (1/t = equal) | `bin` | `as_cs` | `as_ci`, `ai_ci` | PG default, `"C"`, `und-x-icu` | PG level2 |
|---|---|---|---|---|---|
| `é` vs `e`+U+0301 | 0 | 1 | 1 | f | t |
| `ab` vs `a`+U+200B+`b` | 0 | 1 | 1 | f | t |
| `Å` U+00C5 vs U+212B | 0 | 1 | 1 | f | t |
| `fi` vs `ﬁ` U+FB01 | 0 | 0 | 1 | f | t |

## Cross-collation comparison

- MySQL/MariaDB: column `=` column under two different non-binary collations
  raises ERROR 1267 (`ai_ci`/`as_ci`, `ai_ci`/`as_cs`, `as_ci`/`as_cs`,
  MariaDB `unicode_ci`/`uca1400_ai_ci`). With one side `bin` it compares
  binary. Column vs literal never errors. Literal vs literal uses the
  connection collation (the client default, CI).
- PostgreSQL: no error; a default-collation column against a level2 column
  compares under level2. Two different nondeterministic collations error;
  string FKs need the identical collation.

## DDL

- MySQL/MariaDB accept `<type> COLLATE <ci>` in CREATE, ADD (VARCHAR,
  CHAR(36), ENUM, TEXT) and CHANGE; it overrides the table default. `MODIFY`
  without COLLATE reverts to the table default. `MODIFY … UNIQUE` on a keyed
  column adds `email_2`.
- PostgreSQL accepts the folded type in CREATE (with DEFAULT/NOT NULL/UNIQUE
  after it), ADD, `TYPE … COLLATE … USING col::VARCHAR`, bare `TYPE …
  COLLATE`. `UUID COLLATE` is refused. `TYPE` without COLLATE resets to the
  default. `USING col::VARCHAR(100) COLLATE …` truncated a 120-char value
  silently; `USING col::VARCHAR` rejected it.
- `CREATE COLLATION` under `icu_validation_level = error` (context `user`,
  settable by the app role): fails for `utf8mb4_0900_as_ci`,
  `utf8_general_ci`, `und-u-ks-levl2`, `und-u-ks-level9`; passes and stores
  verbatim `und-u-ks-level2`, `und-u-ks-level1`, `es-u-ks-level1`.
  `und-u-ks-secondary` is rewritten to `und-u-ks` and fails;
  `und-u-ks-primary` canonicalizes to `und-u-ks-level1` and succeeds.
  `IF NOT EXISTS` with another locale keeps the old one (NOTICE). A failed
  CREATE outside a transaction leaves the SET in place; ROLLBACK and
  `SET LOCAL` scope it.
- PostgreSQL has no `ALTER COLLATION … SET (locale)`; `DROP COLLATION`
  refuses while columns depend on it; `RENAME` is followed by every
  dependent column. The one-transaction swap procedure in the shipped
  `database.md` completed (level1 → level2) with valid indexes; a stricter
  locale made an FK re-add fail atomically.

## Search

PostgreSQL:

| Shape | 18.6 | 17.11 |
|---|---|---|
| deterministic `ILIKE … ESCAPE '!'` | CI, accent-sensitive, escapes honored | same |
| `ILIKE` on level2/level1 | ERROR "not supported for ILIKE" | same |
| `col COLLATE "default" ILIKE` on level2 | works (drops the column's collation) | — |
| plain `LIKE` on level2/level1 | works, escapes honored | ERROR "not supported for LIKE" |
| ILIKE on a cluster with ICU `und` default | works | — |

On nondeterministic columns regexes, `starts_with()`/`^@` also error;
`col COLLATE "C" ~ …` works. `=`, `IN`, `strpos`, `replace` (CI, unlike
MySQL), `DISTINCT`, `GROUP BY`, `ORDER BY`, unique keys and `left(col, n)`
indexes work.

MySQL/MariaDB on an exact column:

| Pattern vs stored | plain `LIKE` | `LOWER()` both sides | `COLLATE …_as_ci LIKE` |
|---|---|---|---|
| `%ADMIN%` vs `Admin@X.com` | 0 | 1 | 1 |
| `%JOSE%` vs `José Pérez` | — | 0 | 0 |
| `%οδος%` vs `ΟΔΟΣ` (final sigma) | — | **0** | **1** |
| `%A!_B%` / `%A_B%` / `%!%C!!%` | — | 1 / 2 / 1 | 1 / 2 / 1 |

SQLite: `=` is exact; default `LIKE` and `NOCASE` fold ASCII only
(`josé`/`JOSÉ` distinct); `upper()`/`lower()` are ASCII-only; `ESCAPE '!'`
honored; `PRAGMA case_sensitive_like = ON` makes `LIKE` exact.

## Index plans

| Shape | PostgreSQL | MySQL / MariaDB |
|---|---|---|
| `=` on a CI-declared indexed column | Index Scan | ref |
| `=` on an exact indexed column | Index Scan | ref |
| `like_ci()` `%x%` or `x%` on an exact column | Seq Scan | full index scan |
| plain `like()` `%x%` on a CI-declared column | Seq Scan | full index scan |
| plain `like()` `x%` on a CI-declared indexed column | Seq Scan | range |
| plain `like()` `x%` on an exact indexed column | Seq Scan (no pattern-ops index) | range |
| `lower(col) LIKE 'x%'` with a `varchar_pattern_ops` index on `lower(col)` | Bitmap Index Scan | — |
| MySQL functional index on `LOWER(v)` / MariaDB indexed virtual column | — | serves `LOWER(v) = …` only |

`login_attempt` carries `(login, ip_address)` and `(time)` indexes from `v4`
on every engine. The first serves the lockout reads under the CI collation
(`User123` finds `user123`); the second is what lets the successful-login
purge's `OR time < …` arm avoid a scan (PostgreSQL BitmapOr, MySQL/MariaDB
`index_merge`).
