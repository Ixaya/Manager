#!/bin/bash
# SAMPLE per-user caller for a shared base/logs_archive_s3.sh.
# Export S3_BUCKET, source the base script, call archive_logs_s3() once per site.
# Copy to /home/{user}/bin/logs_archive_s3.sh and replace the placeholders.
# Credentials are never set here — the AWS CLI picks them up from the
# environment or the instance's role.

export S3_BUCKET="{s3-bucket-name}"

source /opt/scripts/logs_archive_s3.sh "$@"

HOME_PATH="/home/{user}"
APP_LOG="app/application/logs"

archive_logs_s3 \
    "$HOME_PATH/$APP_LOG" \
    "app-v1" \
    "App V1"

archive_logs_s3 \
    "$HOME_PATH/domains/{domain}/$APP_LOG" \
    "app-v2" \
    "App V2"

if [[ "$DRY_RUN" == true ]]; then
    echo "=== DRY RUN COMPLETE ==="
else
    echo "=== OPERATION COMPLETE ==="
fi
