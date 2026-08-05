#!/bin/bash
# SAMPLE per-user caller for a shared base/logs_archive.sh.
# Source the base script, set paths, call archive_logs() once per site.
# Copy to /home/{user}/bin/logs_archive.sh and replace the placeholders.

source /opt/scripts/logs_archive.sh "$@"

HOME_PATH="/home/{user}"
APP_LOG="app/application/logs"
DEST_LOG="mnt/private/logs"

archive_logs \
    "$HOME_PATH/$APP_LOG" \
    "$HOME_PATH/$DEST_LOG/v1" \
    "App V1"

archive_logs \
    "$HOME_PATH/domains/{domain}/$APP_LOG" \
    "$HOME_PATH/$DEST_LOG/v2" \
    "App V2"

if [[ "$DRY_RUN" == true ]]; then
    echo "=== DRY RUN COMPLETE ==="
else
    echo "=== OPERATION COMPLETE ==="
fi
