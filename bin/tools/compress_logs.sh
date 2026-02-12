#!/bin/bash
# Log file compression script
# Compresses month folders older than specified days to save space
# Works with the year/month folder structure created by archive-logs.sh

# ============================================================================
# CONFIGURATION
# ============================================================================

DRY_RUN=false

# Parse command line arguments
if [[ "$1" == "--dry-run" ]]; then
    DRY_RUN=true
    echo "=== DRY RUN MODE - No folders will be compressed ==="
    echo
fi

# ============================================================================
# FUNCTION DEFINITION
# ============================================================================

# Calculate cutoff date as "previous month" (not current month, not this month)
# This ensures we only compress complete months that won't receive new logs
CURRENT_MONTH=$(date "+%Y/%m")
PREVIOUS_MONTH=$(date -d "1 month ago" "+%Y/%m" 2>/dev/null || date -v-1m "+%Y/%m" 2>/dev/null)

# For compression: compress anything before current month (so previous month and older)
CUTOFF_DATE="$CURRENT_MONTH"

echo "Cutoff date: $PREVIOUS_MONTH"
echo

# Function to compress old month folders from one archive location
compress_logs() {
    local DEST_BASE_DIR="$1"
    local SITE_NAME="$2"  # Optional: for display purposes
    
    echo "========================================"
    if [[ -n "$SITE_NAME" ]]; then
        echo "Processing: $SITE_NAME"
    fi
    echo "Archive: $DEST_BASE_DIR"
    echo "Compressing folders older than: $DAYS_OLD days"
    echo "========================================"
    
    # Check if base directory exists
    if [[ ! -d "$DEST_BASE_DIR" ]]; then
        echo "Warning: Archive directory '$DEST_BASE_DIR' does not exist - skipping"
        echo
        return 1
    fi
    
    # Find and process year/month directories
    local FOLDER_COUNT=0
    local ORIGINAL_SIZE=0
    local COMPRESSED_SIZE=0
    
    # Find all year folders
    for YEAR_DIR in "$DEST_BASE_DIR"/[0-9][0-9][0-9][0-9]; do
        if [[ ! -d "$YEAR_DIR" ]]; then
            continue
        fi
        
        YEAR=$(basename "$YEAR_DIR")
        
        # Find all month folders within this year
        for MONTH_DIR in "$YEAR_DIR"/[0-9][0-9]; do
            if [[ ! -d "$MONTH_DIR" ]]; then
                continue
            fi
            
            MONTH=$(basename "$MONTH_DIR")
            FOLDER_DATE="$YEAR/$MONTH"
            
            # Skip if already compressed
            if [[ -f "$MONTH_DIR.tar.gz" ]]; then
                echo "Skipping $FOLDER_DATE - already compressed"
                continue
            fi
            
            # Compare dates (simple string comparison works for YYYY/MM format)
            if [[ "$FOLDER_DATE" < "$CUTOFF_DATE" ]]; then
                # Get folder size before compression
                FOLDER_SIZE=$(du -sb "$MONTH_DIR" 2>/dev/null | cut -f1)
                ORIGINAL_SIZE=$((ORIGINAL_SIZE + FOLDER_SIZE))
                
                if [[ "$DRY_RUN" == true ]]; then
                    HUMAN_SIZE=$(numfmt --to=iec-i --suffix=B $FOLDER_SIZE 2>/dev/null || echo "$FOLDER_SIZE bytes")
                    echo "[DRY RUN] Would compress: $FOLDER_DATE ($HUMAN_SIZE)"
                    echo "[DRY RUN] Would create: $YEAR/$MONTH.tar.gz"
                    echo "[DRY RUN] Would delete: $FOLDER_DATE/"
                else
                    echo "Compressing: $FOLDER_DATE..."
                    
                    # Create compressed archive
                    # -C changes to parent directory, then compresses just the month folder
                    tar -czf "$MONTH_DIR.tar.gz" -C "$YEAR_DIR" "$MONTH" 2>/dev/null
                    
                    if [[ $? -eq 0 ]]; then
                        # Get compressed size
                        ARCHIVE_SIZE=$(stat -f%z "$MONTH_DIR.tar.gz" 2>/dev/null || stat -c%s "$MONTH_DIR.tar.gz" 2>/dev/null)
                        COMPRESSED_SIZE=$((COMPRESSED_SIZE + ARCHIVE_SIZE))
                        
                        # Calculate compression ratio
                        if [[ $FOLDER_SIZE -gt 0 ]]; then
                            RATIO=$((100 - (ARCHIVE_SIZE * 100 / FOLDER_SIZE)))
                        else
                            RATIO=0
                        fi
                        
                        HUMAN_ORIGINAL=$(numfmt --to=iec-i --suffix=B $FOLDER_SIZE 2>/dev/null || echo "$FOLDER_SIZE bytes")
                        HUMAN_COMPRESSED=$(numfmt --to=iec-i --suffix=B $ARCHIVE_SIZE 2>/dev/null || echo "$ARCHIVE_SIZE bytes")
                        
                        echo "Created: $YEAR/$MONTH.tar.gz ($HUMAN_ORIGINAL -> $HUMAN_COMPRESSED, saved ${RATIO}%)"
                        
                        # Delete the original folder
                        rm -rf "$MONTH_DIR"
                        if [[ $? -eq 0 ]]; then
                            echo "Deleted original folder: $FOLDER_DATE/"
                        else
                            echo "Warning: Could not delete original folder: $FOLDER_DATE/"
                        fi
                    else
                        echo "Error compressing: $FOLDER_DATE"
                    fi
                fi
                
                ((FOLDER_COUNT++))
            fi
        done
    done
    
    # Summary for this archive
    if [[ "$DRY_RUN" == true ]]; then
        HUMAN_ORIGINAL=$(numfmt --to=iec-i --suffix=B $ORIGINAL_SIZE 2>/dev/null || echo "$ORIGINAL_SIZE bytes")
        echo "Month folders that would be compressed: $FOLDER_COUNT"
        echo "Total size that would be compressed: $HUMAN_ORIGINAL"
    else
        if [[ $FOLDER_COUNT -gt 0 ]]; then
            HUMAN_ORIGINAL=$(numfmt --to=iec-i --suffix=B $ORIGINAL_SIZE 2>/dev/null || echo "$ORIGINAL_SIZE bytes")
            HUMAN_COMPRESSED=$(numfmt --to=iec-i --suffix=B $COMPRESSED_SIZE 2>/dev/null || echo "$COMPRESSED_SIZE bytes")
            SAVED=$((ORIGINAL_SIZE - COMPRESSED_SIZE))
            HUMAN_SAVED=$(numfmt --to=iec-i --suffix=B $SAVED 2>/dev/null || echo "$SAVED bytes")
            
            if [[ $ORIGINAL_SIZE -gt 0 ]]; then
                TOTAL_RATIO=$((100 - (COMPRESSED_SIZE * 100 / ORIGINAL_SIZE)))
            else
                TOTAL_RATIO=0
            fi
            
            echo "Month folders compressed: $FOLDER_COUNT"
            echo "Original size: $HUMAN_ORIGINAL"
            echo "Compressed size: $HUMAN_COMPRESSED"
            echo "Space saved: $HUMAN_SAVED (${TOTAL_RATIO}%)"
        else
            echo "No folders to compress"
        fi
    fi
    echo
}

# ============================================================================
# CALL THE FUNCTION FOR EACH SITE
# ============================================================================

HOME_PATH=""
APP_LOG="app/application/logs"
DEST_LOG="mnt/private/logs"

compress_logs \
    "$HOME_PATH/$DEST_LOG/" \
    "App Logs"

# compress_logs \
#     "$HOME_PATH/$DEST_LOG/site1" \
#     "App Site 1"

# compress_logs \
#     "$HOME_PATH/$DEST_LOG/site2" \
#     "App Site 2"

# ============================================================================
# FINAL SUMMARY
# ============================================================================

if [[ "$DRY_RUN" == true ]]; then
    echo "=== DRY RUN COMPLETE ==="
else
    echo "=== OPERATION COMPLETE ==="
fi