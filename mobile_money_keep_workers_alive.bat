@echo off
REM Mobile Money Queue Workers Auto-Restart Script for Windows
REM This batch file runs the PowerShell monitoring script

setlocal

REM Get the directory where this batch file is located
set "SCRIPT_DIR=%~dp0"

REM Default parameters
set "CHECK_INTERVAL=30"
set "DAEMON_MODE="
set "VERBOSE="

REM Parse command line arguments
:parse_args
if "%~1"=="" goto :run_script
if /i "%~1"=="-i" (
    set "CHECK_INTERVAL=%~2"
    shift
    shift
    goto :parse_args
)
if /i "%~1"=="--interval" (
    set "CHECK_INTERVAL=%~2"
    shift
    shift
    goto :parse_args
)
if /i "%~1"=="-d" (
    set "DAEMON_MODE=-Daemon"
    shift
    goto :parse_args
)
if /i "%~1"=="--daemon" (
    set "DAEMON_MODE=-Daemon"
    shift
    goto :parse_args
)
if /i "%~1"=="-v" (
    set "VERBOSE=-Verbose"
    shift
    goto :parse_args
)
if /i "%~1"=="--verbose" (
    set "VERBOSE=-Verbose"
    shift
    goto :parse_args
)
if /i "%~1"=="-h" (
    goto :show_help
)
if /i "%~1"=="--help" (
    goto :show_help
)
echo Unknown option: %~1
goto :show_help

:show_help
echo Usage: %~nx0 [OPTIONS]
echo Options:
echo   -i, --interval SECONDS  Check interval in seconds (default: 30)
echo   -d, --daemon           Run in daemon mode (continuous monitoring)
echo   -v, --verbose          Verbose output
echo   -h, --help             Show this help message
echo.
echo Examples:
echo   %~nx0                    # Single check
echo   %~nx0 -d                # Run in daemon mode
echo   %~nx0 -d -v -i 60       # Daemon mode with verbose output, check every 60 seconds
exit /b 0

:run_script
REM Change to script directory
cd /d "%SCRIPT_DIR%"

REM Build PowerShell command
set "PS_CMD=powershell.exe -ExecutionPolicy Bypass -File "%SCRIPT_DIR%mobile_money_keep_workers_alive.ps1" -CheckInterval %CHECK_INTERVAL% %DAEMON_MODE% %VERBOSE%"

echo Starting Mobile Money Worker Monitor...
echo Command: %PS_CMD%
echo.

REM Run the PowerShell script
%PS_CMD%

endlocal