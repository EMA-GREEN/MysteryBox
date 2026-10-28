#!/bin/bash

# Daily log backup script for MysteryBox
# This script should be run daily via cron

# Set the log directory
LOG_DIR="/var/www/html/MysteryBox/storage/logs"
LOG_FILE="$LOG_DIR/laravel.log"
BACKUP_DIR="$LOG_DIR/backups"

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

# Get today's date in YYYY-MM-DD format
TODAY=$(date +%Y-%m-%d)

# Backup the current log file if it exists and has content
if [ -f "$LOG_FILE" ] && [ -s "$LOG_FILE" ]; then
    BACKUP_FILE="$BACKUP_DIR/laravel-$TODAY.log"
    
    # Copy the log file to backup
    cp "$LOG_FILE" "$BACKUP_FILE"
    
    # Clear the original log file
    > "$LOG_FILE"
    
    echo "$(date): Log backup completed - $BACKUP_FILE"
else
    echo "$(date): No log file to backup or file is empty"
fi

# Clean up old backups (keep last 30 days)
find "$BACKUP_DIR" -name "laravel-*.log" -mtime +30 -delete

echo "$(date): Log backup script completed" 