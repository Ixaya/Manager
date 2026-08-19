# Database drivers — design decisions

> Scope: this repo's database driver rationale — decisions, evidence,
> revisit conditions. For picking an engine or running over PDO, see
> `sample/docs/development/database.md` (the shipped operational twin,
> beside `docker.md`).

One-time rationale with evidence, kept compact.

**Database extensions: `mysqli`, `pgsql`, `pdo_mysql` and `pdo_pgsql` all
ship; `pdo_dblib` deliberately does not.**
Decision: the Dockerfile installs both native extensions (`mysqli` also
covers the `mariadb` profile — same wire protocol) and both PDO subdrivers,
because `pdo/mysql` is the shipped default for a new project and a default
cannot depend on an extension the image lacks. SQL Server is excluded on
purpose: FreeTDS is the only subdriver buildable on this Alpine base, and it
cannot complete this framework's own migrations against unfixed vendor bugs,
so shipping it would advertise support that does not exist. The compose
`mssql` profile stays for whoever resolves that, with a comment saying the
image has no driver.
Why: `DB_DRIVER=pdo/<engine>` measures at performance parity with its native
counterpart through this framework's Model layer (1.03-1.08x, within
run-to-run noise), and returns real `int`/`float`/`bool` instead of CI3's
stringify-everything inheritance — the better contract for an API and its
consumers. Native stays fully supported, but note what it is now *for*: a
project keeping its string contract can do that on PDO with
`PDO::ATTR_STRINGIFY_FETCHES`, so the reasons left for native are a host
without the subdriver, and a project wanting the exact client behavior it
already runs rather than one this framework has equalized difference by
difference. Parity holds only because `mgr_apply_pdo_dsn()` sets
`PDO::ATTR_EMULATE_PREPARES => true`, scoped to the `pgsql` subdriver —
without it, `pdo_pgsql` prepares every statement server-side and measured
~1.65-2x slower; `pdo_mysql` already defaults the option on, which is why
MySQL needed no equivalent override.
Evidence: `docs/design/07-database-drivers/driver-matrix-benchmark.md`
(native-vs-PDO timings, all three engines) and `driver-matrix-types.md`
(fetch-type matrix, including the `STRINGIFY_FETCHES` compatibility
columns) — both framework-side only, not shipped.
Cost: adding any other engine's subdriver is one line in
`docker-php-ext-install` plus a rebuild. `pdo/*` is still not a routine
engine-matrix row (`framework-workflow.md`'s "Cross-engine verification") —
the default flip does not double every DB fix's verification surface;
exercise a PDO variant when a change touches that path or is asked for by
name. `sample/docs/development/database.md`'s PDO section carries the
fetch-type and config differences a project actually needs; the mechanism
and evidence above are deliberately NOT duplicated there beyond a one-line
why — same split as `docker-decisions.md`'s "Docker docs ship with the
stack" entry.
Revisit when: nothing here is a one-way door — a project's needs, or the
framework's own default, can move either way. The excluded subdriver
returns as part of whichever fix resolves it, not before.

**`MgrFieldType::Timestamp` maps to SQL Server's `DATETIMEOFFSET`, never its
own `TIMESTAMP` keyword.**
Decision: campaign 22 (2026-08-10) repointed `Timestamp` from a bare
`TIMESTAMP` literal on every engine to PostgreSQL `TIMESTAMPTZ` / SQL
Server `DATETIMEOFFSET`.
Why: SQL Server's `TIMESTAMP` keyword is not a datetime type at all — it is
a `ROWVERSION` synonym, a per-table monotonic write counter — so the
semantic "timestamp" this type is meant for was never reachable through
that keyword on SQL Server; `DATETIMEOFFSET` is the closest match that also
stores an explicit offset. On PostgreSQL, the bare `TIMESTAMP` this type
used to emit does not re-derive under a session-timezone shift the way
`TIMESTAMPTZ` does — confirmed live before the repoint. One side effect the
code carries no comment for: `modify_field_timestamp()`'s PostgreSQL
trigger (`NEW.{$column} := NOW()`) used to narrow `NOW()`'s native
`timestamptz` result into the then-naive `TIMESTAMP` column under an
implicit cast; with the column now `TIMESTAMPTZ`, that cast no longer
happens — nothing to fix in the trigger, just a different write path than
before.
Evidence: `docs/design/07-database-drivers/decisions.md`'s "Schema type
mapping" section; live read-back matrix in
`driver-matrix-timestamp-uuid.md` (PostgreSQL/MySQL, native drivers).
Cost: breaking change for any existing PostgreSQL/SQL-Server deployment
with live `Timestamp` columns — `system/docs/upgrading/2.3.1.md` carries the
`ALTER COLUMN` path.
Revisit when: SQL Server's `DATETIMEOFFSET` side is code-only, unvalidated
— pending `pdo-dblib-vendor-gaps`.

**An `unsigned: true` field on PostgreSQL or SQL Server is honored by
re-dispatching to the next-widest `MgrFieldType`, not by CI3's own forge
attribute.**
Decision: `MgrFieldBuilder::_resolveColumn()` widens `SmallInt`→`Int`,
`Int`→`BigInt`, `Float`→`Double`, and (PostgreSQL only) `BigInt`→`Decimal`
before resolving the column, instead of passing `unsigned` through to
dbforge for those two engines.
Why: CI3's own vendored `postgre_forge`/`sqlsrv_forge` classes carry an
`_attr_unsigned()` implementation that is a no-op on both engines — an
upstream bug, not a missing feature — so `unsigned: true` silently did
nothing on either engine before this fix (live-confirmed, campaign 22,
2026-08-10). SQL Server has no integer type wider than `BigInt`, so that
one case is capped rather than widened, documented on the enum case itself.
Evidence: `docs/design/07-database-drivers/decisions.md`'s "Schema type
mapping" section; the `unsigned-widen-noop` proposal (narrowed to the
raw-CI3-bypass case, still open).
Cost: none beyond the re-dispatch logic — no Composer patch, since the
workaround stays inside this framework's own `MgrFieldBuilder` (same
config/public-API-only approach the `mgr-helpers-libraries` skill's
"working around a gap in CI3's database layer" section documents).
Revisit when: CI3's vendored forge classes are patched upstream — unlikely,
and not blocking anything above.

**`MGR_Migration_builder::modify_column_cast()` emits the `USING` cast as raw
SQL rather than subclassing CI3's Postgres forge, and casts to the
unconstrained type.**
Decision (2026-08-16): a cross-family `modify_column()` type change (e.g.
VarChar -> TinyInt) fails on PostgreSQL with no `USING` clause — the same bug
sits in `CI_DB_postgre_forge` (native driver) and `CI_DB_pdo_pgsql_forge`
(the `pdo/pgsql` subdriver), two unrelated vendor classes. Subclassing one of
them in-process was the first design and was dropped once live-testing showed
`pdo/pgsql` never loads that class at all. Shipped as raw
`$this->db->query()` SQL instead, the pattern `add_foreign_key()`/
`add_primary_key()` already use for their own PostgreSQL gaps. The cast
targets the type without its length modifier — `col::VARCHAR`, and `TEXT`
for `CHAR`, whose bare form means `CHAR(1)` — and there is no per-call cast
override: values that don't parse as the new type are normalized by an
`UPDATE` ahead of the type change.
Why: the SQL text is identical whichever vendor class CI3 loaded, so raw SQL
needs only the resolved `field()` output to be correct on both. Dropping the
modifier from the cast leaves the length check to the column's own type: an
explicit `col::VARCHAR(5)` truncates a longer value silently, where the
`TYPE VARCHAR(5)` clause alone rejects it — this helper must not destroy data
that a plain `modify_column()` would have refused to touch. A `using`
parameter was built and then removed: `USING` is a PostgreSQL clause, so the
argument would convert nothing on the other engines, and emulating it there
with a pre-`UPDATE` diverges where it matters — the expression result has to
round-trip through the old column type (`to_timestamp(bigint_col)` has
nowhere to land), most non-trivial expressions are engine-specific anyway,
and a failed `ALTER` would leave PostgreSQL untouched but the other engines
holding rewritten, often unreversible data. The caller's own `UPDATE` is two
visible lines and behaves identically everywhere; where the framework cannot
do something on an engine it throws (`add_index()`'s `prefix_lengths`,
`add_auto_increment()`), it does not accept an argument and ignore it.
Evidence: live-tested 2026-08-16 through a probe controller on `pdo/pgsql`
and `pdo/mysql` — bare cast, `UPDATE`-then-cast on text labels, non-numeric
text still refused without one, the missing-`NULL`-backfill caveat, the
`down()` direction, and narrowing (VarChar, Char, numeric) refused with data
intact on both. Native `postgre` and MariaDB not separately exercised;
MariaDB shares MySQL's passthrough unchanged.
Cost: a value mapping is a separate `UPDATE` the migration writes itself. One
column per call, so a batch where only some columns need a cast calls this
once per casted column.
Revisit when: nothing pending.

**Generated migration class names are qualified by module
(`Migration_{Module}_{table}`), not just versioned (`_v{n}`).**
Decision (2026-08-18): `Tools::migration_file()`/`migration_path()` derive
`{Module}_{table}[_v{n}]`, prefixing the owning module, instead of a bare
`{table}[_v{n}]`.
Why: every migration class here lives in PHP's bare global namespace — the
vendored `CI_Migration::version()` resolves classes as a flat
`'Migration_'.ucfirst(strtolower($name))`, no namespace involved anywhere in
that chain, and it is not editable (Composer dependency). `Tools::migrate()`
loops every configured connection, and `MGR_Migration_module_lib::run()`
loops every module target per connection, all inside one PHP process — two
same-named migration classes from different modules or connections both
pending in one run get `include_once`'d back to back, which is a fatal
`Cannot redeclare class`, not a per-module nuisance. Module directory names
are already unique within a project, so qualifying by module makes
cross-module collision impossible by construction rather than merely
unlikely — the only lever available given no real namespace exists to
borrow. `$table_name` itself stays unqualified (`invoice`, not
`billing_invoice`) — this only affects the migration's own class/file
identity, never the SQL table name, which would be its own schema-breaking
decision.
Evidence: live-reproduced 2026-08-18 on the `local` Docker instance — two
throwaway modules each declaring `class Migration_Zzconflict`, a fresh DB
(both pending): `tools migrate` → `PHP Fatal error: Cannot redeclare class
Migration_Zzconflict`, and the crash pre-empted the real `manager` module's
own pending migrations later in the same run. Full write-up (design
alternatives considered, the naming-scheme discussion):
`docs/workspace/00-proposals/migration-versioning/spec.md`, archived to
`docs/workspace/archive/00-proposals/` once committed.
Cost: the 9 migrations already shipped under
`system/package/modules/manager/migrations/default/` predate this and are
not retroactively qualified.
Revisit when: the `manager-migrations-homologation` proposal is picked up
(or dropped) — decides whether the shipped 9 ever get qualified.
