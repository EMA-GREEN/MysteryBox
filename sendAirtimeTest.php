<?php

/**
 * Airtime Disbursement Test Script with Database Integration
 * 
 * This script tests airtime disbursements using the PayScoop API
 * and includes database record creation and updates.
 */

// Bootstrap Laravel
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

// Bootstrap Console Kernel to initialize facades/config
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Console\Commands\AirtimeDisbursementConsumer;
use Illuminate\Support\Facades\DB;

class AirtimeTestWithDatabase
{
    private $consumer;

    public function __construct()
    {
        $this->consumer = new AirtimeDisbursementConsumer();
    }

    /**
     * Create airtime disbursement record in database
     */
    private function createAirtimeDisbursementRecord($msisdn, $amount, $referenceId, $winnerId)
    {
        try {
            $disbursementId = DB::table('winner_airtime_disbursements')->insertGetId([
                'msisdn' => $msisdn,
                'amount' => $amount,
                'reference_id' => $referenceId,
                'box_type' => 'Bronze',
                'box_id' => '260',
                'winner_id' => $winnerId,
                'request_sent_time' => now(),
                'ack_status' => 'pending',
                'ack_message' => null,
                'ack_received_time' => null,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            echo "✅ Database record created - Disbursement ID: {$disbursementId}\n";
            return $disbursementId;
            
        } catch (Exception $e) {
            throw new Exception("Failed to create airtime disbursement record: " . $e->getMessage());
        }
    }

    /**
     * Update airtime disbursement record with response
     */
    private function updateAirtimeDisbursementRecord($referenceId, $statusCode, $responseBody)
    {
        try {
            $ackStatus = ($statusCode === 200) ? 'success' : 'failed';
            $ackMessage = $responseBody;
            
            $updated = DB::table('winner_airtime_disbursements')
                ->where('reference_id', $referenceId)
                ->update([
                    'ack_status' => $ackStatus,
                    'ack_message' => $ackMessage,
                    'ack_received_time' => now(),
                    'updated_at' => now()
                ]);
            
            if ($updated) {
                echo "✅ Database record updated - Status: {$ackStatus}\n";
            } else {
                echo "⚠️ No database record found to update for reference: {$referenceId}\n";
            }
            
        } catch (Exception $e) {
            throw new Exception("Failed to update airtime disbursement record: " . $e->getMessage());
        }
    }

    /**
     * Test airtime disbursement with database integration
     */
    public function testAirtimeWithDatabase()
    {
        try {
            // Test parameters
            $msisdn = '255656692469';
            $amount = 500;
            $winnerId = 244;
            $reference = 'TEST' . date('YmdHis');
            
            echo "=== Airtime Disbursement Test with Database ===\n";
            echo "Phone: {$msisdn}\n";
            echo "Amount: TZS {$amount}\n";
            echo "Reference: {$reference}\n";
            echo "Winner ID: {$winnerId}\n\n";

            // Step 1: Create database record
            echo "Step 1: Creating database record...\n";
            $disbursementId = $this->createAirtimeDisbursementRecord($msisdn, $amount, $reference, $winnerId);

            // Step 2: Prepare request data
            echo "\nStep 2: Preparing API request...\n";
            $requestData = [
                'disbursements' => [
                    [
                        'amount' => $amount,
                        'currency' => config('payscoop.currency', 'TZS'),
                        'msisdn' => $msisdn,
                        'reference' => $reference,
                    ],
                ],
            ];

            // Step 3: Send airtime request using reflection
            echo "Step 3: Sending airtime request to PayScoop API...\n";
            $refClass = new ReflectionClass($this->consumer);
            $method = $refClass->getMethod('sendAirtimeRequest');
            $method->setAccessible(true);

            /** @var \Illuminate\Http\Client\Response $response */
            $response = $method->invoke($this->consumer, $requestData);

            // Step 4: Process response
            $status = $response->status();
            $body = $response->body();

            echo "\nStep 4: Processing API response...\n";
            echo "HTTP Status: {$status}\n";
            echo "Response Body: {$body}\n";

            // Step 5: Update database record
            echo "\nStep 5: Updating database record...\n";
            $this->updateAirtimeDisbursementRecord($reference, $status, $body);

            // Final summary
            echo "\n=== TEST SUMMARY ===\n";
            if ($status === 200) {
                echo "✅ Airtime disbursement test SUCCESSFUL\n";
            } else {
                echo "❌ Airtime disbursement test FAILED\n";
            }
            echo "Reference ID: {$reference}\n";
            echo "Disbursement ID: {$disbursementId}\n";
            echo "HTTP Status: {$status}\n";
            echo "Check winner_airtime_disbursements table for full details.\n";

        } catch (Throwable $e) {
            echo "❌ Error during airtime test: " . $e->getMessage() . "\n";
            if (method_exists($e, 'getTraceAsString')) {
                echo $e->getTraceAsString() . "\n";
            }
            exit(1);
        }
    }
}

// Run the test
try {
    $test = new AirtimeTestWithDatabase();
    $test->testAirtimeWithDatabase();
} catch (Throwable $e) {
    fwrite(STDERR, 'Error initializing airtime test: ' . $e->getMessage() . "\n");
    if (method_exists($e, 'getTraceAsString')) {
        fwrite(STDERR, $e->getTraceAsString() . "\n");
    }
    exit(1);
}


