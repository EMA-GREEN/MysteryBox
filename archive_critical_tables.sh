#!/bin/bash

# Critical Tables Backup Script
# This script creates daily backups of critical tables using Archive Tables approach

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Load environment variables from .env file
if [ -f "$SCRIPT_DIR/.env" ]; then
    export $(grep -v '^#' "$SCRIPT_DIR/.env" | xargs)
else
    echo "Error: .env file not found in $SCRIPT_DIR"
    exit 1
fi

# Configuration from .env
DB_HOST="${DB_HOST:-localhost}"
DB_USER="${DB_USERNAME:-root}"
DB_PASSWORD="${DB_PASSWORD:-}"
DB_NAME="${DB_DATABASE:-mystery_box}"
DATE_SUFFIX=$(TZ=Africa/Dar_es_Salaam date +%Y%m%d)
TIMESTAMP=$(TZ=Africa/Dar_es_Salaam date +"%Y-%m-%d %H:%M:%S")

# Critical tables to backup
TABLES=(
    "renewal_payments"
    "renewal_payment_callbacks"
    "app_notifications"
    "sms_notifications"
    "sms_logs"
    "payment_notifications"
    "payment_callbacks"
    "payments"
    "mobile_money_transaction_notifications"
    "mystery_box_award_notifications"
    "renewal_content_notifications"
    "mpush_subs_init_notifications"
)

# Create logs directory if it doesn't exist
LOG_DIR="$SCRIPT_DIR/storage/logs"
mkdir -p "$LOG_DIR"

# Log file
LOG_FILE="$LOG_DIR/archive_${DATE_SUFFIX}.log"

# Function to log messages
log_message() {
    echo "[$TIMESTAMP] $1" | tee -a "$LOG_FILE"
}

# Function to backup a single table using Archive Tables approach
backup_table() {
    local table_name="$1"
    local archive_table_name="${table_name}_${DATE_SUFFIX}"
    
    log_message "Starting backup of table: $table_name to archive: $archive_table_name"
    
    # Check if table exists and has data
    local table_exists=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "
        SELECT COUNT(*) FROM information_schema.tables 
        WHERE table_schema = '$DB_NAME' AND table_name = '$table_name';
    " | tail -n 1)
    
    if [ "$table_exists" -eq 0 ]; then
        log_message "⚠ Table $table_name does not exist, skipping..."
        return 0
    fi
    
    # Check if table has data
    local row_count=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "
        SELECT COUNT(*) as count FROM \`$table_name\`;
    " | tail -n 1)
    
    if [ "$row_count" -eq 0 ]; then
        log_message "⚠ Table $table_name is empty, skipping..."
        return 0
    fi
    
    # Check if archive table already exists
    local archive_exists=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "
        SELECT COUNT(*) FROM information_schema.tables 
        WHERE table_schema = '$DB_NAME' AND table_name = '$archive_table_name';
    " | tail -n 1)
    
    if [ "$archive_exists" -gt 0 ]; then
        log_message "⚠ Archive table $archive_table_name already exists, dropping it first..."
        mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "
            DROP TABLE \`$archive_table_name\`;
        "
    fi
    
    # Archive the table using RENAME
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "
        RENAME TABLE \`$table_name\` TO \`$archive_table_name\`;
    "
    
    if [ $? -eq 0 ]; then
        log_message "✓ Successfully archived $table_name to $archive_table_name"
        log_message "  Rows archived: $row_count"
        
        # Create new empty table with same structure
        mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "
            CREATE TABLE \`$table_name\` LIKE \`$archive_table_name\`;
        "
        
        if [ $? -eq 0 ]; then
            log_message "✓ Created new empty table: $table_name"
        else
            log_message "✗ Failed to create new table: $table_name"
            return 1
        fi
    else
        log_message "✗ Failed to archive table $table_name"
        return 1
    fi
}

# Function to cleanup old archive tables (keep last 1095 days, 3 years)
cleanup_old_backups() {
    log_message "Cleaning up archive tables older than 1095 days..."
    
    local cutoff_date=$(TZ=Africa/Dar_es_Salaam date -d "1095 days ago" +%Y%m%d)
    local deleted_count=0
    
    for table in "${TABLES[@]}"; do
        # Get all archive tables for this table
        local archive_tables=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "
            SHOW TABLES LIKE '${table}_%';
        " | grep -v "Tables_in" | awk '{print $1}')
        
        for archive_table in $archive_tables; do
            # Extract date from table name
            local table_date=$(echo "$archive_table" | sed "s/${table}_//")
            
            # Check if date is older than cutoff
            if [[ "$table_date" < "$cutoff_date" ]]; then
                mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -e "
                    DROP TABLE IF EXISTS \`$archive_table\`;
                "
                if [ $? -eq 0 ]; then
                    log_message "  Dropped old archive table: $archive_table"
                    ((deleted_count++))
                fi
            fi
        done
    done
    
    log_message "✓ Cleanup completed. Dropped $deleted_count old archive tables"
}

# Function to cleanup old log files (keep last 30 days)
cleanup_old_logs() {
    log_message "Cleaning up log files older than 30 days..."
    
    local deleted_logs=$(find "$LOG_DIR" -name "archive_*.log" -mtime +30 -delete -print | wc -l)
    log_message "✓ Cleanup completed. Deleted $deleted_logs old log files"
}

# Main backup process
main() {
    log_message "=== Starting Critical Tables Archive Process ==="
    log_message "Database: $DB_NAME"
    log_message "Database Host: $DB_HOST"
    log_message "Database User: $DB_USER"
    log_message "Date suffix: $DATE_SUFFIX"
    log_message "Timezone: Africa/Dar_es_Salaam"
    log_message "Log file: $LOG_FILE"
    
    # Check if mysql is available
    if ! command -v mysql &> /dev/null; then
        log_message "✗ mysql client is not installed or not in PATH"
        exit 1
    fi
    
    # Check database connection
    if ! mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" -e "USE $DB_NAME;" 2>/dev/null; then
        log_message "✗ Cannot connect to database. Please check credentials in .env file."
        exit 1
    fi
    
    log_message "✓ Database connection successful"
    
    # Backup each table
    local failed_backups=0
    for table in "${TABLES[@]}"; do
        if ! backup_table "$table"; then
            ((failed_backups++))
        fi
    done
    
    # Cleanup old backups
    cleanup_old_backups
    
    # Cleanup old logs
    cleanup_old_logs
    
    # Summary
    log_message "=== Backup Summary ==="
    log_message "Total tables: ${#TABLES[@]}"
    log_message "Successful archives: $((${#TABLES[@]} - failed_backups))"
    log_message "Failed archives: $failed_backups"
    
    if [ $failed_backups -eq 0 ]; then
        log_message "✓ All archives completed successfully"
        exit 0
    else
        log_message "✗ Some archives failed"
        exit 1
    fi
}

# Run main function
main "$@"