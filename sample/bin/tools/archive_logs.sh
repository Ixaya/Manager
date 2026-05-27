#!/bin/bash
# Log file archival script with year/month organization
# Moves log files older than 7 days from multiple sources to their respective destinations

# ============================================================================
# CONFIGURATION
# ============================================================================

DAYS_OLD=7
DRY_RUN=false

# Parse command line arguments
if [[ "$1" == "--dry-run" ]]; then
    DRY_RUN=true
    echo "=== DRY RUN MODE - No files will be moved ==="
    echo
fi

# ============================================================================
# FUNCTION DEFINITION
# ============================================================================

# Function to archive logs from one source to one destination
archive_logs() {
    local SOURCE_DIR="$1"
    local DEST_BASE_DIR="$2"
    local SITE_NAME="$3"  # Optional: for display purposes
    
    echo "========================================"
    if [[ -n "$SITE_NAME" ]]; then
        echo "Processing: $SITE_NAME"
    fi
    echo "Source: $SOURCE_DIR"
    echo "Destination: $DEST_BASE_DIR"
    echo "========================================"
    
    # Check if source directory exists
    if [[ ! -d "$SOURCE_DIR" ]]; then
        echo "Warning: Source directory '$SOURCE_DIR' does not exist - skipping"
        echo
        return 1
    fi
    
    # Create base destination directory if it doesn't exist (not in dry-run)
    if [[ ! -d "$DEST_BASE_DIR" ]]; then
        echo "Warning: Destination directory '$DEST_BASE_DIR' does not exist - skipping"
        echo
        return 1
    fi
    
    # Find and process files older than specified days
    local FILE_COUNT=0
    echo "Searching for log files older than $DAYS_OLD days..."
    echo
    
    # Find files matching pattern log-*.log that are older than specified days
    while IFS= read -r -d '' file; do
        filename=$(basename "$file")
        
        # Get the file's modification date to determine year/month folder
        # Using stat command (works on both Linux and macOS)
        if [[ "$(uname)" == "Darwin" ]]; then
            # macOS
            FILE_DATE=$(stat -f "%Sm" -t "%Y/%m" "$file")
        else
            # Linux
            FILE_DATE=$(date -r "$file" "+%Y/%m")
        fi
        
        # Construct destination path with year/month
        DEST_DIR="$DEST_BASE_DIR/$FILE_DATE"
        
        if [[ "$DRY_RUN" == true ]]; then
            echo "[DRY RUN] Would move: $file -> $DEST_DIR/$filename"
            if [[ ! -d "$DEST_DIR" ]]; then
                echo "[DRY RUN] Would create directory: $DEST_DIR"
            fi
        else
            # Create year/month directory if it doesn't exist
            if [[ ! -d "$DEST_DIR" ]]; then
                mkdir -p "$DEST_DIR"
                echo "Created directory: $DEST_DIR"
            fi
            
            mv "$file" "$DEST_DIR/$filename"
            if [[ $? -eq 0 ]]; then
                echo "Moved: $filename -> $FILE_DATE/"
            else
                echo "Error moving: $filename"
            fi
        fi
        
        ((FILE_COUNT++))
    done < <(find "$SOURCE_DIR" -maxdepth 1 -name "log-*.log" -type f -mtime +$DAYS_OLD -print0)
    
    # Summary for this source
    if [[ "$DRY_RUN" == true ]]; then
        echo "Files that would be moved: $FILE_COUNT"
    else
        echo "Files moved: $FILE_COUNT"
    fi
    echo
}

# ============================================================================
# CALL THE FUNCTION FOR EACH SITE
# ============================================================================

HOME_PATH=""
APP_LOG="app/application/logs"
DEST_LOG="mnt/private/logs"

archive_logs \
    "$HOME_PATH/$APP_LOG" \
    "$HOME_PATH/$DEST_LOG/" \
    "App Logs"

# archive_logs \
#     "$HOME_PATH/$APP_LOG" \
#     "$HOME_PATH/$DEST_LOG/site1" \
#     "App Site 1"

# archive_logs \
#     "$HOME_PATH/domains/site/$APP_LOG" \
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