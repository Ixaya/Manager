#!/bin/bash
# SAMPLE per-user caller for a shared base/logs_compress.sh.
# Source the base script, set paths, call compress_logs() once per site.
# Copy to /home/{user}/bin/logs_compress.sh and replace the placeholders.

source /opt/scripts/logs_compress.sh "$@"

HOME_PATH="/home/{user}"
DEST_LOG="mnt/private/logs"

compress_logs \
    "$HOME_PATH/$DEST_LOG/v1" \
    "App V1"

compress_logs \
    "$HOME_PATH/$DEST_LOG/v2" \
    "App V2"

if [[ "$DRY_RUN" == true ]]; then
    echo "=== DRY RUN COMPLETE ==="
else
    echo "=== OPERATION COMPLETE ==="
fi
