# Test script for worker monitoring functionality
# This script tests the monitoring capabilities without actually starting workers

param(
    [switch]$Verbose
)

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$LogDir = Join-Path $ScriptDir "storage\logs"
$PidDir = Join-Path $ScriptDir "storage\framework\queue"

# Ensure directories exist
if (!(Test-Path $LogDir)) { New-Item -ItemType Directory -Path $LogDir -Force | Out-Null }
if (!(Test-Path $PidDir)) { New-Item -ItemType Directory -Path $PidDir -Force | Out-Null }

Write-Host "=== Worker Monitor Test ===" -ForegroundColor Blue
Write-Host ""

# Test 1: Check if Redis is accessible
Write-Host "Test 1: Redis connectivity" -ForegroundColor Yellow
try {
    $result = & redis-cli ping 2>$null
    if ($result -eq "PONG") {
        Write-Host "✅ Redis is running and accessible" -ForegroundColor Green
    } else {
        Write-Host "❌ Redis ping returned: $result" -ForegroundColor Red
    }
} catch {
    Write-Host "❌ Redis is not accessible: $($_.Exception.Message)" -ForegroundColor Red
}

# Test 2: Check directory structure
Write-Host "`nTest 2: Directory structure" -ForegroundColor Yellow
$directories = @($LogDir, $PidDir)
foreach ($dir in $directories) {
    if (Test-Path $dir) {
        Write-Host "✅ Directory exists: $dir" -ForegroundColor Green
    } else {
        Write-Host "❌ Directory missing: $dir" -ForegroundColor Red
    }
}

# Test 3: Check if PHP and Artisan are available
Write-Host "`nTest 3: PHP and Laravel availability" -ForegroundColor Yellow
try {
    $phpVersion = & php --version 2>$null | Select-Object -First 1
    Write-Host "✅ PHP available: $phpVersion" -ForegroundColor Green
} catch {
    Write-Host "❌ PHP not found in PATH" -ForegroundColor Red
}

if (Test-Path "artisan") {
    Write-Host "✅ Laravel artisan file found" -ForegroundColor Green
} else {
    Write-Host "❌ Laravel artisan file not found" -ForegroundColor Red
}

# Test 4: Check current worker processes
Write-Host "`nTest 4: Current worker processes" -ForegroundColor Yellow
$workers = @("high", "medium", "low")
foreach ($priority in $workers) {
    $pidFile = Join-Path $PidDir "mobile_money_${priority}_priority.pid"
    
    if (Test-Path $pidFile) {
        try {
            $pid = Get-Content $pidFile -ErrorAction Stop
            $process = Get-Process -Id $pid -ErrorAction Stop
            
            if ($process.ProcessName -eq "php") {
                Write-Host "✅ $priority priority worker running (PID: $pid)" -ForegroundColor Green
            } else {
                Write-Host "⚠️  $priority priority PID file exists but process is not PHP (PID: $pid)" -ForegroundColor Yellow
            }
        } catch {
            Write-Host "❌ $priority priority PID file exists but process not running" -ForegroundColor Red
        }
    } else {
        Write-Host "ℹ️  $priority priority worker not running (no PID file)" -ForegroundColor Cyan
    }
}

# Test 5: Check queue status (if Redis is available)
Write-Host "`nTest 5: Queue status" -ForegroundColor Yellow
$queues = @("mobile_money_high_priority", "mobile_money_medium_priority", "mobile_money_low_priority")
foreach ($queue in $queues) {
    try {
        $size = & redis-cli llen $queue 2>$null
        if ($size -ne $null) {
            Write-Host "✅ Queue '$queue': $size jobs pending" -ForegroundColor Green
        } else {
            Write-Host "❌ Could not check queue '$queue'" -ForegroundColor Red
        }
    } catch {
        Write-Host "❌ Error checking queue '$queue': $($_.Exception.Message)" -ForegroundColor Red
    }
}

# Test 6: Simulate monitoring script execution
Write-Host "`nTest 6: Monitor script simulation" -ForegroundColor Yellow
try {
    $monitorScript = Join-Path $ScriptDir "mobile_money_keep_workers_alive.ps1"
    if (Test-Path $monitorScript) {
        Write-Host "✅ Monitor script found: $monitorScript" -ForegroundColor Green
        
        # Test script syntax
        $syntaxCheck = powershell -NoProfile -Command "& { try { . '$monitorScript' -WhatIf } catch { `$_.Exception.Message } }"
        if ($syntaxCheck) {
            Write-Host "⚠️  Script syntax check: $syntaxCheck" -ForegroundColor Yellow
        } else {
            Write-Host "✅ Monitor script syntax is valid" -ForegroundColor Green
        }
    } else {
        Write-Host "❌ Monitor script not found" -ForegroundColor Red
    }
} catch {
    Write-Host "❌ Error testing monitor script: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host "`n=== Test Complete ===" -ForegroundColor Blue
Write-Host ""
Write-Host "To run the actual monitor:" -ForegroundColor Cyan
Write-Host "  Single check:    .\mobile_money_keep_workers_alive.ps1 -Verbose" -ForegroundColor White
Write-Host "  Daemon mode:     .\mobile_money_keep_workers_alive.ps1 -Daemon -Verbose" -ForegroundColor White
Write-Host "  Using batch:     .\mobile_money_keep_workers_alive.bat -d -v" -ForegroundColor White