#!/bin/bash

# Disbursement Callback Supervisor Management Script
# This script manages all disbursement callback publisher and consumer services

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_header() {
    echo -e "${BLUE}=== $1 ===${NC}"
}

# Function to check if supervisor is running
check_supervisor() {
    if ! command -v supervisorctl &> /dev/null; then
        print_error "Supervisor is not installed or not in PATH"
        exit 1
    fi
}

# Function to copy config files to supervisor directory
copy_configs() {
    print_header "Copying Supervisor Configuration Files"
    
    SUPERVISOR_DIR="/etc/supervisor/conf.d"
    
    if [ ! -d "$SUPERVISOR_DIR" ]; then
        print_error "Supervisor config directory not found: $SUPERVISOR_DIR"
        exit 1
    fi
    
    # Copy all disbursement callback config files
    cp supervisor/airtime_disbursement_callback_consumer.ini "$SUPERVISOR_DIR/"
    cp supervisor/airtime_disbursement_callback_publisher.ini "$SUPERVISOR_DIR/"
    cp supervisor/wallet_disbursement_callback_consumer.ini "$SUPERVISOR_DIR/"
    cp supervisor/wallet_disbursement_callback_publisher.ini "$SUPERVISOR_DIR/"
    
    print_status "Configuration files copied to $SUPERVISOR_DIR"
}

# Function to reload supervisor configuration
reload_config() {
    print_header "Reloading Supervisor Configuration"
    
    supervisorctl reread
    supervisorctl update
    
    print_status "Supervisor configuration reloaded"
}

# Function to start all services
start_all() {
    print_header "Starting All Disbursement Callback Services"
    
    # Start publishers
    supervisorctl start airtime_disbursement_callback_publisher:*
    supervisorctl start wallet_disbursement_callback_publisher:*
    
    # Start consumers
    supervisorctl start airtime_disbursement_callback_consumer:*
    supervisorctl start wallet_disbursement_callback_consumer:*
    
    print_status "All services started"
}

# Function to stop all services
stop_all() {
    print_header "Stopping All Disbursement Callback Services"
    
    # Stop consumers first
    supervisorctl stop airtime_disbursement_callback_consumer:*
    supervisorctl stop wallet_disbursement_callback_consumer:*
    
    # Stop publishers
    supervisorctl stop airtime_disbursement_callback_publisher:*
    supervisorctl stop wallet_disbursement_callback_publisher:*
    
    print_status "All services stopped"
}

# Function to restart all services
restart_all() {
    print_header "Restarting All Disbursement Callback Services"
    
    stop_all
    sleep 2
    start_all
    
    print_status "All services restarted"
}

# Function to show status of all services
show_status() {
    print_header "Disbursement Callback Services Status"
    
    echo ""
    echo "Publishers:"
    supervisorctl status airtime_disbursement_callback_publisher:*
    supervisorctl status wallet_disbursement_callback_publisher:*
    
    echo ""
    echo "Consumers:"
    supervisorctl status airtime_disbursement_callback_consumer:*
    supervisorctl status wallet_disbursement_callback_consumer:*
}

# Function to show logs
show_logs() {
    print_header "Recent Logs"
    
    echo ""
    echo "Airtime Disbursement Callback Publisher Log:"
    echo "--------------------------------------------"
    tail -n 20 /var/log/supervisor/airtime_disbursement_callback_publisher.log 2>/dev/null || echo "Log file not found"
    
    echo ""
    echo "Wallet Disbursement Callback Publisher Log:"
    echo "-------------------------------------------"
    tail -n 20 /var/log/supervisor/wallet_disbursement_callback_publisher.log 2>/dev/null || echo "Log file not found"
    
    echo ""
    echo "Airtime Disbursement Callback Consumer Log:"
    echo "--------------------------------------------"
    tail -n 20 /var/log/supervisor/airtime_disbursement_callback_consumer.log 2>/dev/null || echo "Log file not found"
    
    echo ""
    echo "Wallet Disbursement Callback Consumer Log:"
    echo "-------------------------------------------"
    tail -n 20 /var/log/supervisor/wallet_disbursement_callback_consumer.log 2>/dev/null || echo "Log file not found"
}

# Function to install services
install() {
    print_header "Installing Disbursement Callback Services"
    
    check_supervisor
    copy_configs
    reload_config
    start_all
    
    print_status "Installation completed successfully"
    show_status
}

# Function to uninstall services
uninstall() {
    print_header "Uninstalling Disbursement Callback Services"
    
    stop_all
    
    SUPERVISOR_DIR="/etc/supervisor/conf.d"
    
    # Remove config files
    rm -f "$SUPERVISOR_DIR/airtime_disbursement_callback_consumer.ini"
    rm -f "$SUPERVISOR_DIR/airtime_disbursement_callback_publisher.ini"
    rm -f "$SUPERVISOR_DIR/wallet_disbursement_callback_consumer.ini"
    rm -f "$SUPERVISOR_DIR/wallet_disbursement_callback_publisher.ini"
    
    reload_config
    
    print_status "Uninstallation completed successfully"
}

# Function to show help
show_help() {
    echo "Disbursement Callback Supervisor Management Script"
    echo ""
    echo "Usage: $0 [COMMAND]"
    echo ""
    echo "Commands:"
    echo "  install     - Install and start all disbursement callback services"
    echo "  uninstall   - Stop and remove all disbursement callback services"
    echo "  start       - Start all services"
    echo "  stop        - Stop all services"
    echo "  restart     - Restart all services"
    echo "  status      - Show status of all services"
    echo "  logs        - Show recent logs from all services"
    echo "  reload      - Reload supervisor configuration"
    echo "  help        - Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 install    # Install and start all services"
    echo "  $0 status     # Check status of all services"
    echo "  $0 logs       # View recent logs"
}

# Main script logic
case "$1" in
    install)
        install
        ;;
    uninstall)
        uninstall
        ;;
    start)
        check_supervisor
        start_all
        ;;
    stop)
        check_supervisor
        stop_all
        ;;
    restart)
        check_supervisor
        restart_all
        ;;
    status)
        check_supervisor
        show_status
        ;;
    logs)
        show_logs
        ;;
    reload)
        check_supervisor
        reload_config
        ;;
    help|--help|-h)
        show_help
        ;;
    *)
        print_error "Unknown command: $1"
        echo ""
        show_help
        exit 1
        ;;
esac
