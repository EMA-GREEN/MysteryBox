#!/bin/bash

# Create Access Token Script
# This script creates or updates the latest access token with 10-day expiry
# Only updates if token is expiring tomorrow (within 1 day)
# 
# Usage: ./create_access_token.sh

# Set timezone
export TZ="Africa/Dar_es_Salaam"

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Load environment variables
if [ -f .env ]; then
    export $(grep -v '^#' .env | xargs)
else
    echo "Error: .env file not found"
    exit 1
fi

# Database configuration
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE}"
DB_USERNAME="${DB_USERNAME}"
DB_PASSWORD="${DB_PASSWORD}"

# MNO Services configuration
TOKEN_ENDPOINT="${MNO_SERVICES_YAS_TOKEN_ENDPOINT}"
USERNAME="${MNO_SERVICES_YAS_USERNAME}"
PASSWORD="${MNO_SERVICES_YAS_PASSWORD}"

# Logging function
log_message() {
    local message="$1"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$timestamp] $message"
    
    # Use Laravel's default logging (this will be handled by the PHP script)
}

# Test database connection
test_database() {
    log_message "Testing database connection..."
    
    if mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -e "SELECT 1;" >/dev/null 2>&1; then
        log_message "✓ Database connection successful"
        return 0
    else
        log_message "✗ Cannot connect to database"
        return 1
    fi
}

# Get current token status
show_token_status() {
    log_message "=== Current Token Status ==="
    
    mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -e "
        SELECT 
            ID,
            CREATED_AT,
            EXPIRES_AT,
            CASE 
                WHEN EXPIRES_AT IS NULL THEN 'Unknown'
                WHEN EXPIRES_AT < NOW() THEN 'EXPIRED'
                WHEN DATEDIFF(EXPIRES_AT, NOW()) <= 1 THEN 'EXPIRING TOMORROW'
                ELSE 'VALID'
            END as STATUS
        FROM user_access_token 
        ORDER BY ID DESC 
        LIMIT 5;
    " 2>/dev/null || log_message "No tokens found or error querying database"
}

# Main execution
main() {
    log_message "=== Starting Access Token Creation Process ==="
    log_message "Token Endpoint: $TOKEN_ENDPOINT"
    log_message "Username: $USERNAME"
    log_message "Timezone: Africa/Dar_es_Salaam"
    log_message "Expiry: 10 days from creation"
    
    # Test database connection
    if ! test_database; then
        exit 1
    fi
    
    # Show current token status
    show_token_status
    
    # Run the PHP script
    log_message "Executing PHP token creation script..."
    
    if php create_access_token.php; then
        log_message "✓ Token creation process completed successfully"
    else
        log_message "✗ Token creation process failed"
        exit 1
    fi
    
    # Show final status
    log_message "=== Final Token Status ==="
    show_token_status
    
    log_message "=== Access Token Creation Process Completed ==="
}

# Run main function
main "$@" 