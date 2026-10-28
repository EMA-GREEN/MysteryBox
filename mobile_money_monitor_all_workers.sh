#!/bin/bash

# Mobile Money Queue Workers Monitor - Improved Version
# This script ensures all three priority workers (high, medium, low) are running
# and restarts them if they stop

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_DIR="$SCRIPT_DIR/storage/logs"
PID_DIR="$SCRIPT_DIR/storage/framework/queue"
CHECK_INTERVAL=30
VERBOSE=false
DAEMON_MODE=true

# Worker configurations
declare -A WORKERS
WORKERS[high]="mobile_money_high_priority"
WORKERS[medium]="mobile_money_medium_priority"
WORKERS[low]="mobile_money_low_priority"

# Worker counts (mirror start_queue_workers.sh 200 TPS recommendation)
HIGH_WORKER_COUNT=6
MEDIUM_WORKER_COUNT=3
LOW_WORKER_COUNT=2

declare -A REDIS_CONNECTIONS
REDIS_CONNECTIONS[high]="redis_mobile_money"
REDIS_CONNECTIONS[medium]="redis_mobile_money_medium"
REDIS_CONNECTIONS[low]="redis_mobile_money_low"

# Ensure directories exist
mkdir -p "$LOG_DIR" "$PID_DIR"

# Logging function
log_message() {
    local level="$1"
    local message="$2"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    local log_entry="[$timestamp] [$level] $message"
    
    echo "$log_entry"
    echo "$log_entry" >> "$LOG_DIR/mobile_money_monitor.log"
}

# Verbose logging
verbose_log() {
    if [[ "$VERBOSE" == "true" ]]; then
        log_message "DEBUG" "$1"
    fi
}

# Check if Redis is running
check_redis() {
    verbose_log "Checking Redis connectivity..."
    
    if redis-cli ping > /dev/null 2>&1; then
        verbose_log "Redis is running and responding to ping"
        return 0
    else
        log_message "ERROR" "Redis is not running or not responding to ping"
        return 1
    fi
}

# Check if a specific worker process is running
check_worker_process() {
    local priority="$1"
    # Support multiple PID files per priority
    local pids_found=0
    for pid_file in "$PID_DIR/mobile_money_${priority}_priority_"*.pid "$PID_DIR/mobile_money_${priority}_priority.pid"; do
        [ -e "$pid_file" ] || continue
        ((pids_found++))
        verbose_log "Checking $priority priority worker from $(basename "$pid_file")..."
        
        local pid
        pid=$(cat "$pid_file" 2>/dev/null)
        
        if [[ -z "$pid" ]] || ! [[ "$pid" =~ ^[0-9]+$ ]]; then
            verbose_log "Invalid PID in file for $priority priority worker: $pid"
            continue
        fi
        
        if ! kill -0 "$pid" 2>/dev/null; then
            verbose_log "Process $pid for $priority priority worker is not running"
            continue
        fi
        
        local process_info
        process_info=$(ps -p "$pid" -o args= 2>/dev/null)
        
        if [[ ! "$process_info" =~ queue:work.*${WORKERS[$priority]} ]]; then
            verbose_log "Process $pid is not the expected queue worker for $priority priority"
            continue
        fi
        
        verbose_log "$priority priority worker PID $pid is running"
    done
    
    if [[ $pids_found -eq 0 ]]; then
        verbose_log "No PID files found for $priority priority"
        return 1
    fi
    return 0
}

# Start a worker (with index)
start_worker() {
    local priority="$1"
    local idx="$2"
    local queue="${WORKERS[$priority]}"
    local connection="${REDIS_CONNECTIONS[$priority]}"
    local pid_file="$PID_DIR/mobile_money_${priority}_priority_${idx}.pid"
    local log_file="$LOG_DIR/mobile_money_${priority}_priority_${idx}.log"
    
    log_message "INFO" "Starting $priority priority worker #$idx (queue: $queue, connection: $connection)..."
    
    nohup php artisan queue:work "$connection" \
        --queue="$queue" \
        --sleep=3 \
        --tries=3 \
        --max-time=3600 \
        >> "$log_file" 2>&1 &
    
    local pid=$!
    echo "$pid" > "$pid_file"
    
    sleep 2
    if kill -0 "$pid" 2>/dev/null; then
        log_message "SUCCESS" "Started $priority priority worker #$idx with PID: $pid"
        return 0
    else
        log_message "ERROR" "Failed to start $priority priority worker #$idx"
        rm -f "$pid_file"
        return 1
    fi
}

# Ensure the desired number of workers are running for a given priority
ensure_worker_count() {
    local priority="$1"
    local desired=0
    case "$priority" in
        high) desired=$HIGH_WORKER_COUNT ;;
        medium) desired=$MEDIUM_WORKER_COUNT ;;
        low) desired=$LOW_WORKER_COUNT ;;
    esac
    
    local running=0
    # Count valid running workers
    for pid_file in "$PID_DIR/mobile_money_${priority}_priority_"*.pid; do
        [ -e "$pid_file" ] || continue
        local pid=$(cat "$pid_file" 2>/dev/null)
        if [[ -n "$pid" ]] && kill -0 "$pid" 2>/dev/null; then
            local process_info
            process_info=$(ps -p "$pid" -o args= 2>/dev/null)
            if [[ "$process_info" =~ queue:work.*${WORKERS[$priority]} ]]; then
                ((running++))
            else
                rm -f "$pid_file"
            fi
        else
            rm -f "$pid_file"
        fi
    done
    
    if [[ $running -lt $desired ]]; then
        local to_start=$((desired - running))
        log_message "WARN" "$priority priority has $running/$desired running. Starting $to_start more..."
        # Start missing workers, assign next indices based on existing files
        local next_idx=1
        while [ -e "$PID_DIR/mobile_money_${priority}_priority_${next_idx}.pid" ]; do
            next_idx=$((next_idx+1))
        done
        for ((i=0; i<to_start; i++)); do
            start_worker "$priority" "$((next_idx+i))"
            sleep 1
        done
    else
        log_message "INFO" "$priority priority has $running/$desired workers running"
    fi
}

# Stop a worker
stop_worker() {
    local priority="$1"
    # Stop all workers for this priority
    for pid_file in "$PID_DIR/mobile_money_${priority}_priority_"*.pid "$PID_DIR/mobile_money_${priority}_priority.pid"; do
        [ -e "$pid_file" ] || continue
        local pid
        pid=$(cat "$pid_file" 2>/dev/null)
        if [[ -n "$pid" ]] && kill -0 "$pid" 2>/dev/null; then
            log_message "INFO" "Stopping $priority priority worker PID $pid (file: $(basename "$pid_file"))..."
            kill -TERM "$pid" 2>/dev/null
            sleep 2
            if kill -0 "$pid" 2>/dev/null; then
                kill -KILL "$pid" 2>/dev/null
                log_message "WARN" "Force killed $priority priority worker PID $pid"
            else
                log_message "INFO" "Gracefully stopped $priority priority worker PID $pid"
            fi
        fi
        rm -f "$pid_file"
    done
}

# Stop all workers
stop_all_workers() {
    log_message "INFO" "Stopping all workers..."
    for priority in high medium low; do
        stop_worker "$priority"
    done
    log_message "INFO" "All workers stopped"
}

# Check and restart workers if needed
check_and_restart_workers() {
    log_message "INFO" "Checking all worker statuses..."
    
    if ! check_redis; then
        log_message "ERROR" "Redis connectivity issues detected! Workers cannot function without Redis."
        return 1
    fi
    
    local restart_count=0
    local running_total=0
    
    for priority in high medium low; do
        ensure_worker_count "$priority"
        # Update running_total from current count
        local running=0
        for pid_file in "$PID_DIR/mobile_money_${priority}_priority_"*.pid; do
            [ -e "$pid_file" ] || continue
            local pid=$(cat "$pid_file" 2>/dev/null)
            if [[ -n "$pid" ]] && kill -0 "$pid" 2>/dev/null; then
                ((running++))
            fi
        done
        running_total=$((running_total + running))
        sleep 1
    done
    
    log_message "INFO" "Worker status check complete: total $running_total workers running"
    return 0
}

# Signal handlers
cleanup() {
    log_message "INFO" "Received shutdown signal. Cleaning up..."
    if [[ "$DAEMON_MODE" == "true" ]]; then
        log_message "INFO" "Stopping daemon mode..."
    fi
    stop_all_workers
    log_message "INFO" "Mobile Money Workers Monitor stopped"
    exit 0
}

trap cleanup SIGTERM SIGINT SIGQUIT

usage() {
    echo "Usage: $0 [OPTIONS]"
    echo "Options:"
    echo "  -s, --single     Run single check only (override default daemon mode)"
    echo "  -v, --verbose    Enable verbose logging"
    echo "  -i, --interval N Set check interval in seconds (default: 30)"
    echo "  -h, --help       Show this help message"
    echo "  --stop           Stop all workers and exit"
    echo "  --status         Check status of all workers once and exit"
    echo ""
    echo "Default behavior: Runs continuously in daemon mode checking every 30 seconds"
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -s|--single)
            DAEMON_MODE=false
            shift
            ;;
        -v|--verbose)
            VERBOSE=true
            shift
            ;;
        -i|--interval)
            if [[ -n "$2" ]] && [[ "$2" =~ ^[0-9]+$ ]]; then
                CHECK_INTERVAL="$2"
                shift 2
            else
                echo "Error: --interval requires a numeric value"
                exit 1
            fi
            ;;
        --stop)
            stop_all_workers
            exit 0
            ;;
        --status)
            DAEMON_MODE=false
            check_and_restart_workers
            exit $?
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            usage
            exit 1
            ;;
    esac
done

log_message "INFO" "Mobile Money Workers Monitor starting..."
log_message "INFO" "Mode: $([ "$DAEMON_MODE" == "true" ] && echo "Continuous Daemon" || echo "Single Check"), Verbose: $VERBOSE"

if [[ "$DAEMON_MODE" == "true" ]]; then
    log_message "INFO" "Starting continuous monitoring with $CHECK_INTERVAL second intervals..."
    log_message "INFO" "Monitoring queues: high ($HIGH_WORKER_COUNT), medium ($MEDIUM_WORKER_COUNT), low ($LOW_WORKER_COUNT)"
    log_message "INFO" "Press Ctrl+C to stop the monitor"
    
    check_and_restart_workers
    while true; do
        sleep "$CHECK_INTERVAL"
        check_and_restart_workers
    done
else
    log_message "INFO" "Running single check..."
    check_and_restart_workers
    exit_code=$?
    log_message "INFO" "Single check completed with exit code: $exit_code"
    exit $exit_code
fi