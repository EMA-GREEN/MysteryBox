# Mobile Money Queue Workers Monitoring Guide

This guide explains how to use the automated worker monitoring scripts to ensure your queue workers are always running.

## 📁 Available Scripts

### Windows
- `mobile_money_keep_workers_alive.ps1` - PowerShell monitoring script
- `mobile_money_keep_workers_alive.bat` - Batch file wrapper for easy execution

### Linux/Unix
- `mobile_money_keep_workers_alive.sh` - Bash monitoring script

## 🚀 Quick Start

### Windows

#### Single Check (Check once and exit)
```cmd
# Using batch file
mobile_money_keep_workers_alive.bat

# Using PowerShell directly
powershell -ExecutionPolicy Bypass -File mobile_money_keep_workers_alive.ps1
```

#### Daemon Mode (Continuous monitoring)
```cmd
# Run in background, check every 30 seconds
mobile_money_keep_workers_alive.bat -d

# Run with verbose output, check every 60 seconds
mobile_money_keep_workers_alive.bat -d -v -i 60
```

### Linux/Unix

#### Single Check
```bash
# Make executable first
chmod +x mobile_money_keep_workers_alive.sh

# Run single check
./mobile_money_keep_workers_alive.sh
```

#### Daemon Mode
```bash
# Run in background, check every 30 seconds
./mobile_money_keep_workers_alive.sh --daemon

# Run with verbose output, check every 60 seconds
./mobile_money_keep_workers_alive.sh --daemon --verbose --interval 60
```

## ⚙️ Command Line Options

| Option | Short | Description | Default |
|--------|-------|-------------|---------|
| `--interval` | `-i` | Check interval in seconds | 30 |
| `--daemon` | `-d` | Run in daemon mode (continuous) | false |
| `--verbose` | `-v` | Show detailed output | false |
| `--help` | `-h` | Show help message | - |

## 🔍 What the Scripts Monitor

The monitoring scripts check and manage these queue workers:

1. **High Priority Worker**
   - Queue: `mobile_money_high_priority`
   - PID File: `storage/framework/queue/mobile_money_high_priority.pid`
   - Log File: `storage/logs/mobile_money_high_priority.log`

2. **Medium Priority Worker**
   - Queue: `mobile_money_medium_priority`
   - PID File: `storage/framework/queue/mobile_money_medium_priority.pid`
   - Log File: `storage/logs/mobile_money_medium_priority.log`

3. **Low Priority Worker**
   - Queue: `mobile_money_low_priority`
   - PID File: `storage/framework/queue/mobile_money_low_priority.pid`
   - Log File: `storage/logs/mobile_money_low_priority.log`

## 🔧 How It Works

### Detection Logic
1. **Redis Check**: Verifies Redis is running (workers can't function without it)
2. **PID File Check**: Looks for worker PID files
3. **Process Verification**: Confirms the process is actually running
4. **Command Validation**: Ensures it's a legitimate queue worker process

### Auto-Restart Process
1. **Detection**: Identifies failed/missing workers
2. **Cleanup**: Removes stale PID files
3. **Restart**: Launches new worker process
4. **Verification**: Confirms successful restart
5. **Logging**: Records all actions

### Worker Start Command
Each worker is started with these parameters:
```bash
php artisan queue:work redis \
    --queue=<queue_name> \
    --sleep=3 \
    --tries=3 \
    --max-time=3600 \
    --daemon
```

## 📊 Logging

### Monitor Log
- **Location**: `storage/logs/worker_monitor.log`
- **Content**: All monitoring activities, restarts, errors
- **Format**: `[YYYY-MM-DD HH:MM:SS] [LEVEL] Message`

### Worker Logs
- **Location**: `storage/logs/mobile_money_<priority>_priority.log`
- **Content**: Individual worker output and errors

### Log Levels
- `INFO`: Normal operations
- `WARN`: Warnings (worker restarts, etc.)
- `ERROR`: Errors (failed restarts, Redis down)
- `SUCCESS`: Successful operations

## 🔄 Production Deployment

### Option 1: Systemd Service (Linux)

Create `/etc/systemd/system/mysterybox-worker-monitor.service`:
```ini
[Unit]
Description=MysteryBox Queue Worker Monitor
After=network.target redis.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/path/to/your/mysterybox
ExecStart=/path/to/your/mysterybox/mobile_money_keep_workers_alive.sh --daemon --interval 30
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

Enable and start:
```bash
sudo systemctl enable mysterybox-worker-monitor
sudo systemctl start mysterybox-worker-monitor
```

### Option 2: Cron Job (Linux)

Add to crontab:
```bash
# Check every minute
* * * * * /path/to/your/mysterybox/mobile_money_keep_workers_alive.sh >> /dev/null 2>&1
```

### Option 3: Windows Service

Use tools like NSSM to run the PowerShell script as a Windows service:
```cmd
nssm install MysteryBoxWorkerMonitor
nssm set MysteryBoxWorkerMonitor Application powershell.exe
nssm set MysteryBoxWorkerMonitor AppParameters "-ExecutionPolicy Bypass -File C:\path\to\mobile_money_keep_workers_alive.ps1 -Daemon"
nssm start MysteryBoxWorkerMonitor
```

### Option 4: Task Scheduler (Windows)

1. Open Task Scheduler
2. Create Basic Task
3. Set trigger (e.g., every 1 minute)
4. Set action to run: `mobile_money_keep_workers_alive.bat`

## 🚨 Troubleshooting

### Common Issues

#### Redis Not Running
```
[ERROR] Redis is not running! Workers cannot function without Redis.
```
**Solution**: Start Redis service
```bash
# Linux
sudo systemctl start redis

# Windows (if using Redis Windows port)
redis-server
```

#### Permission Issues (Linux)
```
[ERROR] Failed to start worker: Permission denied
```
**Solution**: Ensure proper permissions
```bash
chmod +x keep_workers_alive.sh
chown www-data:www-data storage/logs storage/framework/queue
```

#### PowerShell Execution Policy (Windows)
```
execution of scripts is disabled on this system
```
**Solution**: Set execution policy
```powershell
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
```

### Monitoring Commands

#### Check if monitoring is working
```bash
# View monitor log
tail -f storage/logs/worker_monitor.log

# Check worker processes
ps aux | grep "queue:work"

# Check Redis queues
redis-cli llen mobile_money_high_priority
redis-cli llen mobile_money_medium_priority
redis-cli llen mobile_money_low_priority
```

## 📈 Performance Considerations

### Check Interval
- **High Load**: 15-30 seconds (faster detection)
- **Normal Load**: 30-60 seconds (balanced)
- **Low Load**: 60-120 seconds (resource efficient)

### Resource Usage
- **CPU**: Minimal (only during checks)
- **Memory**: ~10-20MB for monitoring script
- **Disk**: Log files grow over time (consider log rotation)

## 🔐 Security Notes

1. **File Permissions**: Ensure only appropriate users can execute scripts
2. **Log Rotation**: Implement log rotation to prevent disk space issues
3. **Monitoring**: Monitor the monitor (ensure monitoring script itself is running)

## 📞 Support

If workers continue to fail after restart attempts:

1. Check Laravel logs: `storage/logs/laravel.log`
2. Check worker logs: `storage/logs/mobile_money_*_priority.log`
3. Check Redis connectivity: `redis-cli ping`
4. Check system resources: `top`, `htop`, or Task Manager
5. Check database connectivity

## 🎯 Best Practices

1. **Always run in daemon mode** for production
2. **Monitor the monitor** - ensure the monitoring script itself is running
3. **Set up alerts** for repeated worker failures
4. **Regular log review** to identify patterns
5. **Test restart procedures** during maintenance windows
6. **Keep backups** of working configurations