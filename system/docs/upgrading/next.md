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

## Upload library: type defaults, generated names, storage URIs

- **Uploads have type defaults.** Without an `upload_config`,
  `upload_file()` and `attachment_lib` accept
  `gif|jpg|jpeg|png|webp|pdf|txt|csv|doc|docx|xls|xlsx|ppt|pptx|odt|ods`
  (was any type), and `upload_image()` accepts `gif|jpg|png|jpeg` (no longer
  `svg` or `pdf`). With a list, the extension must match the detected content.
  Page types (`html`, `htm`, `xhtml`, `shtml`, `svg`, `svgz`, `xml`, `xsl`,
  `js`) are refused under the web root (`media/…`) whatever the config. To
  accept more, pass `upload_config` (it replaces the defaults: restate
  `max_size`), set `$this->attachment_lib->upload_config` for attachments, or
  redefine `DEFAULT_FILE_TYPES` / `DEFAULT_IMAGE_TYPES` in a subclass loaded
  under its own name. A type with no `mimes.php` entry goes in the project's
  `config/mimes.php`.
- **`upload_image()` converts only raster content.** Without
  `preserve_type`, content detected as anything but jpeg, png, gif, webp or
  avif (a BMP, a PDF allowed through `upload_config`) is refused with the
  filetype error. `preserve_type: true` is unchanged.
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
  listed for the detected type.
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
