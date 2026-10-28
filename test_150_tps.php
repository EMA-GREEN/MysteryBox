<?php

/**
 * 150 TPS Rate Limiting Test Script
 * 
 * This script tests the rate limiting functionality for 150 TPS
 * (Transactions Per Second) on the mobile money API endpoint.
 * 
 * Usage: php test_150_tps.php
 */

require_once 'vendor/autoload.php';

// Configuration
$baseUrl = 'http://localhost:8285'; // Update this to match your server
$endpoint = '/api/mobile-money/notification';
$rateLimitStatusEndpoint = '/api/mobile-money/rate-limit-status';
$testDuration = 600; // Test for 10 minutes
$targetTPS = 150;
$maxConcurrentRequests = 50; // Number of concurrent requests to simulate real load

// Function to generate dynamic test data
function generateTestData($requestNumber = null) {
    $timestamp = now()->format('Y-m-d H:i:s');
    $orderNumber = 'BILL-INV-' . now()->format('Ymd-His') . '-' . str_pad($requestNumber ?? rand(1, 999), 3, '0', STR_PAD_LEFT);
    
    return [
        'SourceCustomerMobile' => '255656692469',
        'BrandName' => 'water',
        'OriginalAmount' => 6000,
        'SalesOrderNumber' => $orderNumber,
        'SalesOrderStatus' => 'success',
        'TransactionDate' => $timestamp
    ];
}

// Initial test data (will be updated dynamically)
$testData = generateTestData();

echo "==========================================\n";
echo "150 TPS Rate Limiting Test for Mobile Money API\n";
echo "==========================================\n";
echo "Base URL: $baseUrl\n";
echo "Endpoint: $endpoint\n";
echo "Target TPS: $targetTPS\n";
echo "Test Duration: ${testDuration}s\n";
echo "Max Concurrent Requests: $maxConcurrentRequests\n";
echo "==========================================\n\n";

// Show sample of dynamic test data
echo "Sample Test Data:\n";
$sampleData = generateTestData(1);
echo "  SalesOrderNumber: {$sampleData['SalesOrderNumber']}\n";
echo "  TransactionDate: {$sampleData['TransactionDate']}\n";
echo "==========================================\n\n";

// Function to send HTTP request
function sendRequest($url, $data) {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: TPS-Test/1.0'
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HEADER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_NOSIGNAL => true, // Better for high-frequency requests
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    curl_close($ch);
    
    return [
        'http_code' => $httpCode,
        'response' => $response,
        'error' => $error,
        'total_time' => $totalTime
    ];
}

// Function to check rate limit status
function checkRateLimitStatus($baseUrl, $endpoint) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $baseUrl . $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if ($data && isset($data['data'])) {
            return $data['data'];
        }
    }
    
    return null;
}

// Function to send concurrent requests
function sendConcurrentRequests($url, $count) {
    $results = [];
    $mh = curl_multi_init();
    $handles = [];
    
    // Create multiple curl handles
    for ($i = 0; $i < $count; $i++) {
        // Generate unique data for each request
        $requestData = generateTestData($i + 1);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: TPS-Test/1.0'
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($requestData),
            CURLOPT_HEADER => false,
            CURLOPT_NOSIGNAL => true,
        ]);
        
        $handles[] = $ch;
        curl_multi_add_handle($mh, $ch);
    }
    
    // Execute all requests
    $active = null;
    do {
        $mrc = curl_multi_exec($mh, $active);
    } while ($mrc == CURLM_CALL_MULTI_PERFORM);
    
    while ($active && $mrc == CURLM_OK) {
        if (curl_multi_select($mh) != -1) {
            do {
                $mrc = curl_multi_exec($mh, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        }
    }
    
    // Collect results
    foreach ($handles as $ch) {
        $response = curl_multi_getcontent($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        
        $results[] = [
            'http_code' => $httpCode,
            'response' => $response,
            'total_time' => $totalTime
        ];
        
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    
    curl_multi_close($mh);
    return $results;
}

// Test 1: Check initial rate limit status
echo "1. Checking initial rate limit status...\n";
$initialStatus = checkRateLimitStatus($baseUrl, $rateLimitStatusEndpoint);
if ($initialStatus) {
    echo "   Rate Limit: {$initialStatus['rate_limit']} requests/second (TPS)\n";
    echo "   Remaining: {$initialStatus['remaining_attempts']} requests\n";
    echo "   Reset Time: {$initialStatus['reset_time']}\n";
} else {
    echo "   Failed to get rate limit status\n";
}
echo "\n";

// Test 2: Sustained TPS test
echo "2. Running sustained TPS test for ${testDuration} seconds...\n";
$startTime = microtime(true);
$totalRequests = 0;
$successCount = 0;
$rateLimitedCount = 0;
$errorCount = 0;
$responseTimes = [];

$testEndTime = $startTime + $testDuration;

while (microtime(true) < $testEndTime) {
    $batchStartTime = microtime(true);
    
    // Send a batch of concurrent requests
    $batchSize = min($maxConcurrentRequests, $targetTPS);
    $results = sendConcurrentRequests($baseUrl . $endpoint, $batchSize);
    
    $batchEndTime = microtime(true);
    $batchDuration = $batchEndTime - $batchStartTime;
    
    // Process results
    foreach ($results as $result) {
        $totalRequests++;
        
        if ($result['http_code'] === 200) {
            $successCount++;
        } elseif ($result['http_code'] === 429) {
            $rateLimitedCount++;
        } else {
            $errorCount++;
        }
        
        $responseTimes[] = $result['total_time'];
    }
    
    // Calculate current TPS
    $elapsedTime = microtime(true) - $startTime;
    $currentTPS = $totalRequests / $elapsedTime;
    
    echo "   Batch completed: " . count($results) . " requests in " . number_format($batchDuration, 3) . "s";
    echo " | Total: $totalRequests | Current TPS: " . number_format($currentTPS, 1) . "\n";
    
    // Small delay to maintain target TPS
    $targetDelay = (1.0 / $targetTPS) * $batchSize;
    if ($targetDelay > $batchDuration) {
        usleep(($targetDelay - $batchDuration) * 1000000);
    }
}

$endTime = microtime(true);
$totalDuration = $endTime - $startTime;
$actualTPS = $totalRequests / $totalDuration;

echo "\n";
echo "3. TPS Test Results Summary:\n";
echo "   Test Duration: " . number_format($totalDuration, 2) . " seconds\n";
echo "   Total Requests Sent: $totalRequests\n";
echo "   Successful Requests: $successCount\n";
echo "   Rate Limited Requests: $rateLimitedCount\n";
echo "   Error Requests: $errorCount\n";
echo "   Target TPS: $targetTPS\n";
echo "   Actual TPS: " . number_format($actualTPS, 1) . "\n";

if (count($responseTimes) > 0) {
    $avgResponseTime = array_sum($responseTimes) / count($responseTimes);
    $minResponseTime = min($responseTimes);
    $maxResponseTime = max($responseTimes);
    
    echo "   Average Response Time: " . number_format($avgResponseTime * 1000, 2) . " ms\n";
    echo "   Min Response Time: " . number_format($minResponseTime * 1000, 2) . " ms\n";
    echo "   Max Response Time: " . number_format($maxResponseTime * 1000, 2) . " ms\n";
}

// Test 3: Rate limit reset test
echo "\n4. Testing rate limit reset...\n";
echo "   Waiting for rate limit to reset...\n";
sleep(2); // Wait 2 seconds for rate limit to reset

$finalStatus = checkRateLimitStatus($baseUrl, $rateLimitStatusEndpoint);
if ($finalStatus) {
    echo "   Rate Limit: {$finalStatus['rate_limit']} requests/second (TPS)\n";
    echo "   Remaining: {$finalStatus['remaining_attempts']} requests\n";
    echo "   Reset Time: {$finalStatus['reset_time']}\n";
} else {
    echo "   Failed to get final rate limit status\n";
}

echo "\n==========================================\n";
echo "150 TPS Rate Limiting Test Completed\n";
echo "==========================================\n";

// Performance analysis
echo "\nPerformance Analysis:\n";
if ($actualTPS >= $targetTPS * 0.95) {
    echo "✓ TPS Performance: EXCELLENT - Achieved " . number_format($actualTPS, 1) . " TPS\n";
} elseif ($actualTPS >= $targetTPS * 0.8) {
    echo "⚠ TPS Performance: GOOD - Achieved " . number_format($actualTPS, 1) . " TPS\n";
} else {
    echo "✗ TPS Performance: POOR - Only achieved " . number_format($actualTPS, 1) . " TPS\n";
}

if ($rateLimitedCount > 0) {
    echo "✓ Rate Limiting: WORKING - " . $rateLimitedCount . " requests were rate limited\n";
} else {
    echo "⚠ Rate Limiting: NOT TRIGGERED - Consider increasing load or reducing rate limit\n";
}

if ($errorCount > 0) {
    echo "⚠ Errors: " . $errorCount . " requests failed - Check server logs\n";
} else {
    echo "✓ Errors: NONE - All requests processed successfully\n";
}

echo "\nRecommendations:\n";
echo "- Target TPS achieved: " . ($actualTPS >= $targetTPS * 0.95 ? "Yes" : "No") . "\n";
echo "- Rate limiting working: " . ($rateLimitedCount > 0 ? "Yes" : "No") . "\n";
echo "- Server handling load: " . ($errorCount == 0 ? "Yes" : "No") . "\n";
echo "- Consider Redis for better performance at high TPS\n";
echo "- Monitor server resources during high load\n";
