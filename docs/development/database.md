# Database drivers — design decisions

> Scope: this repo's database driver rationale — decisions, evidence,
> revisit conditions. For picking an engine or running over PDO, see
> `sample/docs/development/database.md` (the shipped operational twin,
> beside `docker.md`).

One-time rationale with evidence, kept compact.

**Database extensions: `mysqli`/`pgsql` ship by default; `pdo_*` is opt-in.**
Decision: the Dockerfile installs `mysqli` (also covers the `mariadb`
profile — same wire protocol) and `pgsql`; `pdo_*` extensions aren't in the
default image.
Why: which driver a project runs is a per-project choice, not a verdict on
PDO — `DB_DRIVER=pdo/<engine>` is a fully supported CI3 driver, measured at
performance parity with its native counterpart through this framework's
Model layer (1.03-1.08x, within run-to-run noise).
`sample/docs/development/database.md`'s PDO section has the fetch-type and
config differences that actually distinguish the two paths. Native ships
by default because it matches CI3's long-standing stringify-everything
contract, which is what every existing project's API clients expect; a
project that wants PDO's typed fetches, or is starting fresh with no
contract to preserve, adds the one subdriver it needs and rebuilds. Parity
holds only because `mgr_apply_pdo_dsn()` sets
`PDO::ATTR_EMULATE_PREPARES => true`, scoped to the `pgsql` subdriver —
without it, `pdo_pgsql` prepares every statement server-side and measured
~1.65-2x slower; `pdo_mysql` already defaults the option on, which is why
MySQL needed no equivalent override.
Evidence: `docs/design/07-database-drivers/driver-matrix-benchmark.md`
(native-vs-PDO timings, all three engines) and `driver-matrix-types.md`
(fetch-type matrix) — both framework-side only, not shipped.
Cost: adding a PDO subdriver is one line in `docker-php-ext-install` plus a
rebuild. `pdo/*` is deliberately not an engine-matrix row
(`framework-workflow.md`'s "Cross-engine verification"), so day-to-day
verification stays on the native drivers either way. `mgr_apply_pdo_dsn()`
and `fromCI()`'s `subdriver` param work the same regardless of which driver
a project runs. This mechanism and its evidence are deliberately NOT
duplicated into the shipped twin beyond a one-line why — same split as
`docker-decisions.md`'s "Docker docs ship with the stack" entry.
Revisit when: nothing here is a one-way door — a project's needs, or the
framework's own default, can move either way.
