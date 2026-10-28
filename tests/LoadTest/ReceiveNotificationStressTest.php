<?php

namespace Tests\LoadTest;

use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;

class ReceiveNotificationStressTest
{
    private $client;
    private $baseUrl;
    private $targetTps;
    private $durationMinutes;
    private $metrics;
    private $startTime;
    private $endTime;
    private $totalRequests;
    private $successfulRequests;
    private $failedRequests;
    private $responseTimes;
    private $errorTypes;
    private $statusCodes;
    private $concurrentRequests;

    public function __construct($baseUrl = 'http://localhost:8000', $targetTps = 200, $durationMinutes = 2)
    {
        $this->client = new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
            'http_errors' => false, // Don't throw exceptions on HTTP errors
        ]);
        
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->targetTps = $targetTps;
        $this->durationMinutes = $durationMinutes;
        $this->concurrentRequests = min($targetTps, 50); // Limit concurrent connections
        
        $this->initializeMetrics();
    }

    private function initializeMetrics()
    {
        $this->metrics = [];
        $this->totalRequests = 0;
        $this->successfulRequests = 0;
        $this->failedRequests = 0;
        $this->responseTimes = [];
        $this->errorTypes = [];
        $this->statusCodes = [];
    }

    /**
     * Generate realistic mobile money notification data
     */
    private function generateTestData()
    {
        $salesOrderNumber = 'SO' . time() . rand(1000, 9999);
        $customerMobile = '256' . rand(700000000, 799999999);
        
        return [
            'SourceCustomerMobile' => $customerMobile,
            'BrandName' => 'TestBrand',
            'OriginalAmount' => rand(1000, 50000),
            'SalesOrderNumber' => $salesOrderNumber,
            'SalesOrderStatus' => 'SUCCESSFUL',
            'TransactionDate' => date('Y-m-d H:i:s'),
            'TransactionId' => 'TXN' . time() . rand(100, 999),
            'Currency' => 'UGX',
            'PaymentMethod' => 'MOBILE_MONEY',
            'CustomerName' => 'Test Customer ' . rand(1, 1000),
            'Description' => 'Load test transaction',
            'MerchantReference' => 'MERCHANT' . rand(10000, 99999),
            'ProcessingCode' => '000000',
            'ResponseCode' => '00',
            'ResponseDescription' => 'Successful',
        ];
    }

    /**
     * Create a single HTTP request promise
     */
    private function createRequest()
    {
        $data = $this->generateTestData();
        $requestStartTime = microtime(true);
        
        return $this->client->postAsync($this->baseUrl . '/api/mobile-money/notification', [
            'json' => $data,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'StressTest/1.0',
                'X-Test-Request' => 'true',
            ]
        ])->then(
            function ($response) use ($requestStartTime, $data) {
                $responseTime = (microtime(true) - $requestStartTime) * 1000; // Convert to milliseconds
                $this->recordSuccess($response, $responseTime, $data);
                return $response;
            },
            function ($exception) use ($requestStartTime, $data) {
                $responseTime = (microtime(true) - $requestStartTime) * 1000;
                $this->recordFailure($exception, $responseTime, $data);
                throw $exception;
            }
        );
    }

    /**
     * Record successful request metrics
     */
    private function recordSuccess($response, $responseTime, $data)
    {
        $this->successfulRequests++;
        $this->responseTimes[] = $responseTime;
        
        $statusCode = $response->getStatusCode();
        $this->statusCodes[$statusCode] = ($this->statusCodes[$statusCode] ?? 0) + 1;
        
        // Record detailed metrics every 100 requests
        if ($this->totalRequests % 100 === 0) {
            $this->metrics[] = [
                'timestamp' => microtime(true),
                'total_requests' => $this->totalRequests,
                'successful_requests' => $this->successfulRequests,
                'failed_requests' => $this->failedRequests,
                'avg_response_time' => $this->getAverageResponseTime(),
                'current_tps' => $this->getCurrentTps(),
                'memory_usage' => memory_get_usage(true),
                'status_code' => $statusCode,
                'response_time' => $responseTime,
            ];
        }
    }

    /**
     * Record failed request metrics
     */
    private function recordFailure($exception, $responseTime, $data)
    {
        $this->failedRequests++;
        $this->responseTimes[] = $responseTime;
        
        $errorType = get_class($exception);
        $this->errorTypes[$errorType] = ($this->errorTypes[$errorType] ?? 0) + 1;
        
        if ($exception instanceof RequestException && $exception->hasResponse()) {
            $statusCode = $exception->getResponse()->getStatusCode();
            $this->statusCodes[$statusCode] = ($this->statusCodes[$statusCode] ?? 0) + 1;
        } else {
            $this->statusCodes['connection_error'] = ($this->statusCodes['connection_error'] ?? 0) + 1;
        }
    }

    /**
     * Calculate current TPS
     */
    private function getCurrentTps()
    {
        $elapsed = microtime(true) - $this->startTime;
        return $elapsed > 0 ? round($this->totalRequests / $elapsed, 2) : 0;
    }

    /**
     * Get average response time
     */
    private function getAverageResponseTime()
    {
        return count($this->responseTimes) > 0 ? 
            round(array_sum($this->responseTimes) / count($this->responseTimes), 2) : 0;
    }

    /**
     * Get response time percentiles
     */
    private function getResponseTimePercentiles()
    {
        if (empty($this->responseTimes)) {
            return ['p50' => 0, 'p90' => 0, 'p95' => 0, 'p99' => 0];
        }

        $sorted = $this->responseTimes;
        sort($sorted);
        $count = count($sorted);

        return [
            'p50' => $sorted[intval($count * 0.5)] ?? 0,
            'p90' => $sorted[intval($count * 0.9)] ?? 0,
            'p95' => $sorted[intval($count * 0.95)] ?? 0,
            'p99' => $sorted[intval($count * 0.99)] ?? 0,
        ];
    }

    /**
     * Run the stress test
     */
    public function run()
    {
        echo "🚀 Starting Stress Test for receiveNotification endpoint\n";
        echo "📊 Target: {$this->targetTps} TPS for {$this->durationMinutes} minutes\n";
        echo "🔗 Endpoint: {$this->baseUrl}/api/mobile-money/notification\n";
        echo "⚡ Concurrent connections: {$this->concurrentRequests}\n";
        echo str_repeat("=", 60) . "\n\n";

        $this->startTime = microtime(true);
        $endTime = $this->startTime + ($this->durationMinutes * 60);
        
        $promises = [];
        $lastProgressUpdate = 0;
        $requestInterval = 1.0 / $this->targetTps; // Time between requests in seconds
        $nextRequestTime = $this->startTime;

        while (microtime(true) < $endTime) {
            $currentTime = microtime(true);
            
            // Control request rate
            if ($currentTime >= $nextRequestTime) {
                // Clean up completed promises to prevent memory buildup
                $promises = array_filter($promises, function($promise) {
                    return $promise->getState() === 'pending';
                });

                // Limit concurrent requests
                if (count($promises) < $this->concurrentRequests) {
                    $promises[] = $this->createRequest();
                    $this->totalRequests++;
                    $nextRequestTime = $currentTime + $requestInterval;
                }
            }

            // Update progress every second
            if ($currentTime - $lastProgressUpdate >= 1.0) {
                $this->displayProgress($currentTime, $endTime);
                $lastProgressUpdate = $currentTime;
            }

            // Small sleep to prevent CPU spinning
            usleep(1000); // 1ms
        }

        // Wait for remaining promises to complete
        echo "\n⏳ Waiting for remaining requests to complete...\n";
        Promise\settle($promises)->wait();

        $this->endTime = microtime(true);
        $this->displayResults();
    }

    /**
     * Display real-time progress
     */
    private function displayProgress($currentTime, $endTime)
    {
        $elapsed = $currentTime - $this->startTime;
        $remaining = $endTime - $currentTime;
        $progress = ($elapsed / ($this->durationMinutes * 60)) * 100;
        
        $currentTps = $this->getCurrentTps();
        $avgResponseTime = $this->getAverageResponseTime();
        $successRate = $this->totalRequests > 0 ? 
            round(($this->successfulRequests / $this->totalRequests) * 100, 1) : 0;

        echo sprintf(
            "\r📈 Progress: %3.1f%% | Time: %3.0fs | Requests: %5d | TPS: %6.1f | Avg RT: %6.1fms | Success: %5.1f%%",
            $progress,
            $elapsed,
            $this->totalRequests,
            $currentTps,
            $avgResponseTime,
            $successRate
        );
    }

    /**
     * Display comprehensive test results
     */
    private function displayResults()
    {
        $totalDuration = $this->endTime - $this->startTime;
        $actualTps = $this->totalRequests / $totalDuration;
        $successRate = $this->totalRequests > 0 ? 
            ($this->successfulRequests / $this->totalRequests) * 100 : 0;
        $percentiles = $this->getResponseTimePercentiles();

        echo "\n\n" . str_repeat("=", 80) . "\n";
        echo "📊 STRESS TEST RESULTS - receiveNotification Endpoint\n";
        echo str_repeat("=", 80) . "\n";

        // Test Configuration
        echo "🔧 TEST CONFIGURATION:\n";
        echo sprintf("   Target TPS: %d\n", $this->targetTps);
        echo sprintf("   Duration: %.1f minutes (%.1f seconds)\n", $this->durationMinutes, $totalDuration);
        echo sprintf("   Endpoint: %s/api/mobile-money/notification\n", $this->baseUrl);
        echo sprintf("   Concurrent Connections: %d\n", $this->concurrentRequests);
        echo "\n";

        // Overall Performance
        echo "⚡ OVERALL PERFORMANCE:\n";
        echo sprintf("   Total Requests: %d\n", $this->totalRequests);
        echo sprintf("   Successful Requests: %d\n", $this->successfulRequests);
        echo sprintf("   Failed Requests: %d\n", $this->failedRequests);
        echo sprintf("   Success Rate: %.2f%%\n", $successRate);
        echo sprintf("   Actual TPS: %.2f (Target: %d)\n", $actualTps, $this->targetTps);
        echo sprintf("   TPS Achievement: %.1f%%\n", ($actualTps / $this->targetTps) * 100);
        echo "\n";

        // Response Time Analysis
        echo "⏱️  RESPONSE TIME ANALYSIS:\n";
        echo sprintf("   Average: %.2f ms\n", $this->getAverageResponseTime());
        echo sprintf("   Minimum: %.2f ms\n", min($this->responseTimes) ?: 0);
        echo sprintf("   Maximum: %.2f ms\n", max($this->responseTimes) ?: 0);
        echo sprintf("   50th Percentile (Median): %.2f ms\n", $percentiles['p50']);
        echo sprintf("   90th Percentile: %.2f ms\n", $percentiles['p90']);
        echo sprintf("   95th Percentile: %.2f ms\n", $percentiles['p95']);
        echo sprintf("   99th Percentile: %.2f ms\n", $percentiles['p99']);
        echo "\n";

        // HTTP Status Codes
        echo "📋 HTTP STATUS CODE DISTRIBUTION:\n";
        arsort($this->statusCodes);
        foreach ($this->statusCodes as $code => $count) {
            $percentage = ($count / $this->totalRequests) * 100;
            echo sprintf("   %s: %d requests (%.1f%%)\n", $code, $count, $percentage);
        }
        echo "\n";

        // Error Analysis
        if (!empty($this->errorTypes)) {
            echo "❌ ERROR ANALYSIS:\n";
            arsort($this->errorTypes);
            foreach ($this->errorTypes as $errorType => $count) {
                $percentage = ($count / $this->totalRequests) * 100;
                echo sprintf("   %s: %d occurrences (%.1f%%)\n", 
                    basename($errorType), $count, $percentage);
            }
            echo "\n";
        }

        // Performance Assessment
        echo "🎯 PERFORMANCE ASSESSMENT:\n";
        $this->assessPerformance($actualTps, $successRate, $this->getAverageResponseTime());
        echo "\n";

        // Memory Usage
        echo "💾 RESOURCE USAGE:\n";
        echo sprintf("   Peak Memory Usage: %s\n", $this->formatBytes(memory_get_peak_usage(true)));
        echo sprintf("   Current Memory Usage: %s\n", $this->formatBytes(memory_get_usage(true)));
        echo "\n";

        // Recommendations
        echo "💡 RECOMMENDATIONS:\n";
        $this->generateRecommendations($actualTps, $successRate, $this->getAverageResponseTime());
        
        echo str_repeat("=", 80) . "\n";
    }

    /**
     * Assess performance and provide feedback
     */
    private function assessPerformance($actualTps, $successRate, $avgResponseTime)
    {
        $tpsAchievement = ($actualTps / $this->targetTps) * 100;
        
        if ($tpsAchievement >= 95 && $successRate >= 99 && $avgResponseTime <= 100) {
            echo "   🟢 EXCELLENT: System performing exceptionally well!\n";
        } elseif ($tpsAchievement >= 80 && $successRate >= 95 && $avgResponseTime <= 200) {
            echo "   🟡 GOOD: System performing well with minor optimization opportunities\n";
        } elseif ($tpsAchievement >= 60 && $successRate >= 90 && $avgResponseTime <= 500) {
            echo "   🟠 FAIR: System functional but needs optimization\n";
        } else {
            echo "   🔴 POOR: System requires significant optimization\n";
        }
        
        echo sprintf("   TPS Achievement: %.1f%% of target\n", $tpsAchievement);
        echo sprintf("   Reliability Score: %.1f%%\n", $successRate);
        echo sprintf("   Performance Score: %s\n", $avgResponseTime <= 100 ? 'Excellent' : 
            ($avgResponseTime <= 200 ? 'Good' : ($avgResponseTime <= 500 ? 'Fair' : 'Poor')));
    }

    /**
     * Generate performance recommendations
     */
    private function generateRecommendations($actualTps, $successRate, $avgResponseTime)
    {
        if ($actualTps < $this->targetTps * 0.8) {
            echo "   • Consider increasing server resources or optimizing the application\n";
            echo "   • Check for database connection bottlenecks\n";
            echo "   • Verify Redis queue performance\n";
        }
        
        if ($successRate < 95) {
            echo "   • Investigate error causes and improve error handling\n";
            echo "   • Check server stability and resource limits\n";
        }
        
        if ($avgResponseTime > 200) {
            echo "   • Optimize database queries and Redis operations\n";
            echo "   • Consider implementing response caching\n";
            echo "   • Review application code for performance bottlenecks\n";
        }
        
        if (max($this->responseTimes) > 1000) {
            echo "   • Investigate timeout issues and connection pooling\n";
            echo "   • Consider implementing circuit breakers\n";
        }
        
        echo "   • Monitor system resources during peak load\n";
        echo "   • Implement proper logging and monitoring\n";
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Save detailed results to file
     */
    public function saveResults($filename = null)
    {
        if (!$filename) {
            $filename = 'stress_test_results_' . date('Y-m-d_H-i-s') . '.json';
        }

        $results = [
            'test_config' => [
                'target_tps' => $this->targetTps,
                'duration_minutes' => $this->durationMinutes,
                'endpoint' => $this->baseUrl . '/api/mobile-money/notification',
                'concurrent_connections' => $this->concurrentRequests,
            ],
            'results' => [
                'total_requests' => $this->totalRequests,
                'successful_requests' => $this->successfulRequests,
                'failed_requests' => $this->failedRequests,
                'success_rate' => $this->totalRequests > 0 ? ($this->successfulRequests / $this->totalRequests) * 100 : 0,
                'actual_tps' => ($this->endTime - $this->startTime) > 0 ? $this->totalRequests / ($this->endTime - $this->startTime) : 0,
                'duration_seconds' => $this->endTime - $this->startTime,
            ],
            'response_times' => [
                'average' => $this->getAverageResponseTime(),
                'min' => min($this->responseTimes) ?: 0,
                'max' => max($this->responseTimes) ?: 0,
                'percentiles' => $this->getResponseTimePercentiles(),
            ],
            'status_codes' => $this->statusCodes,
            'error_types' => $this->errorTypes,
            'detailed_metrics' => $this->metrics,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        file_put_contents($filename, json_encode($results, JSON_PRETTY_PRINT));
        echo "📄 Detailed results saved to: $filename\n";
    }
}