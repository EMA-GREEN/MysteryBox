# Mobile Money Queue Workers Auto-Restart Script for Windows
# This script monitors queue workers and automatically restarts them if they fail

param(
    [int]$CheckInterval = 30,  # Check every 30 seconds
    [switch]$Daemon,           # Run as daemon (continuous monitoring)
    [switch]$Verbose           # Verbose output
)

# Configuration
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$LogDir = Join-Path $ScriptDir "storage\logs"
$PidDir = Join-Path $ScriptDir "storage\framework\queue"
$MonitorLogFile = Join-Path $LogDir "worker_monitor.log"

# Ensure directories exist
if (!(Test-Path $LogDir)) { New-Item -ItemType Directory -Path $LogDir -Force | Out-Null }
if (!(Test-Path $PidDir)) { New-Item -ItemType Directory -Path $PidDir -Force | Out-Null }

# Worker configurations
$Workers = @(
    @{
        Name = "High Priority"
        Queue = "mobile_money_high_priority"
        PidFile = Join-Path $PidDir "mobile_money_high_priority.pid"
        LogFile = Join-Path $LogDir "mobile_money_high_priority.log"
    },
    @{
        Name = "Medium Priority"
        Queue = "mobile_money_medium_priority"
        PidFile = Join-Path $PidDir "mobile_money_medium_priority.pid"
        LogFile = Join-Path $LogDir "mobile_money_medium_priority.log"
    },
    @{
        Name = "Low Priority"
        Queue = "mobile_money_low_priority"
        PidFile = Join-Path $PidDir "mobile_money_low_priority.pid"
        LogFile = Join-Path $LogDir "mobile_money_low_priority.log"
    }
)

# Logging function
function Write-Log {
    param([string]$Message, [string]$Level = "INFO")
    $Timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $LogMessage = "[$Timestamp] [$Level] $Message"
    
    if ($Verbose -or $Level -eq "ERROR" -or $Level -eq "WARN") {
        Write-Host $LogMessage -ForegroundColor $(
            switch ($Level) {
                "ERROR" { "Red" }
                "WARN" { "Yellow" }
                "SUCCESS" { "Green" }
                default { "White" }
            }
        )
    }
    
    Add-Content -Path $MonitorLogFile -Value $LogMessage
}

# Check if Redis is running
function Test-Redis {
    try {
        $result = & redis-cli ping 2>$null
        return $result -eq "PONG"
    } catch {
        return $false
    }
}

# Check if a worker process is running
function Test-WorkerProcess {
    param($Worker)
    
    if (!(Test-Path $Worker.PidFile)) {
        return $false
    }
    
    try {
        $pid = Get-Content $Worker.PidFile -ErrorAction Stop
        $process = Get-Process -Id $pid -ErrorAction Stop
        
        # Check if it's actually a PHP queue worker process
        if ($process.ProcessName -eq "php" -and $process.CommandLine -like "*queue:work*") {
            return $true
        } else {
            Write-Log "Process $pid exists but is not a queue worker" "WARN"
            return $false
        }
    } catch {
        return $false
    }
}

# Start a worker
function Start-Worker {
    param($Worker)
    
    Write-Log "Starting $($Worker.Name) worker..." "INFO"
    
    try {
        # Build the command
        $cmd = "php"
        $args = @(
            "artisan",
            "queue:work",
            "redis",
            "--queue=$($Worker.Queue)",
            "--sleep=3",
            "--tries=3",
            "--max-time=3600",
            "--daemon"
        )
        
        # Start the process
        $process = Start-Process -FilePath $cmd -ArgumentList $args -WorkingDirectory $ScriptDir -WindowStyle Hidden -PassThru -RedirectStandardOutput $Worker.LogFile -RedirectStandardError $Worker.LogFile
        
        # Save PID
        $process.Id | Out-File -FilePath $Worker.PidFile -Encoding ASCII
        
        Write-Log "Started $($Worker.Name) worker with PID: $($process.Id)" "SUCCESS"
        return $true
    } catch {
        Write-Log "Failed to start $($Worker.Name) worker: $($_.Exception.Message)" "ERROR"
        return $false
    }
}

# Stop a worker
function Stop-Worker {
    param($Worker)
    
    if (!(Test-Path $Worker.PidFile)) {
        return $true
    }
    
    try {
        $pid = Get-Content $Worker.PidFile -ErrorAction Stop
        $process = Get-Process -Id $pid -ErrorAction Stop
        
        Write-Log "Stopping $($Worker.Name) worker (PID: $pid)..." "INFO"
        $process.Kill()
        $process.WaitForExit(10000)  # Wait up to 10 seconds
        
        Remove-Item $Worker.PidFile -Force -ErrorAction SilentlyContinue
        Write-Log "Stopped $($Worker.Name) worker" "SUCCESS"
        return $true
    } catch {
        Write-Log "Failed to stop $($Worker.Name) worker: $($_.Exception.Message)" "WARN"
        Remove-Item $Worker.PidFile -Force -ErrorAction SilentlyContinue
        return $false
    }
}

# Check and restart workers if needed
function Check-AndRestartWorkers {
    Write-Log "Checking worker status..." "INFO"
    
    # Check Redis first
    if (!(Test-Redis)) {
        Write-Log "Redis is not running! Workers cannot function without Redis." "ERROR"
        return
    }
    
    foreach ($worker in $Workers) {
        if (!(Test-WorkerProcess $worker)) {
            Write-Log "$($worker.Name) worker is not running. Attempting to restart..." "WARN"
            
            # Clean up any stale PID file
            if (Test-Path $worker.PidFile) {
                Remove-Item $worker.PidFile -Force
            }
            
            # Start the worker
            if (Start-Worker $worker) {
                Write-Log "$($worker.Name) worker restarted successfully" "SUCCESS"
            } else {
                Write-Log "Failed to restart $($worker.Name) worker" "ERROR"
            }
        } else {
            Write-Log "$($worker.Name) worker is running normally" "INFO"
        }
    }
}

# Main execution
Write-Log "=== Mobile Money Worker Monitor Started ===" "INFO"
Write-Log "Check interval: $CheckInterval seconds" "INFO"
Write-Log "Daemon mode: $Daemon" "INFO"

if ($Daemon) {
    Write-Log "Running in daemon mode. Press Ctrl+C to stop." "INFO"
    
    try {
        while ($true) {
            Check-AndRestartWorkers
            Start-Sleep -Seconds $CheckInterval
        }
    } catch [System.Management.Automation.PipelineStoppedException] {
        Write-Log "Monitor stopped by user" "INFO"
    } catch {
        Write-Log "Monitor stopped due to error: $($_.Exception.Message)" "ERROR"
    }
} else {
    # Single check
    Check-AndRestartWorkers
}

Write-Log "=== Mobile Money Worker Monitor Stopped ===" "INFO"