# Database drivers — decisions

Operator decisions with rationale, condensed from
`docs/workspace/19-database-pdo-integration`.

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
- **2026-08-05 (FINAL): native (`mysqli`/`postgre`) ships as the default;
  `pdo/mysql` and `pdo/pgsql` are fully supported, opt-in alternatives at
  performance parity, not fallbacks.** Native ships by default because it
  matches CI3's long-standing stringify-everything contract, which is what
  every existing project's API clients expect; a project that wants PDO's
  typed fetches, or is starting fresh with no contract to preserve, adds the
  one subdriver it needs and rebuilds. Nothing here is a one-way door — see
  the `pdo-as-default-driver` proposal for what would have to be true before
  the recommendation could flip.
- **2026-08-05: no `pdo_*` extension ships in `sample/docker/Dockerfile` by
  default.** An image installs whichever native extension it wants; testing
  a PDO path means adding the one subdriver needed and rebuilding, which is
  cheap and rarely needed given the next ruling.
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
- **2026-08-05: native Postgres's `'f'`-string-for-false is a live trap**,
  independent of any driver decision here — `'f'` is truthy in PHP. This is
  why the `mgr-migrations` skill already directs `SmallInt` over `Bool` for
  `0`/`1` flag columns; the guidance holds under PDO too and was not
  duplicated a third time.
