#!/bin/bash
# Log archive deletion script
# Deletes compressed month archives (and any leftover uncompressed month
# folders) older than DAYS_OLD from the archive.
# Works with the year/month folder structure created by logs_archive.sh /
# logs_compress.sh.
#
# Shared base — do not edit per-server/per-user. Deploy as-is to a shared,
# world-readable path and `source` it from a small per-user caller script
# that sets DEST_LOG paths and calls delete_logs() for each site that
# user serves. See samples/logs_delete.sh.

# ============================================================================
# CONFIGURATION
# ============================================================================

DAYS_OLD=180  # Delete archives older than 6 months
DRY_RUN=false

# Parse command line arguments
if [[ "$1" == "--dry-run" ]]; then
    DRY_RUN=true
    echo "=== DRY RUN MODE - Nothing will be deleted ==="
    echo
fi

# ============================================================================
# FUNCTION DEFINITION
# ============================================================================

CUTOFF_DATE=$(date -d "$DAYS_OLD days ago" "+%Y/%m" 2>/dev/null || date -v-${DAYS_OLD}d "+%Y/%m" 2>/dev/null)

# Function to delete old month archives from one archive location
delete_logs() {
    local DEST_BASE_DIR="$1"
    local SITE_NAME="$2"  # Optional: for display purposes

    echo "========================================"
    if [[ -n "$SITE_NAME" ]]; then
        echo "Processing: $SITE_NAME"
    fi
    echo "Archive: $DEST_BASE_DIR"
    echo "Deleting entries older than: $CUTOFF_DATE"
    echo "========================================"

    # Check if base directory exists
    if [[ ! -d "$DEST_BASE_DIR" ]]; then
        echo "Warning: Archive directory '$DEST_BASE_DIR' does not exist - skipping"
        echo
        return 1
    fi

    # Find and process year/month entries
    local ENTRY_COUNT=0
    local TOTAL_SIZE=0

    # Find all year folders
    for YEAR_DIR in "$DEST_BASE_DIR"/[0-9][0-9][0-9][0-9]; do
        if [[ ! -d "$YEAR_DIR" ]]; then
            continue
        fi

        YEAR=$(basename "$YEAR_DIR")

        # Find compressed month archives (normal case, post-compression)
        for MONTH_ARCHIVE in "$YEAR_DIR"/[0-9][0-9].tar.gz; do
            if [[ ! -f "$MONTH_ARCHIVE" ]]; then
                continue
            fi

            MONTH=$(basename "$MONTH_ARCHIVE" .tar.gz)
            FOLDER_DATE="$YEAR/$MONTH"

            if [[ "$FOLDER_DATE" < "$CUTOFF_DATE" ]]; then
                ENTRY_SIZE=$(stat -f%z "$MONTH_ARCHIVE" 2>/dev/null || stat -c%s "$MONTH_ARCHIVE" 2>/dev/null)
                TOTAL_SIZE=$((TOTAL_SIZE + ENTRY_SIZE))
                HUMAN_SIZE=$(numfmt --to=iec-i --suffix=B $ENTRY_SIZE 2>/dev/null || echo "$ENTRY_SIZE bytes")

                if [[ "$DRY_RUN" == true ]]; then
                    echo "[DRY RUN] Would delete archive: $FOLDER_DATE.tar.gz ($HUMAN_SIZE)"
                else
                    rm -f "$MONTH_ARCHIVE"
                    if [[ $? -eq 0 ]]; then
                        echo "Deleted archive: $FOLDER_DATE.tar.gz ($HUMAN_SIZE)"
                    else
                        echo "Error deleting archive: $FOLDER_DATE.tar.gz"
                    fi
                fi

                ((ENTRY_COUNT++))
            fi
        done

        # Find leftover uncompressed month folders (compression skipped/failed)
        for MONTH_DIR in "$YEAR_DIR"/[0-9][0-9]; do
            if [[ ! -d "$MONTH_DIR" ]]; then
                continue
            fi

            MONTH=$(basename "$MONTH_DIR")
            FOLDER_DATE="$YEAR/$MONTH"

            if [[ "$FOLDER_DATE" < "$CUTOFF_DATE" ]]; then
                FOLDER_SIZE=$(du -sb "$MONTH_DIR" 2>/dev/null | cut -f1)
                TOTAL_SIZE=$((TOTAL_SIZE + FOLDER_SIZE))
                HUMAN_SIZE=$(numfmt --to=iec-i --suffix=B $FOLDER_SIZE 2>/dev/null || echo "$FOLDER_SIZE bytes")

                if [[ "$DRY_RUN" == true ]]; then
                    echo "[DRY RUN] Would delete uncompressed folder: $FOLDER_DATE/ ($HUMAN_SIZE)"
                else
                    rm -rf "$MONTH_DIR"
                    if [[ $? -eq 0 ]]; then
                        echo "Deleted uncompressed folder: $FOLDER_DATE/ ($HUMAN_SIZE)"
                    else
                        echo "Error deleting folder: $FOLDER_DATE/"
                    fi
                fi

                ((ENTRY_COUNT++))
            fi
        done

        # Clean up empty year directories (only if not in dry-run)
        if [[ "$DRY_RUN" == false && -d "$YEAR_DIR" ]]; then
            if [[ -z "$(ls -A "$YEAR_DIR")" ]]; then
                rmdir "$YEAR_DIR"
                echo "Removed empty year directory: $YEAR"
            fi
        fi
    done

    # Summary for this archive
    HUMAN_TOTAL=$(numfmt --to=iec-i --suffix=B $TOTAL_SIZE 2>/dev/null || echo "$TOTAL_SIZE bytes")
    if [[ "$DRY_RUN" == true ]]; then
        echo "Entries that would be deleted: $ENTRY_COUNT"
        echo "Total size that would be freed: $HUMAN_TOTAL"
    else
        if [[ $ENTRY_COUNT -gt 0 ]]; then
            echo "Entries deleted: $ENTRY_COUNT"
            echo "Total size freed: $HUMAN_TOTAL"
        else
            echo "No entries to delete"
        fi
    fi
    echo
}
