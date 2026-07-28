# System fixes — decisions

Operator decisions with rationale, condensed from the workspace records.
Item numbers (#1-#18) are the workspace numbering, kept for traceability.

- **2026-07-12 (#15): typo'd HELPERS get `@deprecated` wrappers; the typo'd
  HOOK class got a hard rename.** `mgr_file_extention()` /
  `mgr_mime_extention()` / `mgr_file_kind_extention()` remain as deprecated
  wrappers around the corrected spellings (consuming projects may call
  them). `MGR_Bootsrap` was renamed to `MGR_Bootstrap` with NO back-compat
  alias because the hook class never shipped in a tagged release and its
  only in-repo reference was a commented-out example. General rule
  reaffirmed: public API renames need a deprecation window; never-shipped
  code does not.
- **2026-07-12 (#13): per-property typing instead of blanket
  PHPDoc-deferral.** The consumer scan found exactly one subclass (an empty
  pass-through), so `$group_methods`/`$logged_in_level` got native types
  now; `$user_id`/`$time_zone` stayed PHPDoc-only; `$user_group` was
  deleted outright (no reader or writer anywhere — the same-named `foreach`
  variable shadows it). `$logged_in_level`'s assignment gained an `(int)`
  cast because the value is a DB-sourced numeric string.
- **2026-07-12 (#2): the model scaffold's `$module` parameter is REQUIRED,
  no default.** A default of `admin` is risky and obscure — an omitted
  module must fail loudly at the CLI rather than silently scaffold into
  `admin`. The migration scaffold heredoc emits the
  `MGR_Migration_builder` pattern, including one sample typed `field()` so
  a generated file teaches the pattern.
- **2026-07-12 (#16): "zero behavior change" waived for three latent bugs,
  explicitly opted into:** dead failure guards in `MGR_Attachment_lib`
  (`=== false` on a `?array` contract, changed to `=== null`), and the
  inverted `encrypt_name` logic in `MGR_Upload_lib::upload_file_local()` —
  an observable behavior change (default calls now actually encrypt the
  filename) accepted as correct per the API contract, blast radius called
  out. Follow-up pass: `put_file` family retyped `array` to `string $data`
  (was uncallable with a real blob), `get_file_base64()` gained a
  failed-read guard, and guarded params were documented `string|null` so
  phpstan keeps the null-guards live (deliberate pre-strong-typing state).
- **2026-07-12 (#11): cache TTL default resolves through the config layer,
  not env reads in the driver.** `Cache_redis` reads `default_ttl` from its
  own already-loaded `redis` config array; `cache.php` and `redis.php` both
  read `CACHE_DEFAULT_TTL` with the same 600 default. Gives consuming
  projects the standard CI3 config-copy override path instead of requiring
  a subclass.
- **2026-07-12 (#8): `NOT` is not portable — driver match, and SQL Server
  deliberately unhandled.** T-SQL rejects `NOT <col>` as a scalar; no
  evidence the sync method runs against SQL Server, so that branch was left
  unwritten rather than guessed at. Flag if SQL Server support for
  `sync_commit_enabled()` is ever needed.
- **2026-07-12 (#18): the redis env rename claim was stale twice over** —
  the sample env still defines `LIB_REDIS_SOCKET_TYPE`, so `redis.php`
  reading that key was correct as-is; the mid-pass "fix" was reverted.
  Lesson folded into the campaign playbook: validate the claim, then
  validate the validation.
- **2026-07-12 (`mgr_mimes_config()`, unnumbered):** found live-testing #15 —
  `$CI->config->load('mimes')` requires a `$config` variable but the
  package's `mimes.php` uses `return [...]`, so `mgr_mime_extension()`
  fataled on every call (and `get_mimes()` silently returned `[]`). New
  helper resolves the file via MX_Config's path search and captures
  `include()`'s return. Content-based detection deliberately does NOT fall
  back to the extension table (considered, declined).
- **#4 (`__get` magic proxy): deferred indefinitely** — the proxy is
  load-bearing for unknown consuming-project subclasses (every migration
  reaches dbforge through it). Any future attempt starts with a subclass
  inventory across consuming projects and keeps `__get` as a deprecated
  fallback for at least one release; never a hard removal.
- **2026-07-27 (group_methods gating, unnumbered — no campaign item, found
  via `mgr-rest-controller` homologation):** `_remap()`'s level/group gate
  used to sit behind `isset($this->_apiuser)`, so an unidentified caller
  silently skipped a configured gate while an identified caller under the
  required level got 401 — presenting a credential could leave a caller
  worse off. Fixed by moving the identification check into
  `validate_level()`/`validate_group()` themselves (fail closed on
  `$this->user_id`), keeping `_remap()`'s branching flat. Also added
  `level = 0` / `group = 'none'` as real "no gate on this axis" sentinels a
  per-method override can set to opt out of an active `'*'` wildcard —
  chosen over `null` because `isset()` can't distinguish an explicit `null`
  from an absent key, so `null` silently keeps falling back to the wildcard
  (verified live: a low-priv caller still 401s against a `null`-overridden
  method, but reaches one overridden with the `0`/`'none'` sentinels).
  `validate_access(?int $level, string|array|null $group)` keeps `null` as
  its own axis-skip sentinel regardless — a deliberately different
  convention, safe there because it takes plain values with no
  wildcard-fallback/`isset()` collision to guard against.
- **2026-07-27 (Rest_user split into Rest_user_group):**
  `Rest_user::validate_group()` / `get_user_group_names()` /
  `get_highest_level()` moved to a new `Rest_user_group` model
  (`table_name = 'user_group'`), so `Rest_user` stays a plain `user`-table
  model for ordinary CRUD (`$this->rest_user->get($id)`). The three moved
  methods stay on `Rest_user` as one-line `@deprecated` delegates —
  `Rest_user.php` predates the 2.0 tag, so dropping them outright would be a
  breaking rename with no deprecation window.
- **2026-07-27 (Rest_user_group: query builder over raw SQL):** the moved
  methods previously ran raw SQL with a MySQL-only backtick-quoted
  `` `group` `` identifier — fataled on Postgres (found live-probing the
  gate fix above). Rewritten on the CI query builder
  (`$this->my_db`/`get_all_join()`), which quotes identifiers per driver
  automatically. Considered and declined: temporarily swapping
  `$this->table_name` to reuse `get_all_join()`'s single-FROM-table shape
  (state-mutation risk without a `try/finally`), and `APP_Model_Dyn` (built
  for runtime-composed joins; this join is static, so it doesn't fit Dyn's
  own selection criteria and would only add overhead).
- **2026-07-15 (dev-bind): `-b` binds `application/` only; bind sources are
  pre-verified.** `public/` holds just `index.php` and the separately-bound
  `media/`, and a read-only `public/` bind breaks that nested mount (the
  mountpoint must pre-exist inside the parent bind's host source) — so it
  was dropped from `docker-compose.dev-bind.yml` rather than validated
  around. `docker_manage.sh` now resolves `CODE_BIND_PATH`/
  `MANAGER_BIND_PATH` the way compose does (relative to `docker/`, never
  the caller's cwd) and requires the marker subdir before compose runs,
  because Docker silently auto-creates an empty source directory for a
  wrong path. `--project-directory` was rejected: it would invert every
  existing relative path in every instance env file.
