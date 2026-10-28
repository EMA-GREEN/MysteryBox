@echo off
REM Mobile Money Notification Stress Test Runner (Batch)
REM Quick launcher for the stress test

echo.
echo 🚀 Mobile Money Notification Stress Test Launcher
echo ================================================
echo.

REM Check if PHP is available
php -v >nul 2>&1
if errorlevel 1 (
    echo ❌ Error: PHP is not installed or not in PATH
    echo Please install PHP and add it to your PATH environment variable.
    pause
    exit /b 1
)

REM Default parameters
set URL=http://localhost:8000
set TPS=200
set DURATION=2

REM Check for command line arguments
if "%1"=="help" goto :help
if "%1"=="--help" goto :help
if "%1"=="-h" goto :help

REM Parse simple arguments
if not "%1"=="" set TPS=%1
if not "%2"=="" set DURATION=%2
if not "%3"=="" set URL=%3

echo Configuration:
echo   📍 Target URL: %URL%/api/mobile-money/notification
echo   ⚡ Target TPS: %TPS%
echo   ⏱️  Duration: %DURATION% minutes
echo.

echo 🔍 Checking server connectivity...
curl -s --connect-timeout 5 %URL% >nul 2>&1
if errorlevel 1 (
    echo ⚠️  Warning: Server may not be reachable. Test may fail.
) else (
    echo ✅ Server is reachable
)
echo.

echo ⚠️  This test will generate high load on your server.
echo Make sure your application is ready and monitoring is in place.
echo.
set /p confirm="Do you want to continue? (y/N): "
if /i not "%confirm%"=="y" (
    echo ❌ Test cancelled by user.
    pause
    exit /b 0
)

echo.
echo 🚀 Starting stress test...
echo.

REM Run the PHP stress test
php run_notification_stress_test.php --url=%URL% --tps=%TPS% --duration=%DURATION%

if errorlevel 1 (
    echo.
    echo ❌ Stress test failed
    pause
    exit /b 1
) else (
    echo.
    echo ✅ Stress test completed successfully!
)

echo.
echo 📊 Test Summary:
echo   Target TPS: %TPS%
echo   Duration: %DURATION% minutes
set /a expected_requests=%TPS% * %DURATION% * 60
echo   Total Expected Requests: %expected_requests%
echo.
echo Check the detailed results above for performance analysis and recommendations.
echo.
pause
exit /b 0

:help
echo.
echo 🚀 Mobile Money Notification Stress Test Launcher
echo ================================================
echo.
echo Usage: run_notification_stress_test.bat [TPS] [DURATION] [URL]
echo.
echo Parameters:
echo   TPS        Target transactions per second (default: 200)
echo   DURATION   Test duration in minutes (default: 2)
echo   URL        Base URL for the application (default: http://localhost:8000)
echo.
echo Examples:
echo   run_notification_stress_test.bat
echo   run_notification_stress_test.bat 150
echo   run_notification_stress_test.bat 150 3
echo   run_notification_stress_test.bat 300 5 https://api.example.com
echo.
echo For advanced options, use the PHP script directly:
echo   php run_notification_stress_test.php --help
echo.
echo Note: Make sure your application server is running before starting the test.
echo.
pause
exit /b 0