#!/bin/bash
# Log file S3 archival script with year/month organization
# Uploads log files older than 7 days to S3 and deletes local copies once
# the upload succeeds. For servers with no EFS to move logs to — same role
# as logs_archive.sh, S3 instead of a local/EFS destination.
#
# Shared base — do not edit per-server/per-user. Deploy as-is to a shared,
# world-readable path and `source` it from a small per-user caller script
# that sets S3_BUCKET/HOME_PATH/APP_LOG and calls archive_logs_s3() for each
# site that user serves. See samples/logs_archive_s3.sh.
#
# Bucket comes from the S3_BUCKET env var — set it in the caller, never
# hardcode it here. Credentials come from the environment or the instance's
# EC2 role; the AWS CLI picks those up on its own, nothing to configure here.

# ============================================================================
# CONFIGURATION
# ============================================================================

DAYS_OLD=7
DRY_RUN=false

# Parse command line arguments
if [[ "$1" == "--dry-run" ]]; then
    DRY_RUN=true
    echo "=== DRY RUN MODE - No files will be uploaded ==="
    echo
fi

# Check if AWS CLI is available
if ! command -v aws &> /dev/null; then
    echo "Error: AWS CLI is not installed"
    exit 1
fi

# Bucket must come from the environment (set by the per-user caller)
if [[ -z "$S3_BUCKET" ]]; then
    echo "Error: S3_BUCKET is not set"
    exit 1
fi

# ============================================================================
# FUNCTION DEFINITION
# ============================================================================

# Function to archive logs from one source to S3
archive_logs_s3() {
    local SOURCE_DIR="$1"
    local S3_PATH_PREFIX="$2"  # e.g., "app" or "domains/site1"
    local SITE_NAME="$3"        # Optional: for display purposes

    echo "========================================"
    if [[ -n "$SITE_NAME" ]]; then
        echo "Processing: $SITE_NAME"
    fi
    echo "Source: $SOURCE_DIR"
    echo "S3 Destination: s3://$S3_BUCKET/$S3_PATH_PREFIX/YYYY/MM/"
    echo "========================================"

    # Check if source directory exists
    if [[ ! -d "$SOURCE_DIR" ]]; then
        echo "Warning: Source directory '$SOURCE_DIR' does not exist - skipping"
        echo
        return 1
    fi

    # Find and process files older than specified days
    local FILE_COUNT=0
    local UPLOADED_COUNT=0
    local ERROR_COUNT=0
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

        # Construct S3 path with year/month
        S3_DEST="s3://$S3_BUCKET/$S3_PATH_PREFIX/$FILE_DATE/$filename"

        if [[ "$DRY_RUN" == true ]]; then
            echo "[DRY RUN] Would upload: $file"
            echo "[DRY RUN]           to: $S3_DEST"
            echo "[DRY RUN] Would delete: $file"
        else
            echo "Uploading: $filename -> $FILE_DATE/"
            aws s3 cp "$file" "$S3_DEST" --no-progress

            if [[ $? -eq 0 ]]; then
                # Delete local file only after successful upload
                rm "$file"
                if [[ $? -eq 0 ]]; then
                    echo "Uploaded and deleted: $filename"
                    ((UPLOADED_COUNT++))
                else
                    echo "Warning: Uploaded but could not delete local file: $filename"
                    ((UPLOADED_COUNT++))
                fi
            else
                echo "Error uploading: $filename (keeping local copy)"
                ((ERROR_COUNT++))
            fi
        fi

        ((FILE_COUNT++))
    done < <(find "$SOURCE_DIR" -maxdepth 1 -name "log-*.log" -type f -mtime +$DAYS_OLD -print0)

    # Summary for this source
    if [[ "$DRY_RUN" == true ]]; then
        echo "Files that would be uploaded: $FILE_COUNT"
    else
        echo "Files uploaded: $UPLOADED_COUNT"
        if [[ $ERROR_COUNT -gt 0 ]]; then
            echo "Errors: $ERROR_COUNT"
        fi
    fi
    echo
}
