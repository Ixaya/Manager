#!/bin/bash
# SAMPLE per-user caller for a shared base/logs_delete.sh.
# Source the base script, set paths, call delete_logs() once per site.
# Copy to /home/{user}/bin/logs_delete.sh and replace the placeholders.

source /opt/scripts/logs_delete.sh "$@"

HOME_PATH="/home/{user}"
DEST_LOG="mnt/private/logs"

delete_logs \
    "$HOME_PATH/$DEST_LOG/v1" \
    "App V1"

delete_logs \
    "$HOME_PATH/$DEST_LOG/v2" \
    "App V2"

if [[ "$DRY_RUN" == true ]]; then
    echo "=== DRY RUN COMPLETE ==="
else
    echo "=== OPERATION COMPLETE ==="
fi
