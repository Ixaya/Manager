# Environment & configuration resolution

Where every configuration value comes from and which source wins: the boot
sequence that loads the env layer, how `mgr_env()` and the config files
resolve a key, and the two traps — an empty value is not a missing one, and
quotes are stripped. Read this before adding a configuration key or debugging
a value that is not what you expect.

## Entry point

All HTTP and CLI requests route through `public/index.php`, which boots the
env layer before CodeIgniter:

1. `CI_ENV` (if set in the process environment) selects which env file
   `Env_lib::load()` reads — `.env.{CI_ENV}` / `.env.{CI_ENV}.priv` if
   present, else the plain `.env` / `.env.priv` fallback.
2. Real process environment variables (e.g. Docker's `env_file:` injection)
   always win over file values.
3. The app's actual `ENVIRONMENT` is
   `mgr_env('APP_ENV') ?? $ci_env ?? 'production'`.

`CI_ENV` itself is legacy — just a mode/file selector (e.g. `dev` for a
server, unset for local). Set `APP_ENV` directly instead when possible.

## Environments

`ENVIRONMENT` decides two things: whether errors are shown, and which
dev-only endpoints exist. Configuration values do not depend on it (see
Configuration below).

|`APP_ENV`|Errors shown|`db_debug`|Use|
|---|---|---|---|
|`local`|Yes|On|A developer's own machine and the PHPUnit run|
|`development`|No|Off|An online dev server, such as one a frontend team builds against|
|`testing`|No|Off|Automated runs that must behave like production|
|`production`|No|Off|Live instances|

Only `local` discloses. Everywhere else a failed request answers with the
generic error and the detail goes to the log. An unset `APP_ENV` and
`CI_ENV` resolve to `production`; any name not in the table stops the boot
with a 503.

**`local` is never reachable from outside and never holds data derived from
production.** It returns the database driver's message and the failing SQL
to the caller, so whoever can reach it can read what it holds. An instance
that other people can reach is `development` or stricter.

`development` is production's posture with dev-only endpoints enabled. It
fails queries the way production does (`db_debug` off), so a missing result
check shows up there rather than after release.

### Dev-only endpoints

A controller that must exist only on developer and dev-server instances
checks an allow-list after `parent::__construct()`, in addition to the
normal auth check:

```php
if (!in_array(ENVIRONMENT, ['local', 'development'], true)) {
    log_message('error', 'Dev-only endpoint hit in ' . ENVIRONMENT);
    $this->response(['status' => 0, 'message' => 'Not found'], REST_Controller::HTTP_NOT_FOUND);
}
```

Never test for `production` alone: `testing`, a typo or a name added later
would pass. The sample's smoke controllers (`docker/php/smoke/`) allow
`local` alone.

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

`manager/tools/env_check` reports raw, pre-strip byte lengths, but its
`set`/missing verdict follows the same resolution as `mgr_env()` — a
quoted-empty required key still triggers the missing-key warning.
