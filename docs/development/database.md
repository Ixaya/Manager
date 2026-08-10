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
