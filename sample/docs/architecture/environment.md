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
- **Testing:** `.env.testing` (committed, non-secret) + `.env.testing.priv`
  (secrets only, gitignored). The PHPUnit bootstrap sets `CI_ENV=testing`,
  which makes the loader pick these files *instead of* `.env`/`.env.priv`.
  Process env still outranks both (e.g. `DB_HOST=127.0.0.1 vendor/bin/phpunit`
  for a host-side run against a published db port).
