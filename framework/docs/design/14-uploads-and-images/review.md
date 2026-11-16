# Uploads and images — validation record

## How it was verified

Throwaway probes on the Docker stack, PHP 8.4.27, through real API keys, on
the final tree:

- Attachments: `pdo/*` against PostgreSQL 18.6, MariaDB 12.3.3 and MySQL
  8.4.11, strict and empty `sql_mode`, `db_debug` off and on: identical.
- Real S3 (`aws/aws-sdk-php` 3.403.2): 55 pass, 5 dry-only skips, nothing
  left in the bucket; dry run 60 of 60. The S3 read paths of
  `display_image()` and `get_file_base64()` ran against a real bucket.
- Local images through the sample's REST endpoints: 33 cases, the ceiling's
  worst case, EXIF orientation 1–8 all upright, two uploads in one request.
- The scaffold image build: WebP and `exif` add 871,575 bytes.
- PHPStan level 5 and php-cs-fixer clean.

## Not covered

- Native drivers (`postgre`, `mysqli`); SQLite and SQL Server.
- The S3 thumbnail resize against CI's real `Image_lib`.
- A stack without a WebP encoder; a real legacy `.doc`/`.xls`; a file
  detected as `image/svg`; `show_flashdata` mode.
- Consuming-project overrides of the `upload_image()` signatures and readers
  of `attachment_id` `false`.
