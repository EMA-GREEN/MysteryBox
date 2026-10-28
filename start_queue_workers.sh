#!/bin/bash

# Start Laravel Queue Workers for Mobile Money (CentOS 7)
# Updated to support multiple workers per priority for 200 TPS target

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_DIR="$SCRIPT_DIR/storage/logs"
PID_DIR="$SCRIPT_DIR/storage/framework/queue"
APP_ENV_FILE="$SCRIPT_DIR/.env"

# Worker counts for 200 TPS
HIGH_WORKER_COUNT=6
MEDIUM_WORKER_COUNT=3
LOW_WORKER_COUNT=2

# Ensure directories exist
mkdir -p "$LOG_DIR" "$PID_DIR"

# Load environment variables if .env exists
if [ -f "$APP_ENV_FILE" ]; then
    export $(grep -v '^#' "$APP_ENV_FILE" | xargs)
fi

# Logging helper
log() {
    local level="$1"; shift
    local msg="$*"
    local ts=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$ts] [$level] $msg" | tee -a "$LOG_DIR/mobile_money_start_workers.log"
}

# Stop any existing workers for a priority
stop_workers() {
    local priority="$1"
    local pattern="$PID_DIR/mobile_money_${priority}_priority_*.pid"
    local single="$PID_DIR/mobile_money_${priority}_priority.pid"
    for pid_file in $pattern "$single"; do
        [ -e "$pid_file" ] || continue
        local pid=$(cat "$pid_file" 2>/dev/null)
        if [[ -n "$pid" ]] && kill -0 "$pid" 2>/dev/null; then
            log INFO "Stopping existing $priority worker PID $pid (file: $(basename "$pid_file"))"
            kill -TERM "$pid" 2>/dev/null
            sleep 2
            kill -0 "$pid" 2>/dev/null && kill -KILL "$pid" 2>/dev/null
        fi
        rm -f "$pid_file"
    done
}

# Start a worker for a given priority and index
start_worker() {
    local priority="$1"; local idx="$2"; local queue=""; local connection=""
    case "$priority" in
        high) queue="mobile_money_high_priority"; connection="redis_mobile_money" ;;
        medium) queue="mobile_money_medium_priority"; connection="redis_mobile_money_medium" ;;
        low) queue="mobile_money_low_priority"; connection="redis_mobile_money_low" ;;
        *) log ERROR "Unknown priority: $priority"; return 1 ;;
    esac
    local pid_file="$PID_DIR/mobile_money_${priority}_priority_${idx}.pid"
    local log_file="$LOG_DIR/mobile_money_${priority}_priority_${idx}.log"
    log INFO "Starting $priority worker #$idx (queue=$queue, connection=$connection)"
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
        log SUCCESS "$priority worker #$idx started (PID=$pid)"
        return 0
    else
        log ERROR "Failed to start $priority worker #$idx"
        rm -f "$pid_file"
        return 1
    fi
}

# Start N workers for a priority
start_workers_for_priority() {
    local priority="$1"; local count="$2"
    stop_workers "$priority"
    for ((i=1; i<=count; i++)); do
        start_worker "$priority" "$i"
        sleep 1
    done
}

# Show status
show_status() {
    for priority in high medium low; do
        local count=0
        for pid_file in "$PID_DIR/mobile_money_${priority}_priority_"*.pid; do
            [ -e "$pid_file" ] || continue
            local pid=$(cat "$pid_file" 2>/dev/null)
            kill -0 "$pid" 2>/dev/null && ((count++))
        done
        log INFO "$priority: $count workers running"
    done
}

log INFO "Starting Mobile Money Laravel queue workers for 200 TPS"
start_workers_for_priority high "$HIGH_WORKER_COUNT"
start_workers_for_priority medium "$MEDIUM_WORKER_COUNT"
start_workers_for_priority low "$LOW_WORKER_COUNT"
show_status