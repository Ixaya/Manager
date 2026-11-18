# File, path and link contracts

Every value that names a stored file, a storage path, a view or the host of
a generated link is built by the server. The framework now states that
contract where each value is taken, and enforces the cheap shape rules on
the upload library, its read paths and the view loader.

## What was built

- **Controller helpers.** `MGR_Controller`'s helpers are `protected`;
  `put_file()` takes `string $data`.
- **Upload types.** Allow-list defaults (`DEFAULT_FILE_TYPES`,
  `DEFAULT_IMAGE_TYPES`) instead of `*`. Page types (`PAGE_TYPES`) are
  refused under the web root whatever the config, and always on `s3/…`,
  where the detected type is checked the way CI's `Upload` checks it
  locally. `MGR_Attachment_lib::$upload_config` carries an attachment's
  config. `mimes.php` gained `webp`, `odt`, `ods` and `avif`.
- **Image conversion.** Without `preserve_type`, `upload_image()` converts
  only content detected as one of `RASTER_TYPES` and refuses anything else.
- **Failures.** A throwable inside an upload is logged and returned as
  `null` + `$error`; the local files the call wrote are removed.
- **Stored names.** Generated unless the server passes a desired name;
  `encrypt_name` is ignored. A desired name passes fixed rules in the
  library, then the overridable `mgr_is_allowed_file_name()`. An empty
  `put_file()` name generates `<hash>.<ext>`.
- **Storage URIs.** `mgr_file_path()` and the upload library take
  server-built storage URIs (`media/…`, `private/…`, `s3/…`).
  `get_file_base64()` and `display_image()` refuse a `..` segment through
  the overridable `mgr_is_allowed_file_path()`; `display_image()` resolves
  its URI through `mgr_file_path()` and serves only `RASTER_TYPES` inline,
  anything else as an `attachment`.
- **View names.** `load_view()` and `load_clean_view()` throw
  `InvalidArgumentException` for a `..` segment; `resolve_theme()` skips a
  missing theme row or a shortname with a `..` segment.
- **Error-path CORS** sends the API's own header and method lists, plus
  `Vary: Origin`.
- **URL helpers.** `image_url()`, `secure_url()`, `base_url()` and
  `site_url()` share one rule for a `'https'`/`'https:'` protocol
  (`MGR_Config::with_protocol()`): a URL with a host gets that scheme, a
  root-relative one stays as is. `secure_url()` also takes an absolute or
  `//` URL.
- **Sample.** `config.php` builds `base_url` from `CF_HOST` only (unset,
  links are root-relative). The nginx config gains a page-type `location`
  under `/media/`. `Sysusers::update_post()` validates the id with the new
  `mgr_validate_id()` and uses the database id; image uploads move to their
  own `image_post()` endpoints on `Sysusers` and `Profile`. Both `Profile`
  endpoints answer `image_url` through `image_url($uri, 'https:')`.

Stated, not enforced: write paths (`mgr_file_path()`, `$relative_path`,
`MGR_Amazon_aws_lib::save_file()`) and `setup_model()`'s names.

The working rules live in `mgr-helpers-libraries`, `mgr-web-controllers`,
`mgr-rest-controller` and `mgr-auth`, and in the PHPDoc; the consumer side in
`system/docs/upgrading/next.md`.
