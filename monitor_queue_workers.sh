#!/bin/bash

# Mobile Money Queue Workers Monitor for CentOS 7
# This script monitors the status of queue workers and provides real-time information

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PID_DIR="$SCRIPT_DIR/storage/framework/queue"
LOG_DIR="$SCRIPT_DIR/storage/logs"

# Function to check worker status
check_worker_status() {
    local priority=$1
    local pid_file="$PID_DIR/mobile_money_${priority}_priority.pid"
    
    if [ -f "$pid_file" ]; then
        local pid=$(cat "$pid_file")
        if kill -0 "$pid" 2>/dev/null; then
            # Get process info
            local cpu_mem=$(ps -p "$pid" -o %cpu,%mem --no-headers 2>/dev/null || echo "N/A N/A")
            local start_time=$(ps -p "$pid" -o lstart --no-headers 2>/dev/null || echo "N/A")
            echo -e "${GREEN}✅ ${priority} priority worker: Running${NC}"
            echo "   PID: $pid"
            echo "   CPU/Memory: $cpu_mem"
            echo "   Started: $start_time"
        else
            echo -e "${RED}❌ ${priority} priority worker: Dead (PID file exists but process not running)${NC}"
            return 1
        fi
    else
        echo -e "${RED}❌ ${priority} priority worker: Not started${NC}"
        return 1
    fi
}

# Function to show queue information
show_queue_info() {
    echo -e "${BLUE}=== Queue Information ===${NC}"
    
    for queue in mobile_money_high_priority mobile_money_medium_priority mobile_money_low_priority; do
        if redis-cli ping > /dev/null 2>&1; then
            local size=$(redis-cli llen "$queue" 2>/dev/null || echo "N/A")
            local processing=$(redis-cli llen "${queue}:processing" 2>/dev/null || echo "0")
            local failed=$(redis-cli llen "${queue}:failed" 2>/dev/null || echo "0")
            
            echo -e "${CYAN}Queue: $queue${NC}"
            echo "  Pending: $size jobs"
            echo "  Processing: $processing jobs"
            echo "  Failed: $failed jobs"
        else
            echo -e "${RED}❌ Cannot connect to Redis${NC}"
            break
        fi
    done
}

# Function to show recent log entries
show_recent_logs() {
    echo ""
    echo -e "${BLUE}=== Recent Log Entries (last 10 lines each) ===${NC}"
    
    for priority in high medium low; do
        local log_file="$LOG_DIR/queue_${priority}_priority.log"
        if [ -f "$log_file" ]; then
            echo -e "${CYAN}--- ${priority} priority worker logs ---${NC}"
            tail -n 5 "$log_file" | while read line; do
                echo "  $line"
            done
        else
            echo -e "${YELLOW}No log file for ${priority} priority worker${NC}"
        fi
    done
}

# Function to show system resources
show_system_resources() {
    echo ""
    echo -e "${BLUE}=== System Resources ===${NC}"
    
    # Memory usage
    local mem_info=$(free -h | grep "Mem:")
    echo "Memory: $mem_info"
    
    # Load average
    local load_avg=$(uptime | awk -F'load average:' '{print $2}')
    echo "Load Average:$load_avg"
    
    # Disk space for logs
    local disk_usage=$(df -h "$LOG_DIR" | tail -1)
    echo "Log Directory Disk Usage: $disk_usage"
}

# Function for continuous monitoring
continuous_monitor() {
    echo -e "${YELLOW}Starting continuous monitoring (Press Ctrl+C to stop)...${NC}"
    echo ""
    
    while true; do
        clear
        echo -e "${BLUE}=== Mobile Money Queue Workers Monitor ===${NC}"
        echo "Last updated: $(date)"
        echo ""
        
        # Worker status
        echo -e "${BLUE}=== Worker Status ===${NC}"
        for priority in high medium low; do
            check_worker_status "$priority"
            echo ""
        done
        
        # Queue information
        show_queue_info
        
        # System resources
        show_system_resources
        
        echo ""
        echo -e "${YELLOW}Refreshing in 5 seconds... (Press Ctrl+C to stop)${NC}"
        sleep 5
    done
}

# Function to show help
show_help() {
    echo "Mobile Money Queue Workers Monitor"
    echo ""
    echo "Usage: $0 [OPTION]"
    echo ""
    echo "Options:"
    echo "  status    - Show current worker status and queue information (default)"
    echo "  logs      - Show recent log entries"
    echo "  watch     - Continuous monitoring (refreshes every 5 seconds)"
    echo "  tail      - Follow log files in real-time"
    echo "  help      - Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0                    # Show status once"
    echo "  $0 watch             # Continuous monitoring"
    echo "  $0 logs              # Show recent logs"
    echo "  $0 tail              # Follow logs in real-time"
}

# Function to tail logs
tail_logs() {
    echo -e "${YELLOW}Following log files (Press Ctrl+C to stop)...${NC}"
    echo ""
    
    # Check if log files exist
    local log_files=()
    for priority in high medium low; do
        local log_file="$LOG_DIR/queue_${priority}_priority.log"
        if [ -f "$log_file" ]; then
            log_files+=("$log_file")
        fi
    done
    
    if [ ${#log_files[@]} -eq 0 ]; then
        echo -e "${RED}No log files found${NC}"
        exit 1
    fi
    
    # Use multitail if available, otherwise use tail
    if command -v multitail > /dev/null 2>&1; then
        multitail "${log_files[@]}"
    else
        tail -f "${log_files[@]}"
    fi
}

# Main execution
case "${1:-status}" in
    status)
        echo -e "${BLUE}=== Mobile Money Queue Workers Status ===${NC}"
        echo ""
        
        # Worker status
        for priority in high medium low; do
            check_worker_status "$priority"
            echo ""
        done
        
        # Queue information
        show_queue_info
        
        # System resources
        show_system_resources
        ;;
    logs)
        show_recent_logs
        ;;
    watch)
        continuous_monitor
        ;;
    tail)
        tail_logs
        ;;
    help|--help|-h)
        show_help
        ;;
    *)
        echo "Unknown option: $1"
        echo ""
        show_help
        exit 1
        ;;
esac