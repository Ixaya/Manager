# Log archival sample callers

Generic, client-agnostic sample "caller" scripts for the base+caller log archival pattern:
a shared `base/logs_*.sh` deployed once per server (functions only, no site-specific paths),
`source`d by a tiny per-user caller that fills in paths and calls the function once per site.

These samples aren't runnable as-is — they cement the *shape* a caller should take. Copy one,
replace `{user}` / `{domain}` (and any other placeholder) with real values, and drop the
matching base script (see comment at the top of each sample) into `/opt/scripts/` (or wherever
your convention puts shared, world-readable scripts) on the target server.

- `logs_archive.sh` — moves log files older than N days into `YYYY/MM` folders at a
  destination (local disk or EFS-style mount).
- `logs_compress.sh` — compresses `YYYY/MM` folders older than a cutoff into `MM.tar.gz`.
- `logs_delete.sh` — deletes month archives (or leftover uncompressed folders) older than a
  retention window.
- `logs_archive_s3.sh` — same role as `logs_archive.sh`, but uploads to S3 and deletes the
  local file only after a successful upload, for hosts with no local/EFS archive destination.
  Bucket comes from the `S3_BUCKET` env var; credentials come from the environment or the
  instance's role — never hardcode either.
