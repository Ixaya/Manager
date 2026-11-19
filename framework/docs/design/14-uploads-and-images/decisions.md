# Uploads and images — decisions

The rules themselves are in the skills and PHPDoc; this records only the
reasons a future editor would otherwise re-derive.

## Routing by prefix, one helper (2026-11-14)

`s3/` was always meant as a storage root. One shared helper keeps the
routing sites from drifting apart again. A leading slash is not accepted,
and keys already stored are unchanged.

## Routing drops leading slashes; returns carry none (2026-11-18)

Supersedes "A leading slash is not accepted" above. The rule the entry
protects is that `s3/` is the first segment, not found anywhere in the path,
and that holds after leading slashes are dropped: `media/s3/…` and `files3/…`
stay local. Every other path helper drops them before judging, and refusing
them in the one S3 test left a stored `/s3/…` value unreadable from S3.

The upload returns carry no leading slash because a value read back through
the storage helpers is a storage URI, not a web path; a link for a browser is
built from it with `base_url()` or `image_url()`. Stored rows are not
rewritten: both forms read back.

## Storage writes refuse a `..` segment, and PHP-family names in every root (2026-11-18)

A storage URI is built by the server, so a write applies the check a read
already did (`mgr_is_allowed_file_path()`). It sits in each of the six
backends beside the name checks, not before routing: `upload_file_local()`
and `put_file_local()` are public, and a check before routing does not run
for a direct call.

A PHP-family extension is refused in every root, `private/` included, by
`put_file()` as by the upload methods. The rule does not depend on which
directory a deployment serves: `private/` is a layout convention, S3 refuses
by name wherever the key is, and no framework caller stores PHP. Page types
stay refused under the web root only, since they matter where a browser
renders them from the app's own origin.

## `upload_config` replaces the defaults (2026-11-14)

A merge would silently change callers nobody has surveyed: an omitted
`allowed_types` would widen to the defaults, an omitted `max_size` would
start capping. So no floor may be an `upload_config` key.

## Floors are constants, defaults are properties (2026-11-14)

`MAX_IMAGE_PIXELS` is a floor a subclass of `image_process_lib` may raise;
`$convert_to` is a runtime default a project sets on the upload library.

## Image processing is its own library (2026-11-16)

The GD half moved out of `upload_lib` before it shipped, while no project
could have overridden it. The library keeps no state, so the one instance
the loader caches per request is harmless, and its constants stay
`protected`: `upload_lib` asks through readers, because naming the base
class's constant would bypass a project's same-named subclass.

## The pixel ceiling is measured, not derived (2026-11-16)

16,777,216 (4096²). The worst case (decode, a quarter turn, the sample's
thumbnail) peaked at 132M against the scaffold's 256M FPM limit; a 4032x3024
photo passes. Deriving the ceiling from `memory_limit` was rejected: it
needs an empirical bytes-per-pixel constant and depends on current usage.

## The gate runs on the temp file, before routing (2026-11-14)

So a refusal never overwrites an image already stored under the same name.

## Every raster re-encoded, no fallback encoder (2026-11-14)

Local and S3 now behave the same. A fallback target would silently change
the stored type and extension.

## WebP through a same-named subclass (2026-11-14)

Observed reaching the controller proxies, `attachment_lib` and CLI.
Existing projects keep JPEG, so no stored URL changes on update.

## `Image_lib` reset (2026-11-16)

The MX loader keeps one instance per request, and `initialize()` alone
keeps `master_dim`, so a second image would scale along the wrong side;
`clear()` runs first.

## Thumbnails built by `image_process_lib` (2026-11-16)

`Image_lib` keeps alpha only for PNG, so a WebP thumbnail of a transparent
image lost it, and its per-request instance needed a reset before each use.
The thumbnail is built beside `convert_image()` with `Image_lib`'s fit
arithmetic, so stored sizes do not change. Supersedes the entry above.

## Local paths from CI's resolved `file_path` (2026-11-16)

Path comparisons with CI's `full_path` must hold under a symlinked media
root.

## Attachments: a guard and a return check, no migration (2026-11-17)

The columns stay at 100. Widening would be a new migration whose `down()`
refuses values over 100 (an empty `sql_mode` would truncate them). The
stored `file_name` is checked after the merge because CI appends the
upload's extension to an extension-less name. `title` is scrubbed because a
driver's quoting emptied invalid UTF-8 instead of failing.

## MIME → extension: four corrections (2026-11-14)

Each equals `mime-db`'s preferred extension, so a later standard lookup
leaves those names unchanged. The PHP-family refusal is stored-name policy,
separate from the data. The lookup leaves `mimes.php` alone: CI's
validation reads it by extension.

## A desired name's extension must agree with its file (2026-11-16)

A dotted desired name names what is stored, so a mismatch is refused, never
rewritten, and a refusal follows only the server's choices. Under conversion
the upload is an intermediate and the name names the converted main, so it
is compared with the target (which `allowed_types` must list, as CI applies
the list to a dotted name) and not with the upload. One dot at most: the
text after it is the extension. Extensions are stored lowercase, so two
stored names never differ only by case. No shared function decides the
stored extension: CI sets it inside `do_upload()`, so the library configures
CI (`file_ext_tolower`) and S3 does the equivalent in its own methods.
Locally, under conversion, CI stores the upload as `<stem>_original`, so the
main never overwrites its own source, CI never sees the desired extension,
and a kept original is named as on S3.

## Left out on purpose (2026-11-14)

AVIF output, animation detection, the `upload_config` merge, and removing
the stored file after a later failure.
