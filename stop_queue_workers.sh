#!/bin/bash

# Stop Laravel Queue Workers for Mobile Money (CentOS 7)
# Updated to support multiple workers per priority

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_DIR="$SCRIPT_DIR/storage/logs"
PID_DIR="$SCRIPT_DIR/storage/framework/queue"

mkdir -p "$LOG_DIR" "$PID_DIR"

log() {
    local level="$1"; shift
    local msg="$*"
    local ts=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$ts] [$level] $msg" | tee -a "$LOG_DIR/mobile_money_stop_workers.log"
}

stop_priority() {
    local priority="$1"
    local stopped=0
    # Handle both indexed and legacy single PID files
    for pid_file in "$PID_DIR/mobile_money_${priority}_priority_"*.pid "$PID_DIR/mobile_money_${priority}_priority.pid"; do
        [ -e "$pid_file" ] || continue
        local pid=$(cat "$pid_file" 2>/dev/null)
        if [[ -n "$pid" ]] && kill -0 "$pid" 2>/dev/null; then
            log INFO "Stopping $priority worker PID $pid (file: $(basename "$pid_file"))"
            kill -TERM "$pid" 2>/dev/null
            sleep 2
            if kill -0 "$pid" 2>/dev/null; then
                log WARN "$priority worker PID $pid still running, force killing"
                kill -KILL "$pid" 2>/dev/null
            else
                log INFO "$priority worker PID $pid stopped"
            fi
            ((stopped++))
        fi
        rm -f "$pid_file"
    done
    log INFO "$priority: stopped $stopped workers"
}

# Clear Laravel queue locks to avoid stuck jobs
clear_processing_locks() {
    if command -v php >/dev/null 2>&1; then
        log INFO "Clearing Laravel queue locks for all priorities"
        # Only pass the connection name; older Laravel versions expect a single 'connection' argument
        php artisan queue:clear redis_mobile_money || true
        php artisan queue:clear redis_mobile_money_medium || true
        php artisan queue:clear redis_mobile_money_low || true
    fi
}

log INFO "Stopping all Mobile Money Laravel queue workers"
stop_priority high
stop_priority medium
stop_priority low
clear_processing_locks

# Show final status
for priority in high medium low; do
    count=0
    for pid_file in "$PID_DIR/mobile_money_${priority}_priority_"*.pid "$PID_DIR/mobile_money_${priority}_priority.pid"; do
        [ -e "$pid_file" ] || continue
        pid=$(cat "$pid_file" 2>/dev/null)
        kill -0 "$pid" 2>/dev/null && ((count++))
    done
    log INFO "$priority: $count workers still running"
    if [[ $count -gt 0 ]]; then
        log WARN "Some $priority workers may not have been stopped; check processes manually"
    fi
done

log INFO "All stop operations completed"