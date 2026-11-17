# Uploads and images

How the upload library stores files and images, local and S3, and what it
returns. This record covers routing, image conversion, the return contract,
stored names and extensions, and attachments; later upload or image work
adds its section here. The earlier type, name and path contracts are in
`13-file-path-and-link-contracts`.

## Storage routing

A storage URI is on S3 only when its first segment is `s3/`, like `media/`
and `private/`. One helper, `mgr_is_s3_path()`, is the check at every
routing site (the five in `MGR_Upload_lib`, and `MGR_Amazon_aws_lib`'s
Textract config); `mgr_clean_file_s3_path()` strips a leading `s3/` only.

## Image conversion

- `upload_image()` checks PHP's temp file before either backend stores
  anything: a GD decoder for the detected type (unless `preserve_type`), an
  encoder for the target, and `MAX_IMAGE_PIXELS` whenever GD will decode.
- One `convert_image()`, in `image_process_lib` with the rest of the GD code, serves both backends. Every raster upload is
  re-encoded, JPEG included; metadata is not kept; a JPEG's EXIF orientation
  is applied when `exif` is loaded.
- The target is `$convert_to` (`jpg`, `webp`): a property for the project
  default, a trailing argument per call. WebP keeps alpha, JPEG flattens
  onto white. A missing encoder is refused, never replaced.
- An animated GIF keeps its first frame; an animated WebP cannot be decoded
  by GD and is a failed upload. `preserve_type` stores the file as uploaded.
- Thumbnails are built by `image_process_lib`'s `create_thumbnail()` and keep
  WebP and PNG transparency.
- The scaffold image gains GD WebP and `exif`; the sample adopts WebP
  through a same-named `Upload_lib` subclass.

## Return contract

Every returned name and URL names a stored object: `thumb_*` fall back to
the main image, `original_*` are `''` without a separate original, `?v=`
follows only a non-empty name. On S3 the puts run main, original,
thumbnail; a failed main returns `null` + `$error`, a later failure falls
back.

## Stored names and extensions

A generated name takes its extension from the detected type through
`mgr_file_extension()`: a short corrections list, then the first
`mimes.php` key. Content detected as PHP source gets no extension.
`system/config/mimes.php` follows CodeIgniter's upstream list with the
local changes in `framework/docs/development/codeigniter-upstream.md`.

## Attachments

`MGR_Attachment_lib` checks names against its column widths (protected
properties, 100 each): a desired name over 89 characters throws before
anything is stored; `title` is cut to fit, extension kept, invalid UTF-8
dropped; a row that cannot be saved throws `RuntimeException`, and the
stored file stays, its path logged. No schema change.

The working rules live in `mgr-helpers-libraries` and the PHPDoc; the
consumer side in `system/docs/upgrading/next.md`.
