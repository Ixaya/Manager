# Upgrading — unreleased

Changes between 2.x releases that alter behavior a project already depends on.
Everything else in a minor release is additive.

## Language selection is resolved in the controller base

The `Language` controller (`language/change/{locale}`) that the 2.0.0 table
listed as a package replacement was never routable: the package
`controllers/` directory was not a module location. It is removed. A project
that still has `application/controllers/Language.php` deletes that file but
keeps the (empty) `application/controllers/` directory, which module routing
resolves through. A project that wants a language picker
writes `$_SESSION['language']` from its own controller.

The request language is now resolved in the controller base, before anything
loads a language file, and only values listed in `MGR_LANGUAGES` are accepted
(exact match; anything else is ignored and the configured `language` stays).

- **Every web and REST controller** reads the `X-Language` request header.
  Ion Auth messages follow it. The header is in the default
  `REST_ALLOWED_CORS_HEADERS`; a project that sets that variable replaces the
  whole list and must add `X-Language` itself for cross-origin frontends.
- **`?language=` and `$_SESSION['language']` now apply only to controllers
  that extend `APP_Site_Controller`.** A web controller extending
  `MY_Controller` that used either stops switching; make it extend
  `APP_Site_Controller`. `?language=` still needs `$language_enabled`; the
  session value does not.
- **A language missing from `MGR_LANGUAGES` is no longer applied.** The
  default list is `english,spanish`; a project that reaches `japanese` (or
  any other locale) adds it to `MGR_LANGUAGES`. The REST controller loads its
  own `rest_controller_lang.php` in the resolved language when `REST_LANGUAGE`
  is unset (the package ships it for `english`, `spanish` and `japanese`), so
  a locale a project adds itself needs that file under its language
  directory, or `REST_LANGUAGE` set to pin the REST messages.
- **`REST_LANGUAGE` unset follows the request language** (otherwise the
  configured `language`, `english` by default). Set it to keep REST's own
  messages fixed; `.env` files that carry `REST_LANGUAGE=english` keep that
  pin.
- **`Accept-Language` is not read.** Override `language_candidates()` in a
  base controller to add it; `mgr-web-controllers` has an example.

## Migrations: the package runs first

`manager/tools/migrate` (and `plan`) now order targets as the package's own
migrations, then the application's `database/migrations`, then the other
modules by key. Before, the package ran last, so a project migration that
altered a package table (for example adding a column to `user`) failed on a
fresh install with the table missing.

Nothing to do for an installed database: recorded versions are untouched. Two
things to check:

- A fresh install whose app-level history creates a package table itself
  (`user`, `group`, `user_group`, `login_attempt`) now finds the package's
  table already there. Remove or skip that migration; the package owns those
  tables.
- A pending package migration in an upgrade now runs before the project's
  pending ones.

## The bundled `mariadb` profile runs a healthcheck as the app user, closes `root@'%'`, and becomes a server tier

Reaches a project only when it reconciles `docker/docker-compose.yml`,
`docker/env/sample.docker.env` and `bin/` from the sample; `composer update`
alone changes nothing.

- **The healthcheck changed:** `mariadb` is healthy once `DB_USER` can log in
  to `DB_NAME` over TCP, not merely when the server answers. An instance whose
  data directory was initialized with an empty `DB_NAME`, `DB_USER` or
  `db_password` therefore turns `unhealthy` after the reconcile; the reason is
  in `docker inspect`. Nothing in the stack acts on that, but a host tool that
  restarts unhealthy containers would. It also reports unhealthy while every
  connection slot is taken.
- **`root` logs in only from inside the container** (`MARIADB_ROOT_HOST:
  localhost`). A new data directory gets no `root@'%'`. One created earlier
  keeps it until a one-time step, over the socket:

  ```bash
  ./docker_manage.sh -e <instance> --profile mariadb exec -T mariadb sh -c 'MYSQL_PWD="$(cat /run/secrets/db_root_password)" exec mariadb -uroot' <<< "DROP USER 'root'@'%';"
  ```

  A throwaway local instance can `down -v` instead. The app user, `docker
  exec` logins and `bin/db-backup.sh` are unaffected; anything that logged in
  as `root` over the network no longer can.
- **New, for servers (additive):** `MARIADB_DATA` in `docker.env` (empty
  keeps today's named volume), the `mgr.backup=mariadb` label, and a `mariadb`
  branch in `bin/db-backup.sh`. **Re-copy `bin/db-backup.sh` to wherever a
  host runs it from before the reconciled stack starts:** a copy without the
  branch fails the site with `unsupported engine` while a `mgr.backup=mariadb`
  container runs. Setup, backups, restore and series bumps are in
  `docs/development/docker-server.md`.
- **A MariaDB series bump upgrades the data directory on its first start, and
  putting the old pin back is not a rollback.** `MARIADB_AUTO_UPGRADE=1` is
  unchanged; what is new is the documented rule: dump first, rollback is a
  restore. Compose and `docker.md` now say "series bump" where they said
  "version bump".

## The bundled `postgres` profile refuses the superuser over TCP

Reaches a project only when it reconciles `docker/postgres/` from the sample
(a compose copy run by other tooling also needs `postgres/` re-copied beside
it); `composer update` alone changes nothing.

- **`postgres` logs in only from inside the container**, as MariaDB's `root`
  now does. A new cluster refuses it over TCP from anywhere but loopback. One
  created earlier keeps accepting it until a one-time step:

  ```bash
  ./docker_manage.sh -e <instance> --profile postgres exec -u postgres postgres sh /docker-entrypoint-initdb.d/20-superuser-local-only.sh
  ./docker_manage.sh -e <instance> --profile postgres exec -u postgres postgres psql -U postgres -Atc 'SELECT pg_reload_conf()'
  ```

  A throwaway local instance can `down -v` instead. The app role, `docker
  exec` logins and `bin/db-backup.sh` are unaffected; anything that logged in
  as `postgres` over the network no longer can.

## REST per-method gates follow the method that runs

`group_methods` and `auth_override` entries used to be looked up under the raw
URL segment, so a format suffix (`/locked.json`) or the `index_{verb}`
fallback (`/123`, served by `index_get`) skipped a per-method entry that
`/locked` or `/` honored. They now resolve to the controller method that runs
(`locked_get`, `index_get`), the same key `group_methods`, `log` and `key`
already used.

- **Gates that were being skipped now apply.** A client that reached an
  endpoint through `.json`, `.xml` or `/{id}` while its user failed the
  per-method `group_methods` level or group now gets 401. A per-method
  `auth_override` that tightens access under a public `'*'` (`'basic'`,
  `'digest'`, `'session'`, `'whitelist'` over `'none'`) now also applies
  under a suffix.
- **`methods['x_get']['auth_override']` now works.** It used to match nothing.
  A project that carries such a line, usually written before the key shape
  was known, now gets what the line says. Review every match before
  upgrading; each one becomes active:

  ```bash
  grep -rnE "methods\['[A-Za-z0-9_]+_(get|post|put|patch|delete|options|head)'\]\['auth_override'\]" application/
  ```

  Confirm an endpoint set to `'none'` or `'allow'` should really be public,
  or delete the line.
- **The older shapes are deprecated.** `methods['x']` and
  `methods['x']['get']` keep working, now matched against the
  suffix-stripped segment (`/x.json` honors them). Move them to
  `methods['x_get']`. When both are set for a request, the resolved key wins.
  `methods['index']` covers `/` and `/index` only, not `/123`; `index_get`
  covers all three.

## Upload library: type defaults, conversion, generated names, storage URIs

- **Uploads have type defaults.** Without an `upload_config`,
  `upload_file()` and `attachment_lib` accept
  `gif|jpg|jpeg|png|webp|pdf|txt|csv|doc|docx|xls|xlsx|ppt|pptx|odt|ods`
  (was any type), and `upload_image()` accepts `gif|jpg|png|jpeg|webp` (no
  longer `svg` or `pdf`). With a list, the extension must match the detected
  content. Page types (`html`, `htm`, `xhtml`, `shtml`, `svg`, `svgz`, `xml`,
  `xsl`, `js`) are refused under the web root (`media/…`) whatever the config.
  So are PHP-family extensions (`php`, `phtml`, `phar`…), even under
  `allowed_types` `*`; a caller that stores PHP source moves it to `private/…`.
  On `s3/…` paths the same lists and rules apply against the type
  detected from the content, and only `allowed_types` of an `upload_config` is
  honored there; `file_type` / `fullsize_type` report the detected type. To
  accept more, pass `upload_config` (it replaces the defaults: restate
  `max_size`), set `$this->attachment_lib->upload_config` for attachments, or
  redefine `DEFAULT_FILE_TYPES` / `DEFAULT_IMAGE_TYPES` in a subclass loaded
  under its own name. A type with no `mimes.php` entry goes in the project's
  `config/mimes.php`.
- **`upload_image()` re-encodes every raster upload** on local and `s3/…`,
  JPEG included, to the conversion target. Metadata is not kept; a JPEG's EXIF
  orientation is applied when PHP has the `exif` extension. Without
  `preserve_type`, content GD cannot decode is refused with the filetype error,
  and an image GD would decode (always without `preserve_type`, else for a
  thumbnail) is refused above `MAX_IMAGE_PIXELS` (16,777,216 pixels) with the
  dimensions error, whatever `upload_config` says. Both refusals happen before
  anything is stored. An animated GIF is stored as its first frame; an animated
  WebP is refused as a failed upload. The GD work lives in the new
  `image_process_lib`, usable on its own; raise `MAX_IMAGE_PIXELS` in a
  same-named `application/libraries/Image_process_lib.php`, not in an
  `Upload_lib` subclass.
- **Conversion target `$convert_to`:** `jpg` (default) or `webp`. Set
  project-wide with a same-named `application/libraries/Upload_lib.php`
  subclass (the sample ships one with `webp`), or per call with the new
  trailing `upload_image(convert_to: …)` argument. Any other value throws
  `InvalidArgumentException` before anything is stored. WebP keeps
  transparency, JPEG flattens it onto white. A target the stack's GD cannot
  encode is refused with the generic upload error and a log line naming it.
  Adopting WebP leaves files stored under the old `.jpg` names in place, to
  convert or prune.
- **New trailing parameter:** `upload_image()`, `MGR_Controller::upload_image()`
  and the protected `upload_image_local()` / `upload_image_s3()` take
  `?string $convert_to = null`. An override with the old signature must add it.
- **Return keys, local and S3:** `thumb_*` name the main image whenever no
  thumbnail was stored; `original_*` (and local `original_link`) are `''` when
  no separate original was kept (`delete_original`, `preserve_type`); `?v=`
  follows only a non-empty name. A kept original is stored as
  `<name>_original.<ext>` on both backends (locally it kept the upload's own
  name, `X.png` beside `X.jpg`, unless the main would take it); files already
  stored keep their names, and a re-upload leaves the old `X.png` in place.
  On `s3/…` the call returns `null` with `$error` when
  the main image is not stored; a failed `_original` or thumbnail store is
  logged and the call succeeds with that variant falling back. Thumbnails are
  built for WebP too, and two `upload_image()` calls with `$resolution` in one
  request each get their own. `image_process_lib` gains `create_thumbnail()` for
  this: a resize that keeps PNG and WebP transparency, usable on its own.
- **Failures return `null`.** An error inside an upload, GD's or the S3
  client's included, returns `null` with `$error` set instead of a 500. Pass
  `error: $error` and answer the failure; a falsy check alone now carries on
  silently.
- **Stored names are generated.** `encrypt_name: false` is ignored. A desired
  name (`$desired_file_name`, `put_file()`'s `$file_name`, `attachment_lib`'s
  `$desired_filename`) is built by the server, and is refused (`null` +
  `$error`; `attachment_lib` throws) when it has `/`, `\`, `?`, NUL, a leading
  dot or invalid UTF-8, or fails `mgr_is_allowed_file_name()`: ASCII letters,
  digits and `._-`, starting with a letter or digit, at most 255 bytes.
  Override that function to widen the charset or the length. To keep a
  client's original name, let the library generate the stored one and keep
  the original in `title` (`$extra_data` overrides it). `put_file()` with an
  empty name generates `<hash>.<ext>`, or `<hash>` alone when no extension is
  listed for the detected type or the content is PHP source. Plain text gets
  `.txt`, a plain zip `.zip`, a legacy Word file `.doc`, a legacy Excel file
  `.xls`; `mgr_file_extension()` with only a MIME type returns the same. Files
  already stored keep their names.
- **A desired name is a stem, or carries one extension that agrees with its
  file.** For `upload_file()` and `upload_image()` (`put_file()` stores its
  name whole), omit the extension and the library appends the right one; a
  dot makes the text after it the extension, which must agree with the file
  it names: the detected content for `upload_file()` and `preserve_type`, the
  conversion target for `upload_image()`, which `allowed_types` must also
  list. `<id>.jpg` with the `jpg` target passes for any image; `<id>.png`,
  `<id>.jpeg` and `<id>.jpe` there are refused instead of stored as
  `<id>.jpg`. A name with more than one dot (`report.v2`, `a.b.jpg`) or a
  trailing dot (`X.`) is refused on both backends. Refusals are `null` +
  `$error`. On `s3/…` the key carries that one extension and the thumbnail
  and kept original are named from the stem (`photo_thumb.jpg`,
  `photo_original.png`); keys stored earlier (`doc.pdf.png`,
  `photo.png_thumb.jpg`) and any URL stored for them stay in place, and a
  re-upload writes the new key.
- **Stored extensions are lowercase** on both backends, a desired one
  included (`report.PDF` is stored `report.pdf`); the stem keeps its case. A
  kept original takes the client's extension, lowercased (`<id>_original.png`
  for a PNG upload). Files already stored keep their names: on S3 or a
  case-sensitive filesystem a re-upload of a former `X.JPG` writes `X.jpg`
  beside it, and a project that rebuilds a key from the client's file name
  sees the difference.
- **Storage URIs route by their first segment.** A URI is on S3 only when it
  starts with `s3/`, in every method (`upload_file()`, `upload_image()`,
  `put_file()`, `display_image()`, `get_file_base64()` and the Textract
  helper). `media/s3/…` and `/s3/…` are local paths. `mgr_clean_file_s3_path()`
  removes the query string and a leading `s3/` only. New
  `mgr_is_s3_path(string $uri): bool` is the one check; a project override of
  `mgr_clean_file_s3_path()` should use it. Objects already stored keep their
  keys.
- **`attachment_lib` `upload_file()` and `put_file()`:** a non-empty
  `$desired_filename` over 89 characters (the `file_name` column's 100, less
  the 11 of `?v=`) throws `InvalidArgumentException` before anything is stored.
  `title` is stored cut to 100 characters, extension kept, invalid UTF-8 bytes
  dropped. A row that cannot be saved throws `RuntimeException`, and the call
  no longer returns `attachment_id` `false` or `null`. "Cannot be saved" means
  a database refusal, or a stored `file_name` over 100 characters (an extension
  the library appends counts). With `db_debug` on, a database refusal still
  ends in CI's own error first. The stored file stays; its path is in the app
  log. The limits are protected properties (`$title_max_length`,
  `$file_name_max_length`) for a project with a wider table.
- **`mimes.php` follows CodeIgniter's upstream list.** `application/octet-stream`
  no longer matches `.xlsx`. New types: `heic`, `heif`, `7z` and the
  OpenDocument chart, formula, graphics, image, presentation, template and
  master formats. `aac` accepts `audio/x-aac` and `audio/aac` (`audio/x-acc` is
  gone), and `svg` also matches `image/svg`. A project `config/mimes.php` that
  includes the framework file picks these up; one that copied the list does
  not.
- **`display_image()` and `get_file_base64()` take a storage URI**
  (`media/…`, `private/…`, `s3/…`). An absolute filesystem path no longer
  resolves, and a URI with a `..` segment is refused (empty image / `null`,
  logged). Add another storage root by overriding `mgr_file_path()`.
  `display_image()` serves jpeg, png, gif, webp and avif inline, typed from
  the content, and anything else as a download (`application/octet-stream`,
  `attachment`).

## Controller helpers are `protected`

`MGR_Controller`'s `load_language`, `load_session`, `load_clean_view`,
`load_view`, `json_response`, `upload_file`, `upload_image`, `put_file`,
`get_file_base64` and `display_image` form the controller-internal API and
are available to controller subclasses. Outside a controller, call
`upload_lib` instead of the file helpers (the same methods and arguments);
the others are controller-only. `put_file()` takes `string $data`: an
override typed `array` must change.
`load_view()` and `load_clean_view()` throw `InvalidArgumentException` for a
view name with a `..` segment; map slug pages to view names through an
allow-list.

## Sample: `base_url` from `CF_HOST` only, and a page-type nginx location

Reaches a project only when it reconciles these files from the sample.

- **`application/config/config.php`:** remove the `$_SERVER['HTTP_HOST']`
  fallback and set `CF_HOST` to the main public host, no scheme, in every
  instance env file. Links then follow one configured host on web and CLI
  alike. The fallback applied only to web requests, so pages looked right
  while CLI-built links (cron, mail sent from a command) came out host-less;
  unset, `CF_HOST` now gives root-relative links everywhere, so the gap shows
  on day one.
- **`docker/nginx/conf.d/app.conf`:** copy the page-type `location` into your
  own, before the generic `/media/` block (the first matching regex wins), and
  keep its `include security-headers.inc`; rebuild or reload nginx. Page types
  under `/media/` then download instead of rendering; other extensions are
  unchanged. A server other than the sample nginx needs the equivalent
  headers.
- **`docker/Dockerfile`:** GD gains WebP (`libwebp`, `libwebp-dev`,
  `--with-webp`) and the `exif` extension, needed for the WebP target and for
  orientation. **`application/libraries/Upload_lib.php`** (new) sets the WebP
  target project-wide; copy it only together with the image change.

## Sample nginx refuses PHP-family files under `/assets/` and `/media/`, and any-case `.php` at the root

Reaches a project only when it reconciles `docker/nginx/conf.d/app.conf`;
rebuild or reload nginx after.

- Copy the `location` that denies `php`, `php3`, `php4`, `php5`, `php7`,
  `php8`, `pht`, `phtml`, `phps` and `phar` under `/assets/` and `/media/`,
  placing it before the `/assets/` block (the first matching regex wins). A
  matching file now answers 403 instead of downloading; every other
  extension is unchanged. It covers files stored before the upgrade and
  ones the upload library never saw, such as a `put_file()` with a
  server-chosen name.
- A server other than the sample nginx needs the equivalent deny.
- Change the public-root `location ~ \.php$` deny to `~*`. It was
  case-sensitive, so a `.PHP`-cased file in `public/` was served instead of
  refused; it now answers 403 like `.php`.

## URL helpers: what a `$protocol` of `'https'` gives

`image_url()`, `secure_url()`, `base_url()` and `site_url()` now share one
rule for a `$protocol` of `'https'` or `'https:'` (both are accepted): a URL
with a host gets `https://host/…`, replacing any scheme it has; a
root-relative one (`CF_HOST` unset) stays root-relative. `''` and no
protocol return what they did. Output that changes, and who may notice:

- **A root-relative base with a protocol** returns `/media/x.jpg` where it
  returned `https:/media/x.jpg`. The JWT `iss` claim is `/` instead of
  `https:/` with `CF_HOST` unset, and unchanged (`https://host/`) when it is
  set. A client or a stored value that matched the old form needs updating;
  an email or push link needs `CF_HOST` set (build it with `secure_url()`).
- **An absolute `image_url` or `base_url` with a protocol** returns
  `https://cdn/…` where it returned `https:://cdn/…`.
- **The bare `'https'`** now works on a `//host` base (it returned
  `https//host/…`).
- **`secure_url()` given an absolute `http://` URL** returns its `https://`
  form instead of the URL appended to the base. `//cdn/x` and `https://cdn/x`
  are unchanged.
- **`MGR_Config` overrides `base_url()` and `site_url()`.** Only a call that
  passes a protocol changes. A project `MY_Config` that overrides either
  keeps its own behavior and does not get the rule.
- **The sample `Profile::get_profile()`** answers `image_url` through
  `image_url($uri, 'https:')`, as `image_post()` does: it honours a configured
  `image_url`, gives `/media/…` instead of `https://media/…` with `CF_HOST`
  unset, and no longer doubles the scheme on an absolute `base_url`. A project
  that copied the old `'https://' . ltrim(base_url(…), '/')` expression
  adopts the same call.
