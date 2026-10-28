# Mobile Money Notification Stress Test Runner (PowerShell)
# This script provides an easy way to run the stress test on Windows

param(
    [string]$Url = "http://localhost:8000",
    [int]$Tps = 200,
    [double]$Duration = 2,
    [string]$Output = "",
    [switch]$NoSave,
    [switch]$Help,
    [switch]$Auto
)

# Display help
if ($Help) {
    Write-Host "🚀 Mobile Money Notification Stress Test Runner" -ForegroundColor Green
    Write-Host "=" * 50
    Write-Host "Usage: .\run_notification_stress_test.ps1 [parameters]"
    Write-Host ""
    Write-Host "Parameters:"
    Write-Host "  -Url <URL>              Base URL for the application (default: http://localhost:8000)"
    Write-Host "  -Tps <NUMBER>           Target transactions per second (default: 200)"
    Write-Host "  -Duration <MINUTES>     Test duration in minutes (default: 2)"
    Write-Host "  -Output <FILENAME>      Save results to specific file"
    Write-Host "  -NoSave                 Don't save results to file"
    Write-Host "  -Auto                   Run without confirmation"
    Write-Host "  -Help                   Show this help message"
    Write-Host ""
    Write-Host "Examples:"
    Write-Host "  .\run_notification_stress_test.ps1"
    Write-Host "  .\run_notification_stress_test.ps1 -Tps 150 -Duration 3"
    Write-Host "  .\run_notification_stress_test.ps1 -Url https://api.example.com -Tps 300"
    Write-Host "  .\run_notification_stress_test.ps1 -Output my_test_results.json -Auto"
    Write-Host ""
    Write-Host "Note: Make sure your application server is running before starting the test."
    exit 0
}

# Validate parameters
if ($Tps -le 0 -or $Tps -gt 1000) {
    Write-Host "❌ Error: TPS must be between 1 and 1000" -ForegroundColor Red
    exit 1
}

if ($Duration -le 0 -or $Duration -gt 60) {
    Write-Host "❌ Error: Duration must be between 0.1 and 60 minutes" -ForegroundColor Red
    exit 1
}

# Check if PHP is available
try {
    $phpVersion = php -v 2>$null
    if (-not $phpVersion) {
        throw "PHP not found"
    }
} catch {
    Write-Host "❌ Error: PHP is not installed or not in PATH" -ForegroundColor Red
    Write-Host "Please install PHP and add it to your PATH environment variable."
    exit 1
}

# Build PHP command arguments
$phpArgs = @()

if ($Url -ne "http://localhost:8000") {
    $phpArgs += "--url=$Url"
}

if ($Tps -ne 200) {
    $phpArgs += "--tps=$Tps"
}

if ($Duration -ne 2) {
    $phpArgs += "--duration=$Duration"
}

if ($Output) {
    $phpArgs += "--output=$Output"
}

if ($NoSave) {
    $phpArgs += "--no-save"
}

if ($Auto) {
    $phpArgs += "--auto"
}

# Display configuration
Write-Host ""
Write-Host "🚀 Mobile Money Notification Stress Test" -ForegroundColor Green
Write-Host "=" * 50
Write-Host "Configuration:"
Write-Host "  📍 Target URL: $Url/api/mobile-money/notification"
Write-Host "  ⚡ Target TPS: $Tps"
Write-Host "  ⏱️  Duration: $Duration minutes"
Write-Host "  💾 Save Results: $(-not $NoSave)"
if ($Output) {
    Write-Host "  📄 Results File: $Output"
}
Write-Host ""

# Check if server is reachable
Write-Host "🔍 Checking server connectivity..." -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri $Url -TimeoutSec 5 -UseBasicParsing -ErrorAction Stop
    Write-Host "✅ Server is reachable" -ForegroundColor Green
} catch {
    Write-Host "⚠️  Warning: Server may not be reachable. Test may fail." -ForegroundColor Yellow
    Write-Host "Error: $($_.Exception.Message)"
}
Write-Host ""

# Confirm before starting (unless auto mode)
if (-not $Auto) {
    Write-Host "⚠️  This test will generate high load on your server." -ForegroundColor Yellow
    Write-Host "Make sure your application is ready and monitoring is in place."
    Write-Host ""
    $confirmation = Read-Host "Do you want to continue? (y/N)"
    if ($confirmation.ToLower() -ne 'y') {
        Write-Host "❌ Test cancelled by user." -ForegroundColor Red
        exit 0
    }
}

# Run the PHP stress test
Write-Host "🚀 Starting stress test..." -ForegroundColor Green
Write-Host ""

try {
    if ($phpArgs.Count -gt 0) {
        $phpArgsString = $phpArgs -join " "
        $command = "php run_notification_stress_test.php $phpArgsString"
    } else {
        $command = "php run_notification_stress_test.php"
    }
    
    Write-Host "Executing: $command" -ForegroundColor Cyan
    Write-Host ""
    
    Invoke-Expression $command
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host ""
        Write-Host "✅ Stress test completed successfully!" -ForegroundColor Green
    } else {
        Write-Host ""
        Write-Host "❌ Stress test failed with exit code: $LASTEXITCODE" -ForegroundColor Red
        exit $LASTEXITCODE
    }
} catch {
    Write-Host ""
    Write-Host "❌ Error running stress test: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "📊 Test Summary:" -ForegroundColor Cyan
Write-Host "  Target TPS: $Tps"
Write-Host "  Duration: $Duration minutes"
Write-Host "  Total Expected Requests: $($Tps * $Duration * 60)"
Write-Host ""
Write-Host "Check the detailed results above for performance analysis and recommendations."