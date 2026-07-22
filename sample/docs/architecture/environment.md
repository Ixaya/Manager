# Environment & configuration resolution

## Entry point

All HTTP and CLI requests route through `public/index.php`, which boots the
env layer before CodeIgniter:

1. `CI_ENV` (if set in the process environment) selects which env file
   `Env_lib::load()` reads — `.env.{CI_ENV}` / `.env.{CI_ENV}.priv` if
   present, else the plain `.env` / `.env.priv` fallback.
2. Real process environment variables (e.g. Docker's `env_file:` injection)
   always win over file values.
3. The app's actual `ENVIRONMENT` is
   `mgr_env('APP_ENV') ?? $ci_env ?? 'development'`.

`CI_ENV` itself is legacy — just a mode/file selector (e.g. `dev` for a
server, unset for local). Set `APP_ENV` directly instead when possible.

`ENVIRONMENT` drives error display/reporting: `development` shows errors;
`production`/`testing` hide them.

## Configuration

There are no per-environment config directories. Every value in
`application/config/*.php` resolves via `mgr_env()` / `mgr_env_int()` /
`mgr_env_bool()` — e.g. `database.php`'s `DB_HOST`, `config.php`'s
`CF_*` / `AUTH_*` / `LIB_*` keys.

Values reach the process as real environment variables:

- **Docker deployment:** injected via the compose `env_file:` mechanism
  (non-secret) and the bind-mounted `.env.priv` file (secrets) — see
  `docs/development/docker.md`.
- **Outside Docker:** root `.env` / `.env.priv` files (templates:
  `.env.sample` (full) + `.env.sample.prod` (production overlay, not a
  runtime file) / `.env.sample.priv`).
- **Testing:** `.env.testing` (committed, profile-independent config) +
  `.env.testing.priv` (the DB block — profile-dependent, gitignored; template:
  `.env.sample.testing.priv`). The PHPUnit bootstrap sets `CI_ENV=testing`,
  which makes the loader pick these files *instead of* `.env`/`.env.priv`.
  Process env still outranks both (e.g. `DB_HOST=127.0.0.1 vendor/bin/phpunit`
  for a host-side run against a published db port).

## Empty values vs defaults

Precedence: process env → `$_ENV` → merged files (`.env.priv` then `.env`) →
the caller's default, short-circuiting at the first source that mentions the
key. A present-but-empty value (blank or whitespace-only) from any source
resolves to the default rather than its literal value — the key still wins
precedence, it just doesn't resurrect a lower source. For a key where blank
is meaningful, pass `''` as the default.

`mgr_env($key, $default, $strict = true)`: `$strict` defaults to `true`,
applying the rule above. `strict=false` opts back into the verbatim empty
value, but only when `$default` is non-null (a `null` default always
normalizes to `null` — e.g. `CF_LOG_PATH`). Typed helpers
(`mgr_env_bool/int/float/array/json`) and `mgr_env_required()` always force
`strict=true`.

## Quoting

A matched pair of wrapping quotes (`KEY="value"` / `KEY='value'`) is stripped
before the empty check above, from every source. Only a genuine matching
pair is stripped — a stray or mismatched quote is left as literal data.
`KEY=""` strips to `''` and then follows the same rule as bare `KEY=`.

Docker Compose's own `env_file:` parser already strips matched quotes (and
rejects a mismatched one outright); this framework's stripping additionally
covers file-based (`.env` / `.env.priv`) and non-Compose sources.

`manager/tools env_check` reports raw, pre-strip byte lengths, but its
`set`/missing verdict follows the same resolution as `mgr_env()` — a
quoted-empty required key still triggers the missing-key warning.
