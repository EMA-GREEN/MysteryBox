<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/tests/LoadTest/ReceiveNotificationStressTest.php';

use Tests\LoadTest\ReceiveNotificationStressTest;

/**
 * Stress Test Runner for receiveNotification Endpoint
 * 
 * This script runs a comprehensive stress test against the mobile money
 * notification endpoint with configurable parameters.
 */

// Default configuration
$config = [
    'base_url' => 'http://localhost:8000',
    'target_tps' => 200,
    'duration_minutes' => 2,
    'save_results' => true,
    'results_file' => null,
];

// Parse command line arguments
$options = getopt('', [
    'url:',
    'tps:',
    'duration:',
    'help',
    'no-save',
    'output:',
]);

// Display help
if (isset($options['help'])) {
    displayHelp();
    exit(0);
}

// Override config with command line options
if (isset($options['url'])) {
    $config['base_url'] = $options['url'];
}

if (isset($options['tps'])) {
    $config['target_tps'] = (int)$options['tps'];
}

if (isset($options['duration'])) {
    $config['duration_minutes'] = (float)$options['duration'];
}

if (isset($options['no-save'])) {
    $config['save_results'] = false;
}

if (isset($options['output'])) {
    $config['results_file'] = $options['output'];
}

// Validate configuration
validateConfig($config);

// Display startup information
displayStartupInfo($config);

// Confirm before starting (unless in automated mode)
if (!isset($options['auto']) && !confirmStart()) {
    echo "❌ Test cancelled by user.\n";
    exit(0);
}

try {
    // Create and run the stress test
    $stressTest = new ReceiveNotificationStressTest(
        $config['base_url'],
        $config['target_tps'],
        $config['duration_minutes']
    );

    // Run the test
    $stressTest->run();

    // Save results if requested
    if ($config['save_results']) {
        $stressTest->saveResults($config['results_file']);
    }

    echo "\n✅ Stress test completed successfully!\n";

} catch (Exception $e) {
    echo "\n❌ Error running stress test: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

/**
 * Display help information
 */
function displayHelp()
{
    echo "🚀 Mobile Money Notification Stress Test Runner\n";
    echo str_repeat("=", 50) . "\n";
    echo "Usage: php run_notification_stress_test.php [options]\n\n";
    echo "Options:\n";
    echo "  --url=URL              Base URL for the application (default: http://localhost:8000)\n";
    echo "  --tps=NUMBER           Target transactions per second (default: 200)\n";
    echo "  --duration=MINUTES     Test duration in minutes (default: 2)\n";
    echo "  --output=FILENAME      Save results to specific file\n";
    echo "  --no-save              Don't save results to file\n";
    echo "  --help                 Show this help message\n\n";
    echo "Examples:\n";
    echo "  php run_notification_stress_test.php\n";
    echo "  php run_notification_stress_test.php --tps=150 --duration=3\n";
    echo "  php run_notification_stress_test.php --url=https://api.example.com --tps=300\n";
    echo "  php run_notification_stress_test.php --output=my_test_results.json\n\n";
    echo "Note: Make sure your application server is running before starting the test.\n";
}

/**
 * Validate configuration parameters
 */
function validateConfig($config)
{
    if ($config['target_tps'] <= 0 || $config['target_tps'] > 1000) {
        echo "❌ Error: TPS must be between 1 and 1000\n";
        exit(1);
    }

    if ($config['duration_minutes'] <= 0 || $config['duration_minutes'] > 60) {
        echo "❌ Error: Duration must be between 0.1 and 60 minutes\n";
        exit(1);
    }

    if (!filter_var($config['base_url'], FILTER_VALIDATE_URL)) {
        echo "❌ Error: Invalid URL format\n";
        exit(1);
    }
}

/**
 * Display startup information
 */
function displayStartupInfo($config)
{
    echo "\n🚀 Mobile Money Notification Stress Test\n";
    echo str_repeat("=", 50) . "\n";
    echo "Configuration:\n";
    echo "  📍 Target URL: " . $config['base_url'] . "/api/mobile-money/notification\n";
    echo "  ⚡ Target TPS: " . $config['target_tps'] . "\n";
    echo "  ⏱️  Duration: " . $config['duration_minutes'] . " minutes\n";
    echo "  💾 Save Results: " . ($config['save_results'] ? 'Yes' : 'No') . "\n";
    if ($config['results_file']) {
        echo "  📄 Results File: " . $config['results_file'] . "\n";
    }
    echo "\n";

    // Check if server is reachable
    echo "🔍 Checking server connectivity...\n";
    if (checkServerConnectivity($config['base_url'])) {
        echo "✅ Server is reachable\n";
    } else {
        echo "⚠️  Warning: Server may not be reachable. Test may fail.\n";
    }
    echo "\n";
}

/**
 * Check if the server is reachable
 */
function checkServerConnectivity($baseUrl)
{
    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'method' => 'GET',
            ]
        ]);
        
        $result = @file_get_contents($baseUrl, false, $context);
        return $result !== false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Confirm before starting the test
 */
function confirmStart()
{
    echo "⚠️  This test will generate high load on your server.\n";
    echo "Make sure your application is ready and monitoring is in place.\n\n";
    echo "Do you want to continue? (y/N): ";
    
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    
    return strtolower(trim($line)) === 'y';
}

/**
 * Display system requirements check
 */
function checkSystemRequirements()
{
    echo "🔧 Checking system requirements...\n";
    
    $requirements = [
        'PHP Version >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'cURL Extension' => extension_loaded('curl'),
        'JSON Extension' => extension_loaded('json'),
        'Memory Limit >= 256M' => checkMemoryLimit(),
        'GuzzleHttp Available' => class_exists('GuzzleHttp\Client'),
    ];
    
    $allPassed = true;
    foreach ($requirements as $requirement => $passed) {
        echo sprintf("  %s %s\n", $passed ? '✅' : '❌', $requirement);
        if (!$passed) {
            $allPassed = false;
        }
    }
    
    if (!$allPassed) {
        echo "\n❌ Some requirements are not met. Please fix them before running the test.\n";
        exit(1);
    }
    
    echo "✅ All requirements met!\n\n";
}

/**
 * Check if memory limit is sufficient
 */
function checkMemoryLimit()
{
    $memoryLimit = ini_get('memory_limit');
    if ($memoryLimit == -1) {
        return true; // Unlimited
    }
    
    $memoryLimitBytes = parseMemoryLimit($memoryLimit);
    return $memoryLimitBytes >= 256 * 1024 * 1024; // 256MB
}

/**
 * Parse memory limit string to bytes
 */
function parseMemoryLimit($limit)
{
    $limit = trim($limit);
    $last = strtolower($limit[strlen($limit) - 1]);
    $limit = (int) $limit;

    switch ($last) {
        case 'g':
            $limit *= 1024;
        case 'm':
            $limit *= 1024;
        case 'k':
            $limit *= 1024;
    }

    return $limit;
}

// Check system requirements before starting
checkSystemRequirements();