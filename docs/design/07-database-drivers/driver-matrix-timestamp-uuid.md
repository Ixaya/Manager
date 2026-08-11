# Driver matrix — Timestamp / Uuid read-back shapes

**Source of truth for what a `Timestamp`/`Uuid` column's raw value looks like
coming back from the framework's own read path, per engine.** Every figure
here goes through `$this->rest->db->get()`/`->row()` — the same path any
model `get()`/`select()` caller goes through — not a hand-rolled
`::text`-cast or a schema-only inspection.

## What produces these rows

Two gitignored probe methods in
`sample/application/modules/probes/controllers/api/`:

| file / method | role |
|---|---|
| `Timestamp_tz.php`'s `raw_read_get()` | reads `theme.last_update` (a real, `Timestamp`-typed production column) via the model read path; inserts a row through the `theme` model first if empty, so the value is written via the real `set_alter_keys()` path |
| `Timestamp_tz.php`'s `format_iso_get()` | applies `mgr_format_date_time_iso()` to the same column's raw value |
| `Field_type_sweep.php`'s `uuid_case_get()` | builds a scratch table via `MgrFieldBuilder` with `MgrFieldType::Uuid`, inserts a mixed-case literal, reads it back the same way |

```bash
curl -s -H "X-API-KEY: $KEY" "$AGENT_BASE_URL/probes/api/timestamp_tz/raw_read"
curl -s -H "X-API-KEY: $KEY" "$AGENT_BASE_URL/probes/api/field_type_sweep/uuid_case"
```

REST endpoints, same pattern as every probe in `mgr-live-probes` — a keyless
hit confirms `401`/`403` before trusting the authenticated result.

## The matrix

Measured 2026-08-10, PostgreSQL and MySQL only (native drivers, `local`
instance, framework mode `-b -m`, engine swapped via `local.env`'s `DB_*`
block between runs). SQLite figures are inferred, not independently
re-pulled this session, on the reasoning noted per row. SQL Server figures
are code-inspection-only for this entire campaign, blocked on the open
`pdo-dblib-vendor-gaps` proposal — never live.

### `Timestamp` (`theme.last_update`)

| Engine | Raw value | Shape |
|---|---|---|
| MySQL/MariaDB | `2026-08-10 21:00:04` | naive, `Y-m-d H:i:s`, no offset |
| PostgreSQL | `2026-08-10 20:59:09-06` | offset-suffixed; 2-digit hour, no minutes, when the offset is a whole hour |
| SQLite | *(inferred)* | naive TEXT, byte-identical to whatever `mgr_get_now_date_time_sql_format()` wrote — no type-level conversion exists on this driver |
| SQL Server | *(code-inspection only)* | `DATETIMEOFFSET`'s documented default string form, e.g. `2026-08-10 20:59:09.0000000 -06:00` (7-digit fractional seconds, colon in the offset) |

### `mgr_format_date_time_iso()` applied to the row above

| Engine | Raw value | Formatted output |
|---|---|---|
| PostgreSQL | `2026-08-10 21:34:41-06` | `2026-08-10T21:34:41-06:00` |
| MySQL | `2026-08-10 21:35:24` | `2026-08-10T21:35:24-06:00` |

MySQL's naive string and PostgreSQL's offset-suffixed string converge on the
same ISO-8601 shape once run through the helper — the exact divergence it
was built to close.

### `Uuid` (scratch `MgrFieldBuilder` column, mixed-case insert
`AbC12345-1234-5678-9ABC-DEF012345678`)

| Engine | Read-back | Behavior |
|---|---|---|
| PostgreSQL (`UUID`) | `abc12345-1234-5678-9abc-def012345678` | canonicalizes to lowercase |
| MySQL/MariaDB (`CHAR(36)`) | `AbC12345-1234-5678-9ABC-DEF012345678` | round-trips exact case |
| SQLite (`TEXT`) | *(inferred)* | round-trips exact case — plain TEXT column, no canonicalization possible |
| SQL Server (`UNIQUEIDENTIFIER`) | *(code-inspection only)* | Microsoft's documented default string conversion is lowercase — if true, SQL Server agrees with PostgreSQL; MySQL is the outlier of the four |

Neither divergence has a schema-level fix on any engine; normalize at the
caller (`strtolower()` for `Uuid`) if cross-engine-identical comparison is
required. Full rationale: `decisions.md`'s "Schema type mapping" section.

## Adding SQL Server to this matrix

Blocked on `pdo-dblib-vendor-gaps` clearing — note the result there, not
here, when it does. Once unblocked: bring up the `mssql` profile, insert
through the same probe methods, and replace the "code-inspection only" rows
above with measured values.
