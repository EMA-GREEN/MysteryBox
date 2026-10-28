<?php

/**
 * Mobile Money Load Test Runner
 * 
 * This script helps you run the mobile money load tests easily.
 * Make sure your application is running before executing these tests.
 */

require_once 'vendor/autoload.php';

echo "=== Mobile Money Load Test Runner ===\n\n";

// Load environment variables
if (file_exists('.env')) {
    $lines = file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Check if application is running
$appUrl = $_ENV['APP_URL'] ?? 'http://localhost:8285';
echo "Checking if application is running at: {$appUrl}\n";

$ch = curl_init($appUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_NOBODY, true);
$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "❌ Application is not running or not accessible at {$appUrl}\n";
    echo "Please start your Laravel application first:\n";
    echo "   php artisan serve\n\n";
    exit(1);
}

echo "✅ Application is running\n\n";

// Available tests
$tests = [
    '1' => [
        'name' => '300 TPS Sustained Load Test (60 seconds)',
        'method' => 'test_300_tps_sustained_load',
        'description' => 'Tests sustained 300 TPS for 60 seconds (18,000 requests total)'
    ],
    '2' => [
        'name' => 'Burst Load Test (500 TPS for 10 seconds)',
        'method' => 'test_burst_load_handling',
        'description' => 'Tests burst handling at 500 TPS for 10 seconds'
    ],
    '3' => [
        'name' => 'Queue Processing Performance Test',
        'method' => 'test_queue_processing_performance',
        'description' => 'Tests queue processing performance with 1000 jobs'
    ],
    '4' => [
        'name' => 'Run All Tests',
        'method' => 'all',
        'description' => 'Runs all load tests sequentially'
    ]
];

echo "Available Load Tests:\n";
foreach ($tests as $key => $test) {
    echo "  {$key}. {$test['name']}\n";
    echo "     {$test['description']}\n\n";
}

echo "Select a test to run (1-4): ";
$choice = trim(fgets(STDIN));

if (!isset($tests[$choice])) {
    echo "Invalid choice. Exiting.\n";
    exit(1);
}

$selectedTest = $tests[$choice];

echo "\n=== Running: {$selectedTest['name']} ===\n";
echo "⚠️  WARNING: This will generate significant load on your system!\n";
echo "Make sure you have:\n";
echo "  - Redis running\n";
echo "  - Database accessible\n";
echo "  - Queue workers running (for queue tests)\n";
echo "  - Sufficient system resources\n\n";

echo "Continue? (y/N): ";
$confirm = trim(fgets(STDIN));

if (strtolower($confirm) !== 'y') {
    echo "Test cancelled.\n";
    exit(0);
}

echo "\n🚀 Starting load test...\n\n";

// Prepare PHPUnit command
if ($selectedTest['method'] === 'all') {
    $command = 'vendor/bin/phpunit tests/LoadTest/MobileMoneyLoadTest.php --no-coverage';
} else {
    $command = "vendor/bin/phpunit tests/LoadTest/MobileMoneyLoadTest.php --filter {$selectedTest['method']} --no-coverage";
}

// Modify the test file to enable the test temporarily
$testFile = 'tests/LoadTest/MobileMoneyLoadTest.php';
$content = file_get_contents($testFile);

if ($selectedTest['method'] === 'all') {
    // Enable all tests - use regex for more robust matching
    $content = preg_replace(
        '/(\s*)\$this->markTestSkipped\(\'Load test - run manually only\'\);/',
        '$1// $this->markTestSkipped(\'Load test - run manually only\');',
        $content
    );
} else {
    // Enable specific test - find the method and comment out the skip line
    $lines = explode("\n", $content);
    $inTargetMethod = false;
    $methodFound = false;
    
    for ($i = 0; $i < count($lines); $i++) {
        // Check if we found the target method
        if (strpos($lines[$i], "public function {$selectedTest['method']}()") !== false) {
            $inTargetMethod = true;
            $methodFound = true;
            continue;
        }
        
        // If we're in the target method and find the skip line, comment it out
        if ($inTargetMethod && preg_match('/\$this->markTestSkipped\(\'Load test - run manually only\'\);/', $lines[$i])) {
            $lines[$i] = preg_replace(
                '/(\s*)\$this->markTestSkipped\(\'Load test - run manually only\'\);/',
                '$1// $this->markTestSkipped(\'Load test - run manually only\');',
                $lines[$i]
            );
            echo "✅ Enabled test method: {$selectedTest['method']}\n";
            break;
        }
        
        // If we hit another method, we're no longer in the target method
        if ($inTargetMethod && strpos($lines[$i], 'public function ') !== false) {
            break;
        }
    }
    
    $content = implode("\n", $lines);
    
    if (!$methodFound) {
        echo "⚠️  Warning: Test method '{$selectedTest['method']}' not found in the test file.\n";
    }
}

// Backup original file
copy($testFile, $testFile . '.backup');

// Debug: Check if the modification was successful
$modificationCount = substr_count($content, '// $this->markTestSkipped(\'Load test - run manually only\');');
echo "Debug: Found {$modificationCount} commented skip lines in modified content\n";

file_put_contents($testFile, $content);

try {
    // Run the test
    echo "Executing: {$command}\n\n";
    
    $startTime = time();
    passthru($command, $exitCode);
    $endTime = time();
    
    echo "\n=== Test Completed ===\n";
    echo "Duration: " . ($endTime - $startTime) . " seconds\n";
    echo "Exit Code: {$exitCode}\n";
    
    if ($exitCode === 0) {
        echo "✅ Test completed successfully!\n";
    } else {
        echo "❌ Test failed or encountered errors.\n";
    }
    
} finally {
    // Restore original file
    rename($testFile . '.backup', $testFile);
    echo "\nTest file restored to original state.\n";
}

echo "\n=== Load Test Runner Finished ===\n";