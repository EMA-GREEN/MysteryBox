#!/bin/bash

# Mobile Money Queue Workers Auto-Restart Script for Linux/Unix
# This script monitors queue workers and automatically restarts them if they fail

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_DIR="$SCRIPT_DIR/storage/logs"
PID_DIR="$SCRIPT_DIR/storage/framework/queue"
MONITOR_LOG_FILE="$LOG_DIR/worker_monitor.log"
CHECK_INTERVAL=30  # Check every 30 seconds
DAEMON_MODE=false
VERBOSE=false

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Worker configurations
declare -A WORKERS
WORKERS[high]="mobile_money_high_priority"
WORKERS[medium]="mobile_money_medium_priority"
WORKERS[low]="mobile_money_low_priority"

# Redis connection configurations for each priority
declare -A REDIS_CONNECTIONS
REDIS_CONNECTIONS[high]="redis_mobile_money"
REDIS_CONNECTIONS[medium]="redis_mobile_money_medium"
REDIS_CONNECTIONS[low]="redis_mobile_money_low"

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -i|--interval)
            CHECK_INTERVAL="$2"
            shift 2
            ;;
        -d|--daemon)
            DAEMON_MODE=true
            shift
            ;;
        -v|--verbose)
            VERBOSE=true
            shift
            ;;
        -h|--help)
            echo "Usage: $0 [OPTIONS]"
            echo "Options:"
            echo "  -i, --interval SECONDS  Check interval in seconds (default: 30)"
            echo "  -d, --daemon           Run in daemon mode (continuous monitoring)"
            echo "  -v, --verbose          Verbose output"
            echo "  -h, --help             Show this help message"
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

# Ensure directories exist
mkdir -p "$LOG_DIR"
mkdir -p "$PID_DIR"

# Logging function
log_message() {
    local level="$1"
    local message="$2"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    local log_entry="[$timestamp] [$level] $message"
    
    # Always log to file
    echo "$log_entry" >> "$MONITOR_LOG_FILE"
    
    # Console output based on level and verbose setting
    if [[ "$VERBOSE" == "true" ]] || [[ "$level" == "ERROR" ]] || [[ "$level" == "WARN" ]]; then
        case "$level" in
            "ERROR")
                echo -e "${RED}$log_entry${NC}"
                ;;
            "WARN")
                echo -e "${YELLOW}$log_entry${NC}"
                ;;
            "SUCCESS")
                echo -e "${GREEN}$log_entry${NC}"
                ;;
            *)
                echo -e "${BLUE}$log_entry${NC}"
                ;;
        esac
    fi
}

# Check if Redis is running
check_redis() {
    if redis-cli ping > /dev/null 2>&1; then
        return 0
    else
        return 1
    fi
}

# Check if a worker process is running
check_worker_process() {
    local priority="$1"
    local pid_file="$PID_DIR/mobile_money_${priority}_priority.pid"
    
    if [[ ! -f "$pid_file" ]]; then
        return 1
    fi
    
    local pid=$(cat "$pid_file" 2>/dev/null)
    if [[ -z "$pid" ]]; then
        return 1
    fi
    
    # Check if process exists and is a PHP queue worker
    if kill -0 "$pid" 2>/dev/null; then
        local cmd=$(ps -p "$pid" -o cmd --no-headers 2>/dev/null || echo "")
        if [[ "$cmd" == *"queue:work"* ]]; then
            return 0
        else
            log_message "WARN" "Process $pid exists but is not a queue worker"
            return 1
        fi
    else
        return 1
    fi
}

# Start a worker
start_worker() {
    local priority="$1"
    local queue="${WORKERS[$priority]}"
    local connection="${REDIS_CONNECTIONS[$priority]}"
    local pid_file="$PID_DIR/mobile_money_${priority}_priority.pid"
    local log_file="$LOG_DIR/mobile_money_${priority}_priority.log"
    
    log_message "INFO" "Starting $priority priority worker..."
    
    # Start the worker in background
    nohup php artisan queue:work "$connection" \
        --queue="$queue" \
        --sleep=3 \
        --tries=3 \
        --max-time=3600 \
        --daemon \
        >> "$log_file" 2>&1 &
    
    local pid=$!
    
    # Save PID
    echo "$pid" > "$pid_file"
    
    # Wait a moment and check if it's still running
    sleep 2
    if kill -0 "$pid" 2>/dev/null; then
        log_message "SUCCESS" "Started $priority priority worker with PID: $pid"
        return 0
    else
        log_message "ERROR" "Failed to start $priority priority worker"
        rm -f "$pid_file"
        return 1
    fi
}

# Stop a worker
stop_worker() {
    local priority="$1"
    local pid_file="$PID_DIR/mobile_money_${priority}_priority.pid"
    
    if [[ ! -f "$pid_file" ]]; then
        return 0
    fi
    
    local pid=$(cat "$pid_file" 2>/dev/null)
    if [[ -z "$pid" ]]; then
        rm -f "$pid_file"
        return 0
    fi
    
    log_message "INFO" "Stopping $priority priority worker (PID: $pid)..."
    
    if kill -0 "$pid" 2>/dev/null; then
        # Try graceful shutdown first
        kill -TERM "$pid" 2>/dev/null
        
        # Wait up to 10 seconds for graceful shutdown
        local count=0
        while kill -0 "$pid" 2>/dev/null && [[ $count -lt 10 ]]; do
            sleep 1
            ((count++))
        done
        
        # Force kill if still running
        if kill -0 "$pid" 2>/dev/null; then
            kill -KILL "$pid" 2>/dev/null
            log_message "WARN" "Force killed $priority priority worker"
        else
            log_message "SUCCESS" "Gracefully stopped $priority priority worker"
        fi
    fi
    
    rm -f "$pid_file"
    return 0
}

# Check and restart workers if needed
check_and_restart_workers() {
    log_message "INFO" "Checking worker status..."
    
    # Check Redis first
    if ! check_redis; then
        log_message "ERROR" "Redis is not running! Workers cannot function without Redis."
        return 1
    fi
    
    local restart_count=0
    
    for priority in "${!WORKERS[@]}"; do
        if ! check_worker_process "$priority"; then
            log_message "WARN" "$priority priority worker is not running. Attempting to restart..."
            
            # Clean up any stale PID file
            local pid_file="$PID_DIR/mobile_money_${priority}_priority.pid"
            rm -f "$pid_file"
            
            # Start the worker
            if start_worker "$priority"; then
                log_message "SUCCESS" "$priority priority worker restarted successfully"
                ((restart_count++))
            else
                log_message "ERROR" "Failed to restart $priority priority worker"
            fi
        else
            log_message "INFO" "$priority priority worker is running normally"
        fi
    done
    
    if [[ $restart_count -gt 0 ]]; then
        log_message "INFO" "Restarted $restart_count worker(s)"
    fi
    
    return 0
}

# Signal handlers for graceful shutdown
cleanup() {
    log_message "INFO" "Received shutdown signal. Cleaning up..."
    exit 0
}

trap cleanup SIGTERM SIGINT

# Main execution
log_message "INFO" "=== Mobile Money Worker Monitor Started ==="
log_message "INFO" "Check interval: $CHECK_INTERVAL seconds"
log_message "INFO" "Daemon mode: $DAEMON_MODE"
log_message "INFO" "Verbose mode: $VERBOSE"

if [[ "$DAEMON_MODE" == "true" ]]; then
    log_message "INFO" "Running in daemon mode. Send SIGTERM or SIGINT to stop."
    
    while true; do
        check_and_restart_workers
        sleep "$CHECK_INTERVAL"
    done
else
    # Single check
    check_and_restart_workers
fi

log_message "INFO" "=== Mobile Money Worker Monitor Stopped ==="