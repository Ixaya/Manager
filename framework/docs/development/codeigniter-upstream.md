# CodeIgniter-derived files outside `third_party/`

Files the framework carries that started as copies of CodeIgniter 3 files
but live outside `system/third_party/`. Each section says what the upstream
file is, when it was last synced, and which local changes a resync must keep.
A file gets its section the first time someone resyncs it. The Composer
dependency (`nielbuys/framework`) is not covered: it is never edited, and
changes to it go through Composer patches.

## `system/config/mimes.php`

Upstream: CodeIgniter 3.1 `application/config/mimes.php` (`3.1-stable`,
`master` and `develop` were byte-identical, sha1 `1a3a26ae8d83`). Last
synced 2026-11-14. The vendored fork's copy is not upstream (its `json` row
adds `text/plain`), so diff against CodeIgniter's own repository.

Local changes to keep:

- **`jpg` listed before `jpeg`.** `mgr_file_extension()` returns the first
  key whose list contains a MIME type, so this order is what gives
  `image/jpeg` the `.jpg` extension in generated names. It can go once that
  lookup stops reading this file.
- **The `webp` key.** CI's `Upload` requires the detected type under the
  file's extension key; without it every WebP is refused, and `webp` is in
  the `upload_image()` default list.
- **The `avif` key.** Same reason, for AVIF uploads under a caller's own
  `allowed_types`.
- **Short array syntax**, per the code style.

After a resync, re-run the upload checks that read this file: the default
type lists through `upload_file()` and `upload_image()` (local and S3), the
page-type refusal, and the extension `mgr_file_extension()` returns for
`image/jpeg`, `image/webp` and the corrected types in its own array.
