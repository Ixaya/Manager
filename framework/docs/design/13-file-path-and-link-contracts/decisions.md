# File, path and link contracts — decisions

The rules themselves are in the skills and PHPDoc; this records only the
reasons a future editor would otherwise re-derive.

## Shape rules on reads and includes, contracts on writes (2026-11-10)

Write paths are a contract with no containment check: a server-built path
may legitimately contain `..`, and the shipped `read_only` stack already
confines where writes land. Reads and view includes refuse a `..` segment
through one overridable helper. Another storage root is added by overriding
`mgr_file_path()`, not with `../`.

## Stored names (2026-11-10, amended 2026-11-11)

A client's upload name is never the stored name; the original goes to the
database (`attachment_lib`'s `title`). Rejected: sanitizing a desired name
(a silent rewrite the caller never sees), always generating (breaks
server-chosen overwrite names such as `<id>.pdf`), and deprecating
`encrypt_name` instead of ignoring it (keeps client names until a major).
The fixed rules make a name one path component, so they need no separate
`..` rule; they refuse `?` because the library's `?v=` URLs and the path
cleaners cut there. Charset and the 255-byte length sit in the overridable
helper: they are correctness defaults, not security rules.

## Types (2026-11-10, amended 2026-11-11)

Page types are checked on the final stored name, so a desired name's own
extension counts. On S3 the detected type is checked too, because S3 serves
the object with the content's type whatever the key says. "Under the web
root" is decided from the requested path through `mgr_file_path()`, not
CI's `realpath()`, so a symlinked `public/media` stays covered. `put_file()`
is outside the page-type rule: the server picks its content and extension.
`upload_config` replaces the defaults; whether to merge instead is open
(spec "Open work").

## Serving (2026-11-11)

`display_image()` decides from the detected content, not the extension.
`nosniff` stays with the server config (a PHP header would duplicate
nginx's). The sample nginx `location` is a narrow deny-list of page types,
mirrored from `PAGE_TYPES` with keep-in-sync comments: a header on all of
`/media/` could break inline PDFs, and a stale allow-list would break files
projects serve today.

## Failure cleanup (2026-11-11)

A written path is registered for cleanup only after its write returns, so a
failed re-upload under a desired name never deletes the previous file.
Accepted: that re-upload has already replaced the previous content.

## Links and themes (2026-11-10)

No request-Host fallback and no new env key: unset `CF_HOST` gives
root-relative links, and `"//$host"` stays (same output as `'/'`, and it
keeps CI's own host detection out). `resolve_theme()` logs and skips
instead of throwing, since it runs in a constructor and a throw would fail
every page of the domain.

## A protocol on a base without a host (2026-11-17)

With `CF_HOST` unset the base is root-relative, and no scheme makes it
absolute: prepending one gives `https:/media/…`, which a client reads as host
`media`. The URL stays root-relative instead, with no log line (it would fire
on every call in the default configuration) and no throw (it would fail that
configuration). The rule sits in `MGR_Config` overrides that wrap CI's
`base_url()`/`site_url()` rather than copying their bodies, so a call without
a protocol is CI's own.
