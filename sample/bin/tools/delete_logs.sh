#!/bin/bash
# Log file deletion script
# Deletes entire month folders older than specified days from the archive
# Works with the year/month folder structure created by archive-logs.sh

# ============================================================================
# CONFIGURATION
# ============================================================================
BASE_PATH="/home/pylc"
DAYS_OLD=180  # Delete logs older than 6 months
DRY_RUN=false

# Parse command line arguments
if [[ "$1" == "--dry-run" ]]; then
    DRY_RUN=true
    echo "=== DRY RUN MODE - No folders will be deleted ==="
    echo
fi

# ============================================================================
# FUNCTION DEFINITION
# ============================================================================

# Function to delete old month folders from one archive location
delete_old_logs() {
    local DEST_BASE_DIR="$1"
    local SITE_NAME="$2"  # Optional: for display purposes
    
    echo "========================================"
    if [[ -n "$SITE_NAME" ]]; then
        echo "Processing: $SITE_NAME"
    fi
    echo "Archive: $DEST_BASE_DIR"
    echo "Deleting folders older than: $DAYS_OLD days"
    echo "========================================"
    
    # Check if base directory exists
    if [[ ! -d "$DEST_BASE_DIR" ]]; then
        echo "Warning: Archive directory '$DEST_BASE_DIR' does not exist - skipping"
        echo
        return 1
    fi
    
    # Calculate the cutoff date (YYYY/MM format)
    # Folders older than this will be deleted
    CUTOFF_DATE=$(date -d "$DAYS_OLD days ago" "+%Y/%m" 2>/dev/null || date -v-${DAYS_OLD}d "+%Y/%m" 2>/dev/null)
    
    echo "Cutoff date: $CUTOFF_DATE (folders before this will be deleted)"
    echo
    
    # Find and process year/month directories
    local FOLDER_COUNT=0
    local FILE_COUNT=0
    
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
            
            # Compare dates (simple string comparison works for YYYY/MM format)
            if [[ "$FOLDER_DATE" < "$CUTOFF_DATE" ]]; then
                # Count files in this folder
                FOLDER_FILE_COUNT=$(find "$MONTH_DIR" -type f | wc -l)
                FILE_COUNT=$((FILE_COUNT + FOLDER_FILE_COUNT))
                
                if [[ "$DRY_RUN" == true ]]; then
                    echo "[DRY RUN] Would delete folder: $FOLDER_DATE ($FOLDER_FILE_COUNT files)"
                else
                    rm -rf "$MONTH_DIR"
                    if [[ $? -eq 0 ]]; then
                        echo "Deleted folder: $FOLDER_DATE ($FOLDER_FILE_COUNT files)"
                    else
                        echo "Error deleting folder: $FOLDER_DATE"
                    fi
                fi
                
                ((FOLDER_COUNT++))
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
    if [[ "$DRY_RUN" == true ]]; then
        echo "Month folders that would be deleted: $FOLDER_COUNT"
        echo "Files that would be deleted: $FILE_COUNT"
    else
        echo "Month folders deleted: $FOLDER_COUNT"
        echo "Files deleted: $FILE_COUNT"
    fi
    echo
}

# ============================================================================
# CALL THE FUNCTION FOR EACH SITE
# ============================================================================

# App site
delete_old_logs \
    "$BASE_PATH/app/private/logs/v1" \
    "App Site"

# Domain 1
delete_old_logs \
    "$BASE_PATH/domains/site1/private/logs/v1" \
    "Domain 1"

# Domain 2
delete_old_logs \
    "$BASE_PATH/domains/site2/private/logs/v1" \
    "Domain 2"

# Domain 3
delete_old_logs \
    "$BASE_PATH/domains/site3/private/logs/v1" \
    "Domain 3"

# Domain 4
delete_old_logs \
    "$BASE_PATH/domains/site4/private/logs/v1" \
    "Domain 4"

# ============================================================================
# FINAL SUMMARY
# ============================================================================

if [[ "$DRY_RUN" == true ]]; then
    echo "=== DRY RUN COMPLETE ==="
else
    echo "=== OPERATION COMPLETE ==="
fi