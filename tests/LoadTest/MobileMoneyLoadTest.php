<?php

namespace Tests\LoadTest;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Utils;

class MobileMoneyLoadTest extends TestCase
{
    use WithFaker;

    private Client $httpClient;
    private string $baseUrl;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->httpClient = new Client([
            'timeout' => 5,
            'connect_timeout' => 2,
        ]);
        
        $this->baseUrl = config('app.url');
    }

    /**
     * Test 300 TPS load for 60 seconds
     */
    public function test_300_tps_sustained_load()
    {
        $this->markTestSkipped('Load test - run manually only');
        
        $targetTps = 300;
        $durationSeconds = 60;
        $totalRequests = $targetTps * $durationSeconds;
        
        $this->info("Starting 300 TPS load test for {$durationSeconds} seconds");
        $this->info("Total requests: {$totalRequests}");
        
        $startTime = microtime(true);
        $successCount = 0;
        $errorCount = 0;
        $responseTimes = [];
        
        // Clear queues before test
        Redis::flushdb();
        
        // Generate test data
        $testData = $this->generateTestData($totalRequests);
        
        // Execute load test in batches
        $batchSize = 50; // Concurrent requests per batch
        $batches = array_chunk($testData, $batchSize);
        
        foreach ($batches as $batchIndex => $batch) {
            $batchStartTime = microtime(true);
            
            // Create promises for concurrent requests
            $promises = [];
            foreach ($batch as $data) {
                $promises[] = $this->httpClient->postAsync(
                    $this->baseUrl . '/api/mobile-money/notification',
                    [
                        'json' => $data,
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Accept' => 'application/json',
                        ]
                    ]
                );
            }
            
            // Wait for all requests in batch to complete
            $responses = Utils::settle($promises)->wait();
            
            // Process results
            foreach ($responses as $response) {
                if ($response['state'] === 'fulfilled') {
                    $successCount++;
                    $responseTimes[] = $response['value']->getHeaderLine('X-Response-Time') ?: 0;
                } else {
                    $errorCount++;
                    $this->info("Error: " . $response['reason']->getMessage());
                }
            }
            
            $batchEndTime = microtime(true);
            $batchDuration = $batchEndTime - $batchStartTime;
            
            // Calculate current TPS
            $currentTps = count($batch) / $batchDuration;
            $overallTps = $successCount / (microtime(true) - $startTime);
            
            $this->info(sprintf(
                "Batch %d/%d - Current TPS: %.1f, Overall TPS: %.1f, Success: %d, Errors: %d",
                $batchIndex + 1,
                count($batches),
                $currentTps,
                $overallTps,
                $successCount,
                $errorCount
            ));
            
            // Rate limiting to maintain target TPS
            $targetBatchDuration = count($batch) / $targetTps;
            if ($batchDuration < $targetBatchDuration) {
                usleep(($targetBatchDuration - $batchDuration) * 1000000);
            }
        }
        
        $endTime = microtime(true);
        $totalDuration = $endTime - $startTime;
        $actualTps = $successCount / $totalDuration;
        
        // Calculate statistics
        if (count($responseTimes) > 0) {
            $avgResponseTime = array_sum($responseTimes) / count($responseTimes);
            $maxResponseTime = max($responseTimes);
            $minResponseTime = min($responseTimes);
        } else {
            $avgResponseTime = 0;
            $maxResponseTime = 0;
            $minResponseTime = 0;
        }
        
        // Check queue status
        $queueSizes = [
            'high' => Redis::llen('queues:mobile_money_high_priority'),
            'medium' => Redis::llen('queues:mobile_money_medium_priority'),
            'low' => Redis::llen('queues:mobile_money_low_priority'),
        ];
        
        // Results
        $this->info("\n=== LOAD TEST RESULTS ===");
        $this->info("Duration: {$totalDuration}s");
        $this->info("Target TPS: {$targetTps}");
        $this->info("Actual TPS: " . round($actualTps, 2));
        $this->info("Success Rate: " . round(($successCount / $totalRequests) * 100, 2) . "%");
        $this->info("Total Requests: {$totalRequests}");
        $this->info("Successful: {$successCount}");
        $this->info("Failed: {$errorCount}");
        $this->info("Avg Response Time: " . round($avgResponseTime, 2) . "ms");
        $this->info("Min Response Time: " . round($minResponseTime, 2) . "ms");
        $this->info("Max Response Time: " . round($maxResponseTime, 2) . "ms");
        $this->info("Queue Sizes - High: {$queueSizes['high']}, Medium: {$queueSizes['medium']}, Low: {$queueSizes['low']}");
        
        // Assertions
        $this->assertGreaterThanOrEqual(295, $actualTps, "TPS should be at least 295");
        $this->assertGreaterThanOrEqual(99, ($successCount / $totalRequests) * 100, "Success rate should be at least 99%");
        $this->assertLessThanOrEqual(100, $avgResponseTime, "Average response time should be under 100ms");
    }

    /**
     * Test burst load handling
     */
    public function test_burst_load_handling()
    {
        $this->markTestSkipped('Load test - run manually only');
        
        $burstTps = 500;
        $burstDuration = 10; // seconds
        $totalRequests = $burstTps * $burstDuration;
        
        $this->info("Starting burst load test: {$burstTps} TPS for {$burstDuration} seconds");
        
        $testData = $this->generateTestData($totalRequests);
        $startTime = microtime(true);
        
        // Send all requests as fast as possible
        $promises = [];
        foreach ($testData as $data) {
            $promises[] = $this->httpClient->postAsync(
                $this->baseUrl . '/api/mobile-money/notification',
                ['json' => $data]
            );
        }
        
        $responses = Utils::settle($promises)->wait();
        $endTime = microtime(true);
        
        $successCount = 0;
        foreach ($responses as $response) {
            if ($response['state'] === 'fulfilled') {
                $successCount++;
            }
        }
        
        $actualTps = $successCount / ($endTime - $startTime);
        $successRate = ($successCount / $totalRequests) * 100;
        
        $this->info("Burst Test Results:");
        $this->info("Actual TPS: " . round($actualTps, 2));
        $this->info("Success Rate: " . round($successRate, 2) . "%");
        
        // Should handle burst gracefully without major failures
        $this->assertGreaterThanOrEqual(90, $successRate, "Should handle burst with at least 90% success rate");
    }

    /**
     * Generate realistic test data
     */
    private function generateTestData(int $count): array
    {
        $data = [];
        
        for ($i = 0; $i < $count; $i++) {
            $data[] = [
                'SourceCustomerMobile' => '255656692469',
                'BrandName' => $this->faker->randomElement(['MPESA', 'AIRTEL_MONEY', 'TKASH']),
                'OriginalAmount' => $this->faker->randomFloat(2, 10, 10000),
                'SalesOrderNumber' => 'REF' . time() . $i . $this->faker->numerify('####'),
                'SalesOrderStatus' => $this->faker->randomElement(['SUCCESS', 'PENDING', 'FAILED']),
                'TransactionDate' => $this->faker->randomElement([
                    now()->format('Y-m-d H:i:s'), // ISO format
                    (string) (time() * 1000), // Timestamp in milliseconds
                    now()->format('d/m/Y H:i:s'), // Alternative format
                ]),
            ];
        }
        
        return $data;
    }

    /**
     * Monitor queue processing during load test
     */
    public function test_queue_processing_performance()
    {
        $this->markTestSkipped('Load test - run manually only');
        
        // Add test jobs to queue
        $jobCount = 1000;
        
        for ($i = 0; $i < $jobCount; $i++) {
            \App\Jobs\ProcessMobileMoneyNotification::dispatch(
                [
                    'SourceCustomerMobile' => '255656692469',
                    'BrandName' => 'MPESA',
                    'OriginalAmount' => 100.00,
                    'SalesOrderNumber' => 'TEST' . $i,
                    'SalesOrderStatus' => 'SUCCESS',
                    'TransactionDate' => now()->format('Y-m-d H:i:s'),
                ],
                '127.0.0.1'
            );
        }
        
        $startTime = time();
        $initialCount = DB::table('mobile_money_transaction_notifications')->count();
        
        // Wait for processing
        $timeout = 60; // seconds
        while (time() - $startTime < $timeout) {
            $currentCount = DB::table('mobile_money_transaction_notifications')->count();
            $processed = $currentCount - $initialCount;
            
            if ($processed >= $jobCount) {
                break;
            }
            
            sleep(1);
        }
        
        $endTime = time();
        $finalCount = DB::table('mobile_money_transaction_notifications')->count();
        $totalProcessed = $finalCount - $initialCount;
        $processingTime = $endTime - $startTime;
        $processingTps = $totalProcessed / $processingTime;
        
        $this->info("Queue Processing Results:");
        $this->info("Jobs Added: {$jobCount}");
        $this->info("Jobs Processed: {$totalProcessed}");
        $this->info("Processing Time: {$processingTime}s");
        $this->info("Processing TPS: " . round($processingTps, 2));
        
        $this->assertGreaterThanOrEqual($jobCount * 0.95, $totalProcessed, "Should process at least 95% of jobs");
        $this->assertGreaterThanOrEqual(50, $processingTps, "Should process at least 50 jobs per second");
    }

    private function info(string $message): void
    {
        echo $message . "\n";
    }
}