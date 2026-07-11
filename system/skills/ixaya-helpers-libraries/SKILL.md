---
name: ixaya-helpers-libraries
description: Use when needing utility functions (hashing, env vars, dates/timezones, file paths, mime types, pagination, cross-DB SQL functions) or framework libraries (file upload, S3, JWT, email, background exec, websockets, Excel), or when CREATING a new library in this codebase. Maps what the ixaya/manager package already provides so you don't reimplement it with PHP primitives, and teaches the library authoring conventions (naming, CI access, config modes).
---

# Ixaya Helpers & Libraries

Before writing a utility function or pulling a Composer package, check this map —
the framework probably ships it. Helpers are global functions (`mgr_*` prefix);
libraries are classes loaded via `$this->load->library('{name}')`.

Everything resolves through `$autoload['packages'] = [MGRPATH . 'package']` in
`application/config/autoload.php` — the vendor package is a CI package path, so
its helpers/libraries load exactly like application ones.

## Helpers

Source: `vendor/ixaya/manager/system/package/helpers/manager_{name}_helper.php`.

**Autoloaded (always available, never load them):** `manager_helper`,
`manager_file_helper`, `manager_mime_helper`, `manager_timezone_helper`,
`manager_db_driver`, `manager_db_function` — plus `manager_env_helper`, required
by `public/index.php` at boot. The rest need
`$this->load->helper('manager_time')` etc.

| Helper | Key functions |
|---|---|
| `manager_helper` | `mgr_provided($v)` — "has a usable value" check (use instead of ad-hoc `!empty()` chains); `mgr_generate_hash($len)`, `mgr_format_hash`/`mgr_unformat_hash` (folio display); `mgr_process_exception($e, $context)` — standard exception logging; `mgr_cache_key($prefix, $params)`; `mgr_build_limit_page($limit, $page)` and `mgr_build_order_by($col, $dir, $allowed_cols)` — pagination/ordering from request params with whitelisting; `image_url()`, `secure_url()` |
| `manager_env_helper` | `mgr_env($key, $default)` + typed variants `mgr_env_bool/int/float/array/json`, `mgr_env_strict` — ALWAYS use these for environment variables, never `getenv()`/`$_ENV` |
| `manager_time_helper` (load it) | `mgr_get_date_option($option, $date)` / `_obj` / `_unix` — named ranges like today/yesterday/month starts; `mgr_create_date_time($string, $format): ?DateTime`; `mgr_to_unix($date)` |
| `manager_timezone_helper` | `mgr_date_default_timezone_set($tz)`, `mgr_get_time_zone_offset($tz)`, `mgr_get_now_date_time($tz): DateTime` |
| `manager_file_helper` | `mgr_file_path()` / `mgr_private_file_path()` — public vs private storage roots; `mgr_clean_file_path()`, `mgr_clean_file_s3_path()`; `mgr_get_temp_upload_path*($field)` — `$_FILES` handling |
| `manager_mime_helper` | `mgr_file_extention()`, `mgr_mime_extention()`, `mgr_detect_mime_from_file/data()` — never trust client mime types |
| `manager_db_driver` | `MgrDriver` enum (`MySQL, MariaDB, Postgres, SQLServer, SQLite`): `MgrDriver::fromCI($db->dbdriver)`, `->isMysqlFamily()`, `->supportsUnsigned()` — use for any driver-conditional SQL |
| `manager_db_function` | `MgrFunctionType` enum (`FromUnixtime, ToUnixtime, Now, DateFormat, DateDiff, Round, Floor, Ceil, Abs`) + `mgr_build_function()` / `mgr_build_field_select()` — cross-engine SQL functions in SELECTs; models wrap these as `$this->build_field_select()` (see ixaya-models) |
| `manager_spreadsheet_helper` (load it) | `mgr_sheet_*` — cell refs, ranges, sum/avg rows, fills, fonts for PhpSpreadsheet exports |
| `manager_assets_helper` (load it) | `add_css_fontawesome5/6($items)` |

## Libraries

Load by the **unprefixed name**: `$this->load->library('async_exec_lib')` →
`$this->async_exec_lib`. The unprefixed classes are thin aliases in
`vendor/ixaya/manager/system/package/libraries/`; implementations are the `MGR_*`
classes in `vendor/ixaya/manager/system/libraries/` (read those for signatures).
To customize one for this app, create `application/libraries/{Name}.php` extending
the `MGR_*` class — same pattern the aliases use. Load in the method that uses the
library, not the constructor (see ixaya-rest-controller).

| Library | Purpose |
|---|---|
| `async_exec_lib` | Fire-and-forget background execution of controller URIs or module library calls (see ixaya-cli-modules). Use instead of `exec()` |
| `upload_lib` | File/image uploads: validation, unique naming, resizing/thumbnails (`upload_image` with `$resolution`), `put_file` for generated content, `display_image`. Controllers already proxy it: `$this->upload_file()`, `$this->upload_image()`, `$this->get_file_base64()` on any `MY_Controller` descendant (REST included) |
| `attachment_lib` | Uploads tied to DB records: stores files AND rows in the `attachment` table keyed by `(model_name, model_hash)` — use when a file belongs to an entity |
| `amazon_aws_lib` | S3: `upload_file`, `upload_data`, `get_file`, `save_file`, `get_presigned_url`, `list_files`; multiple configs via `set_config_key()` |
| `jwt_lib` | `generate_token($user_id, $aud, $scopes, $extra)` / `decode_token($token, $aud)`; config-keyed secrets via `set_config_key()` |
| `mailing_lib` | Themed email sending: `send_email($to, $data, $subject, $view)` renders a mailing view (module `mailing`); `set_theme()`, BCC support, `$view_only` for previewing |
| `websocket_lib` | amphp-based WebSocket server (`serve()`) + `generateLink($user_identifier, $channel)` for signed client URLs (JWT-authed) |
| `env_lib` | Loads `.env` / `.env.priv` at boot — you interact via `mgr_env*()`, not this class |
| `migration_module_lib` | Per-module migration plan/run/version API — used through `manager/tools` (see ixaya-migrations) |
| `ion_auth` | Authentication/groups (CI3 Ion Auth): `logged_in()`, `login()`, `register()`, `user()`, `in_group()`, `is_admin()`, `activate()/deactivate()`, `add_to_group()/remove_from_group()`, `delete_user()`, `clear_login_attempts()` |
| `format`, `seeder` | REST output formatting (used internally by `response()`); DB seeding base class for `application/database/seeds/` |

Library configuration lives in `vendor/ixaya/manager/system/package/config/`
(`lib_mailing.php`, `lib_amazon_aws.php`, `lib_jwt.php`, `lib_websocket.php`,
`ion_auth.php`, `rest.php`, …) — all env-var driven; apps override values via
environment variables (see the `.env` samples), or shadow a config file entirely
by creating one of the same name in `application/config/`. Framework-level toggles
(`migration_db`, `languages`, `rest_time_zone`, `cache_enable`) are in
`package/config/manager.php`. Reference these, don't edit vendor copies.

## Framework-provided models

The package ships ready-made models in `vendor/ixaya/manager/system/package/models/`
— load them like any model (`$this->load->model('manager_option')`), don't recreate
their tables:

| Model | Purpose |
|---|---|
| `manager_option` | **Key/value app settings** — `get_value($key, $default)` / `save_value($key, $value)`. Use for persisted app state (sync cursors, feature flags) instead of inventing one-off tables; the cron jobs use it this way |
| `rest_key_model` | API key issuance/lifecycle (see ixaya-rest-controller) |
| `rest_user` | REST group/level checks: `validate_group()`, `get_highest_level()` — used by the framework's `_remap()` gating |
| `attachment` | Rows behind `attachment_lib` |
| `domain` / `theme` | Per-domain theming/redirects (web layer) |
| `ion_auth_model` | Ion Auth internals — prefer the `ion_auth` library API |

Caching is CI3's cache **driver**, not a library:
`$this->load->driver('cache')` → `$this->cache->get/save($key, $value, $ttl)`.
A Redis driver ships in `vendor/ixaya/manager/system/libraries/MGR/Cache/`
(app-side copy under `application/libraries/Cache/`); pair with `mgr_cache_key()`.

## Creating a new library

Exemplars to copy from:
`vendor/ixaya/manager/system/libraries/MGR_Amazon_aws_lib.php` +
`system/package/config/lib_amazon_aws.php` (multi-profile config), and
`system/libraries/MGR/Migration.php` + `system/package/config/migration.php`
(plain config read).

### Naming contract

- Library file/class end in `_lib`: `application/libraries/Payment_lib.php` →
  `class Payment_lib` (module-level: `application/modules/{module}/libraries/`).
- Its config file starts with `lib_`: `application/config/lib_payment.php`.
- Every config value comes from `mgr_env()` with a sane default — never `getenv()`,
  never hardcoded secrets.

### Accessing the CI instance

Do NOT add the magic proxy `public function __get($var) { return get_instance()->$var; }`
— some older MGR libraries still carry it; don't copy it (it hides dependencies and
breaks static analysis). Instead:

```php
// CI used extensively across the class -> keep a property
protected $CI;
public function __construct()
{
    $this->CI = &get_instance();
}
// ...later: $this->CI->load->model('billing/invoice');

// CI used once or twice -> grab it locally, right where it's needed
$CI = &get_instance();
$path = $CI->config->path('lib_payment');
```

### Config mode A — multi-profile (external services, multiple accounts/tenants)

Config file declares an active profile plus one block per profile; common values
can sit alongside:

```php
// application/config/lib_payment.php
$active_config = 'default';
$config['default']['api_key'] = mgr_env('LIB_PAYMENT_API_KEY', null);
$config['default']['sandbox'] = mgr_env_bool('LIB_PAYMENT_SANDBOX', true);
// more profiles: $config['client_b']['api_key'] = mgr_env('LIB_PAYMENT_B_API_KEY', null);
```

The library resolves the file path, `include`s it into **local scope** (never into
CI's global config array), loads the active profile into typed properties, and
exposes `set_config_key()` for runtime switching:

```php
class Payment_lib
{
    protected array $config;
    protected string $config_key;
    protected string $api_key;

    public function __construct()
    {
        $file_path = get_instance()->config->path('lib_payment');   // env-aware resolution
        if ($file_path === null) {
            show_error('The configuration file lib_payment.php does not exist.');
        }

        include($file_path);   // $active_config and $config are now local vars

        if (!isset($this->config_key) && isset($active_config)) {
            $this->config_key = $active_config;
        }
        $this->config = $config ?? [];

        if (!empty($config[$this->config_key])) {
            $this->load_config($config[$this->config_key]);
        }
    }

    public function set_config_key(string $key)
    {
        if (!empty($this->config[$key])) {
            $this->config_key = $key;
            $this->load_config($this->config[$key]);
        }
    }

    protected function load_config(array $config)
    {
        $this->api_key = $config['api_key'] ?? '';
    }
}
```

### Config mode B — plain read (flat internal options)

For a single flat option set, read the array directly with the MX config `read()`
— it returns the file's `$config` **without merging it into the global config
array**. Never `$this->config->load('lib_payment')` — global loading risks key
collisions and leaks library options into every context:

```php
$base_config = get_instance()->config->read('lib_payment');   // ?array, not merged
$config = array_merge($base_config, $overrides);               // runtime overrides win
```

Pick mode A when the library talks to an external service that may need multiple
accounts/profiles; mode B when it's a flat set of internal options.

## Anti-patterns

```php
getenv('DB_HOST');                    // WRONG — use mgr_env('DB_HOST')
exec("php index.php reports/sync/full"); // WRONG — use async_exec_lib->cli_run_uri()
move_uploaded_file(...);              // WRONG — use upload_lib / $this->upload_file()
md5(uniqid());                        // WRONG — use mgr_generate_hash() / get_unique_hash()
"FROM_UNIXTIME(created_on)"          // WRONG in shared code — MySQL-only; use MgrFunctionType::FromUnixtime
if ($db->dbdriver == 'mysqli')        // WRONG — use MgrDriver::fromCI(...)->isMysqlFamily()
function __get($var) { return get_instance()->$var; }  // WRONG in new libraries — CI property or local $CI
$this->config->load('lib_payment');   // WRONG — include locally (mode A) or config->read() (mode B)
```
