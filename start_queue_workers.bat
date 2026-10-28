@echo off
echo === Mobile Money Queue Workers Starter ===
echo.
echo This will start 3 queue workers in separate windows:
echo 1. High Priority Queue (mobile_money_high_priority)
echo 2. Medium Priority Queue (mobile_money_medium_priority) 
echo 3. Low Priority Queue (mobile_money_low_priority)
echo.
echo Press any key to start the workers...
pause

echo Starting High Priority Queue Worker...
start "High Priority Queue" cmd /k "php artisan queue:work redis_mobile_money --queue=mobile_money_high_priority --tries=3 --timeout=60"

timeout /t 2 /nobreak >nul

echo Starting Medium Priority Queue Worker...
start "Medium Priority Queue" cmd /k "php artisan queue:work redis_mobile_money_medium --queue=mobile_money_medium_priority --tries=3 --timeout=120"

timeout /t 2 /nobreak >nul

echo Starting Low Priority Queue Worker...
start "Low Priority Queue" cmd /k "php artisan queue:work redis_mobile_money_low --queue=mobile_money_low_priority --tries=3 --timeout=180"

echo.
echo ✅ All queue workers started in separate windows!
echo.
echo To stop workers: Close the individual windows or press Ctrl+C in each
echo To monitor queues: php artisan queue:monitor
echo.
pause