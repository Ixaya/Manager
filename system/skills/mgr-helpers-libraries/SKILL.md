---
name: mgr-helpers-libraries
description: Use when needing utility functions (hashing, env vars, dates/timezones, file paths, mime types, pagination, cross-DB SQL functions) or framework libraries (file upload, S3, JWT, email, background exec, WebSocket, Excel), or when CREATING a new library in this codebase. Maps what the framework already provides and teaches the library authoring conventions (naming, CI access, config modes) — instead of hand-rolling it with PHP primitives.
---

# Manager Helpers & Libraries

> **Prerequisite:** this skill assumes `mgr-code-style` is loaded — invoke it
> before writing any code. It owns naming, typing, PHPDoc, and the comments
> policy; this skill only covers the helper/library catalog and library
> authoring.

Before writing a utility function or pulling a Composer package, check this
map — the framework probably ships it. Helpers are global functions (`mgr_*`
prefix); libraries are classes loaded via `$this->load->library('{name}')`.

Everything resolves through `$autoload['packages'] = [MGRPATH . 'package']` in
`application/config/autoload.php` — the vendor package is a CI package path,
so its helpers/libraries load exactly like application ones.

## Helpers

Source:
`vendor/ixaya/manager/system/package/helpers/manager_{name}_helper.php`.

**Autoloaded (always available, never load them):** `manager_helper`,
`manager_file_helper`, `manager_mime_helper`, `manager_timezone_helper`,
`manager_db_driver`, `manager_db_function` — plus `manager_env_helper`,
required by `public/index.php` at boot. The rest need
`$this->load->helper('manager_time')` etc.

| Helper | Key functions |
|---|---|
| `manager_helper` | `mgr_provided($v)` — "has a usable value" check (use instead of ad-hoc `!empty()` chains); `mgr_generate_hash($len)`, `mgr_format_hash`/`mgr_unformat_hash` (folio display); `mgr_process_exception($e, $context)` — standard exception logging; `mgr_cache_key($prefix, $params)`; `mgr_build_limit_page($limit, $page)` and `mgr_build_order_by($col, $dir, $allowed_cols)` — pagination/ordering from request params, rejecting (returns `null`) a `$col` not in `$allowed_cols` instead of substituting a default; `mgr_validate_order_by($col, $allowed_cols)` is the same check alone, for validating before running a query; `mgr_validate_id($value): ?int` — a request value as a record id (integer ≥ 1, else `null`); answer 400 on `null`, look the record up, then use the id the database returns, never the request value; `image_url()`, `secure_url()` |
| `manager_env_helper` | `mgr_env($key, $default)` + typed `mgr_env_bool/int/float/array/json`, `mgr_env_strict` — ALWAYS, never `getenv()`/`$_ENV`. An empty value from any source collapses to `$default` (`strict=false` opts out when `$default` is non-null); a matched quote pair (`KEY="value"`) is stripped first, a mismatched one stays literal |
| `manager_time_helper` (load it) | `mgr_get_date_option($option, $date)` / `_obj` / `_unix` — named ranges (today, month starts…); `mgr_create_date_time($string, $format): ?DateTime`; `mgr_format_date_time_iso($raw_value)` / `mgr_format_date_time_sql($raw_value)` — normalize a `Timestamp` column's raw value (naive on MySQL, offset-suffixed on Postgres/SQL Server) to ISO-8601 / `Y-m-d H:i:s`; opt-in at response time, never automatic; `mgr_to_unix($date)` |
| `manager_timezone_helper` | `mgr_date_default_timezone_set($tz)` — after boot use it, never `date_default_timezone_set()`: it also re-sends the offset to cached DB handles; a non-identifier is logged at `error` and ignored; `mgr_get_time_zone_offset($tz)`, `mgr_get_now_date_time($tz): DateTime`, `mgr_get_now_date_time_sql_format($tz): string` — SQL-literal (`Y-m-d H:i:s`) form, used for `last_update`-style write sites |
| `manager_file_helper` | `mgr_file_path()` / `mgr_private_file_path()` — public vs private storage roots; paths are storage URIs (`media/…`, `private/…`) built by the server, never request input, and are not validated; add another root by overriding `mgr_file_path()` in `application/helpers/manager_file_helper.php`, not with `../`; `mgr_clean_file_path()`; `mgr_is_s3_path($uri)` — bool, true when `s3/` is the first segment of a storage URI (a root like `media/`, never matched later in the path; the one check every upload/read method routes by); `mgr_clean_file_s3_path()` — strips the query and a leading `s3/` to give the key; `mgr_is_allowed_file_name($name)` — overridable bool check on one stored file name (see "Desired names"); `mgr_is_allowed_file_path($uri)` — overridable bool check the two read methods (`get_file_base64()`, local `display_image()`) apply to a storage URI: false for a `..` segment, which they refuse (`null` / the empty image) and log; `strip_query: false` checks the whole string, as `load_view()` and `resolve_theme()` do for view names; `mgr_get_temp_upload_path*($field)` — `$_FILES` handling |
| `manager_mime_helper` | `mgr_file_extension($filepath, $mime_type)` — the path's own extension, else the one for the MIME type (a short corrections list first, then the first `mimes.php` key listing it: `text/plain` → `txt`, `application/zip` → `zip`, `image/jpeg` → `jpg`); `mgr_mime_extension()`, `mgr_detect_mime_from_file/data()` — never trust client mime types (the `_extention` spellings are deprecated aliases) |
| `manager_db_driver` | `MgrDriver` enum (`MySQL, MariaDB, Postgres, SQLServer, SQLite`): `MgrDriver::fromCI($db->dbdriver, subdriver: $db->subdriver ?? null)` (resolves `pdo/<engine>`); `->isMysqlFamily()`, `->supportsUnsigned()` — use for any driver-conditional SQL |
| `manager_db_function` | `MgrFunctionType` enum (`FromUnixtime, ToUnixtime, Now, DateFormat, DateDiff, Round, Floor, Ceil, Abs`) + `mgr_build_function()` / `mgr_build_field_select()` — cross-engine SQL functions in SELECTs (models: `$this->build_field_select()`, see mgr-models). `mgr_is_sql_identifier()` — validate a config/user-sourced column or `table.column` name before concatenating it into SQL (callers throw on false; CI's `escape_identifiers()` is NOT a guard, it passes parens/quotes through) |
| `manager_spreadsheet_helper` (load it) | `mgr_sheet_*` — cell refs, ranges, sum/avg rows, fills, fonts for PhpSpreadsheet exports |
| `manager_assets_helper` (load it) | `add_css_fontawesome5/6($items)` |

Every package helper function is wrapped in `if (! function_exists(...))`,
so to override one, declare just that function in a same-named
`application/helpers/manager_{name}_helper.php`; the package's other
functions still load. Never `MY_manager_{name}_helper.php` — CI3's extension
path hard-errors on a package helper.

## Libraries

Load by the **unprefixed name**: `$this->load->library('async_exec_lib')` →
`$this->async_exec_lib`. The unprefixed classes are thin aliases in
`vendor/ixaya/manager/system/package/libraries/`; implementations are the
`MGR_*` classes in `vendor/ixaya/manager/system/libraries/` (read those for
signatures). Load in the method that uses the library, not the constructor
(see mgr-rest-controller). To customize one:

- **App-wide (the default):** `application/libraries/{Name}.php` extending
  the `MGR_*` class, under a name of your own — load it by that name, not
  the alias's.
- **Project-wide, in place of the alias:** the same file under the alias's
  own name (`application/libraries/Upload_lib.php`, `class Upload_lib
  extends MGR_Upload_lib`, after a `require_once` of the `MGR_*` file). It
  wins over the package alias for every `$this->load->library('upload_lib')`
  — the controller proxies and other package libraries included — so use it
  only for a change every caller should get (the sample's WebP target).
- **Per-module**, only when modules each need their own profile, templates
  or config of the same base library:
  `application/modules/{module}/libraries/{Name}.php`, loaded from outside
  as `$this->load->library('{module}/{name}')`. `mailing_lib` is the shipped
  example (`Frontend_mailing`, `Auth_mailing`, each
  `extends MGR_Mailing_lib`).
- **Never a `MY_` file named to override the alias** (CI3's
  `subclass_prefix`, e.g. `MY_Mailing_lib.php`): it is silently never
  loaded, because `MX_Loader::library()` resolves a package library via
  `Modules::find()` before CI3's core loader — the only one honoring
  `subclass_prefix` — runs.

| Library | Purpose |
|---|---|
| `async_exec_lib` | Fire-and-forget background execution of controller URIs or module library calls (see mgr-cli-modules). Use instead of `exec()` |
| `upload_lib` | File/image uploads: validation, unique naming, resizing/thumbnails (`upload_image` with `$resolution`), `put_file` for generated content, `display_image` (serves jpeg/png/gif/webp/avif inline by detected type; any other file is sent as a download). Controllers already proxy it: `$this->upload_file()`, `$this->upload_image()`, `$this->put_file()`, `$this->get_file_base64()` on any `MY_Controller` descendant (REST included) |
| `image_process_lib` | GD image processing behind `upload_lib`, stateless and usable on its own, e.g. re-encoding stored images: `convert_image($source_path, $detected_type, $target_path, $convert_to)` (`jpg` or `webp`; see "Conversion target"), `create_thumbnail($source_path, $detected_type, $target_path, $resolution)` (a copy fitted inside the `[width, height]` box, in the same type; `false` when it cannot be built), `has_image_decoder()`, `has_image_encoder()`, `fits_pixel_ceiling()`, and the readers `is_raster_type()`, `is_convert_target()`, `convert_target_type()`, `is_thumbnail_type()` |
| `attachment_lib` | Uploads tied to DB records: stores files AND rows in the `attachment` table keyed by `(model_name, model_hash)` — use when a file belongs to an entity. Accepted types follow its public `$upload_config` property (see "Upload defaults"). The `title` and `file_name` limits (100 characters each) are the protected `$title_max_length` / `$file_name_max_length`, which a project with its own wider table sets in a subclass; `title` is stored cut to its limit (extension kept, invalid UTF-8 bytes dropped), and a row that cannot be saved throws `RuntimeException` (the stored file stays) |
| `amazon_aws_lib` | S3: `upload_file`, `upload_data`, `get_file`, `save_file` (`$file_name` is a server-chosen key that becomes a local path under `$path`; never request input), `get_presigned_url`, `list_files`; multiple configs via `set_config_key()` |
| `jwt_lib` | `generate_token($user_id, $aud, $scopes, $extra)` / `decode_token($token, $aud)`; config-keyed secrets via `set_config_key()` |
| `mailing_lib` | Themed email: `send_email($email, $data, $subject, $view)` renders a mailing view; transport is the active profile's `protocol` (`smtp`/`sendmail`/`sendgrid`). `send_template($email, $template_key, $data)` is `sendgrid`-only, `$template_key` declared in the subclass's `$templates`; `set_theme()`, `set_config_key()` |
| `sendgrid_lib` | SendGrid v3 Mail Send client: `send()`, `send_template()`, `get_template_id($key)`, `set_config_key()` — directly, or via `mailing_lib`'s `sendgrid` protocol |
| `websocket_lib` | amphp-based WebSocket server (`serve()`) + `generateLink($user_identifier, $channel)` for signed client URLs (JWT-authed) (see mgr-cache-websockets) |
| `env_lib` | Loads `.env` / `.env.priv` at boot — you interact via `mgr_env*()`, not this class |
| `migration_module_lib` | Per-module migration plan/run/version API — used through `manager/tools` (see mgr-migrations) |
| `log_prune_lib` | Age/size retention over caller-passed log directories (`prune_app`, `prune_cli`) and the REST `api_log` table (`prune_api`, given a connection and table); thresholds from `lib_log_prune.php` as public properties a caller may override — used through `manager/tools/log_prune` |
| `ion_auth` | Authentication, users and groups (see mgr-auth) |
| `format`, `seeder` | REST output formatting (used internally by `response()`); DB seeding base class for `application/database/seeds/` |

**Upload defaults.** Without an `upload_config`, `upload_file()` accepts
`gif|jpg|jpeg|png|webp|pdf|txt|csv|doc|docx|xls|xlsx|ppt|pptx|odt|ods` and
`upload_image()` accepts `gif|jpg|png|jpeg|webp`. Page types (`html`, `htm`,
`xhtml`, `shtml`, `svg`, `svgz`, `xml`, `xsl`, `js`) are refused under the
web root whatever the config. So are PHP-family extensions (`php`, `phtml`,
`phar`…), even under `allowed_types` `*`. A storage URI is on S3 only when
it starts with `s3/` (`mgr_is_s3_path()`), in every method; `media/s3/…` is
a local path. `s3/…` paths apply the same lists and rules
against the file's detected type (never the client-declared one), and also
refuse a file whose detected type is a page type's (`text/html`, SVG, XML…)
whatever the config; there only the `allowed_types` of an `upload_config` is
honored. To accept more, pass
`upload_config` — it replaces the defaults entirely, so restate `allowed_types`
and `max_size` — or set `$this->attachment_lib->upload_config` for attachments.
A project subclass of `MGR_Upload_lib` can instead redefine the
`DEFAULT_FILE_TYPES` / `DEFAULT_IMAGE_TYPES` constants. Saved as
`application/libraries/Upload_lib.php` (`class Upload_lib extends
MGR_Upload_lib`, after `require_once MGRPATH .
'libraries/MGR_Upload_lib.php';`), it replaces the package alias project-wide:
the controller proxies, `attachment_lib` and CLI all load it as `upload_lib`.
Under a name of its own it reaches only the code that loads that name.

**Conversion target.** `upload_image()` re-encodes to the `$convert_to`
target: `jpg` (the property's default) or `webp`, set project-wide by the
`protected string $convert_to` property of that same-named subclass (the
sample sets `webp`), or per call with the trailing `convert_to:` argument,
which wins. Any other value throws `InvalidArgumentException`. JPEG flattens
transparency onto white; WebP keeps it. A target whose encoder this stack's
GD lacks is refused (logged, `$error` the generic upload message), never
replaced by another. `preserve_type` ignores the target. Thumbnails are built
for jpeg, png and webp, in the main image's type, keeping WebP and PNG
transparency. Changing the target leaves
files already stored under the old extension in place; the overwrite of a
re-upload only replaces the same name.

Without `preserve_type`, `upload_image()` converts only content detected as
jpeg, png, gif, webp or avif whose decoder this stack's GD has; any other
detected type (a PDF or BMP allowed through `upload_config`, or a WebP on a
GD built without WebP) is refused with the filetype error. An image GD would
decode (always without `preserve_type`, otherwise when a thumbnail is built)
is refused with the dimensions error above `MAX_IMAGE_PIXELS` (width × height,
16,777,216), whatever `upload_config` says; the constant belongs to
`image_process_lib`, raised in a same-named
`application/libraries/Image_process_lib.php` (`class Image_process_lib
extends MGR_Image_process_lib`, after a `require_once MGRPATH .
'libraries/MGR_Image_process_lib.php';`). Both checks run on PHP's temp file
before either backend stores anything, so a refused re-upload leaves the
previous image in place. Without `preserve_type`, every raster upload is
re-encoded to the conversion target (above) on both local and `s3/…`, JPEG
included: metadata is dropped and a JPEG's EXIF orientation is applied when
the `exif` extension is loaded (without it the image keeps its stored pixel
order). A kept original is stored as `<name>_original.<ext>` on both
backends, with the client's extension. Re-encoding keeps only the first
frame of an animated GIF; an animated WebP cannot be decoded by GD and comes
back as the generic failure. `preserve_type` stores an animated GIF with its
frames (no thumbnail). Any failure inside an upload, including an error
thrown by GD or the S3 client, is logged and comes back as `null` with the
reason in `$error`; the call's files are removed, never a 500.

`upload_image()` returns `fullsize_*` for the stored main image. `thumb_*`
name the thumbnail when one was built and stored, otherwise the main image
(no `$resolution`, a type that gets no thumbnail, an image already smaller
than `$resolution`, or a thumbnail that failed). `original_*` (and
`original_link` locally) name a separate original that was kept, and are `''`
otherwise: `delete_original` or `preserve_type`.
`?v=` follows only a non-empty name, so every returned name and URL names a
stored object. On `s3/…` the puts run main, original, thumbnail: a failed main
put returns `null` with `$error` and stores nothing; a failed original or
thumbnail put is logged and the call still succeeds, with that variant
falling back as above.

**Desired names.** A `$desired_file_name` (`upload_file()`, `upload_image()`),
`put_file()`'s `$file_name` and `attachment_lib`'s `$desired_filename` are names
the server builds — `<id>.pdf`, `invoice_<hash>.pdf` — never request input. To
keep a client's original name, store it in the DB (`attachment_lib` keeps it in
`title`; `$extra_data` overrides it) and let the library generate the stored
name. For `upload_file()` and `upload_image()` pass a stem (`<id>`, the library
appends the extension) or `<stem>.<ext>` with one dot at most; the text after
the dot is the extension and must agree with the file it names: the detected
content for `upload_file()` and `preserve_type`, the conversion target for
`upload_image()` (`<id>.jpg` with the `jpg` target, which `allowed_types` must
also list; `<id>.png` is refused, `report.v2`, `a.b.jpg` and `X.` too). On
`s3/…` the key carries that one extension. A stored extension is lowercase on
both backends, a desired one included (`report.PDF` is stored `report.pdf`);
the stem keeps its case. `put_file()` stores its name whole. A name that fails
is refused with `$error` (`attachment_lib` throws), never rewritten.
`attachment_lib` also throws `InvalidArgumentException` for a desired name
over 89 characters (its `file_name` column of 100, less the 11 characters of
the `?v=` suffix), and `RuntimeException` when the stored `file_name` still
would not fit (an extension the library appends counts). Two
layers decide: fixed rules in `upload_lib` (valid UTF-8,
no `/`, `\`, `?` or NUL, no leading dot) and the overridable
`mgr_is_allowed_file_name()` — ASCII letters, digits, `.`, `_`, `-`, starting
with a letter or digit, at most 255 bytes. To accept more (CJK, Thai, a longer
limit), declare that one function in
`application/helpers/manager_file_helper.php`; the fixed rules still run first.
The helper checks one file name, never a path or directory segment. A name is
checked as given, so one near 255 bytes can still fail once the extension (or
`_thumb`/`_original`) is added. `put_file()` with an empty name generates
`<hash>.<ext>`, the extension looked up from the detected content type (`.txt`
for plain text, `.zip` for a zip); no extension when the type is unlisted or
the extension is PHP's.

Library configuration lives in `vendor/ixaya/manager/system/package/config/`,
in two different shapes:

- **`$config[]` array files** (`lib_websocket.php`, `ion_auth.php`,
  `rest.php`, …) — env-var driven; override via environment variables (see
  the `.env` samples) or a same-named `application/config/<file>.php` setting
  only the keys it changes — every layer loads and the project wins per key.
- **Loose-variable/return-shape files** (`lib_mailing.php`, `lib_jwt.php`,
  `lib_sendgrid.php`, `lib_amazon_aws.php`, `mimes.php`) — one file is
  `include()`d whole, no per-key merge. A project override
  (`application/config/<file>.php`) starts with
  `include MGRPATH . 'config/<file>.php';`, then sets only the fields it
  changes — the include is what makes it complete.

Framework-level toggles (`migration_db`, `languages`, `rest_time_zone`,
`cache_enable`) are in `package/config/manager.php`. Reference these, don't
edit vendor copies.

Caching is CI3's cache **driver**, not a library (see mgr-cache-websockets);
key it with `mgr_cache_key()`.

## Framework-provided models

The package ships ready-made models in
`vendor/ixaya/manager/system/package/models/` — load them like any model
(`$this->load->model('manager_option')`), don't recreate their tables:

| Model | Purpose |
|---|---|
| `manager_option` | **Key/value app settings** — `get_value($key, $default)` / `save_value($key, $value)`. Use for persisted app state (sync cursors, feature flags) instead of inventing one-off tables; the cron jobs use it this way |
| `rest_key_model` | API key issuance/lifecycle (see mgr-rest-controller) |
| `rest_user` | REST user get |
| `rest_user_group` | REST group/level checks: `validate_group()`, `get_highest_level()` — used by the framework's `_remap()` gating |
| `attachment` | Rows behind `attachment_lib` |
| `domain` / `theme` | Per-domain theming/redirects, web layer (see mgr-web-controllers) |
| `ion_auth_model` | Ion Auth internals — prefer the `ion_auth` library API |

Every one of these is a shim over an `MGR_<Name>` base (`system/models/MGR_<Name>.php`),
the same shape as the library shims above. To customize one, create
`application/models/<Name>.php extends MGR_<Name>` — a subclass, not a
copy, so it keeps inheriting framework fixes.

## Creating a new library

Only read when AUTHORING a new library (not when consuming one):
`references/creating-a-library.md` beside this file — naming, CI access, the
two config modes (multi-profile vs plain read), and the exemplars to copy
from.

## Working around a gap in CI3's database layer

The CI3 driver classes (`DB_driver`, `DB_forge`, the `pdo_*` subdrivers) sit
outside the alias chain, so there is no subclass seam and "override the
driver method" means a Composer patch. Before proposing one, read
`references/ci3-db-layer-gaps.md` beside this file — most gaps close through
the driver's public methods or `database.php` config instead.

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
