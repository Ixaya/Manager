# File, path and link contracts — validation record

## How it was verified

Throwaway probes on the Docker stack, PHP 8.4.27, through real API keys,
plus a dry-run and a real-bucket S3 harness. On the final tree:

- `pdo/*` against PostgreSQL 18.6, MariaDB 12.3.3 and MySQL 8.4.11:
  identical results on all three.
- Real S3 (`aws/aws-sdk-php` 3.403.2): 47 of 47 cases, nothing left in the
  bucket.
- PHPStan level 5 and php-cs-fixer clean on every shipped file.
- Id validation: the engines disagree on a malformed id, so the sample
  validates before it looks up.

## Not covered

- Native drivers (`postgre`, `mysqli`); SQL Server for the id check.
- The S3 branches of `display_image()` and `get_file_base64()`.
- Browser rendering behind the nginx `location` (headers only).
- A rendered `load_clean_view()` (the scaffold ships no clean layout) and
  `resolve_theme()`'s mailer path.
- `MGR_Amazon_aws_lib` beyond its argument handling, and `third_party/BE`.
