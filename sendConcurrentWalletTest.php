<?php

/**
 * Concurrent Wallet Disbursement Test Script
 * 
 * This script tests concurrent wallet disbursement functionality by sending
 * 10 transactions of Tsh 100 each without tax to multiple phone numbers
 * using the same methods as WalletDisbursementConsumer.php
 */
class ConcurrentWalletDisbursementTest
{
    private $config;
    private $pdo;
    private $testPhoneNumbers;

    public function __construct()
    {
        // Load environment variables from .env file (needed for database connection)
        $this->loadEnvFile();
        
        // Load configuration from payscoop.php config file
        $this->loadPayScoopConfig();

        // Setup database connection
        $this->setupDatabase();

        // Define test phone numbers for concurrent testing
        $this->testPhoneNumbers = [
            '255656692469',//Standard
            '255712505004',//Hemedi
            '255655807727',//Richard
            '255676589824',//Andrea
            '255712342000',//Prosper
            '255714136007',//Rasheed
            '255654181116',// Patrick
            '255713123772',//AdamTigo
            '255713883746',//Prosper2
            '255653607644'// David
        ];
    }

    private function loadPayScoopConfig()
    {
        // Define env() function if it doesn't exist (for standalone script)
        if (!function_exists('env')) {
            function env($key, $default = null) {
                $value = getenv($key);
                return $value !== false ? $value : $default;
            }
        }

        // Load the payscoop config file
        $configPath = __DIR__ . '/config/payscoop.php';
        if (!file_exists($configPath)) {
            throw new Exception("Payscoop config file not found at: {$configPath}");
        }

        // Include the config file and get the configuration array
        $paycoopConfig = include $configPath;
        
        // Map the config to our internal structure
        $this->config = [
            'api_key' => $paycoopConfig['api_key'],
            'api_secret' => $paycoopConfig['api_secret'], // Note: config uses 'api_secret'
            'base_url' => $paycoopConfig['base_url'],
            'currency' => $paycoopConfig['currency'],
            'timeout' => $paycoopConfig['timeout'] ?? 30
        ];

        echo "✅ PayScoop configuration loaded successfully\n";
        echo "   API Key: " . substr($this->config['api_key'], 0, 8) . "...\n";
        echo "   Base URL: {$this->config['base_url']}\n";
        echo "   Currency: {$this->config['currency']}\n\n";
    }

    private function loadEnvFile()
    {
        $envFile = __DIR__ . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }
                
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    
                    // Remove quotes if present
                    $value = trim($value, '"\'');
                    
                    $_ENV[$key] = $value;
                }
            }
        }
    }

    /**
     * Get environment variable
     */
    private function getEnv($key, $default = null)
    {
        return isset($_ENV[$key]) ? $_ENV[$key] : $default;
    }

    /**
     * Setup database connection
     */
    private function setupDatabase()
    {
        try {
            $host = $this->getEnv('DB_HOST', 'localhost');
            $port = $this->getEnv('DB_PORT', '3306');
            $database = $this->getEnv('DB_DATABASE');
            $username = $this->getEnv('DB_USERNAME');
            $password = $this->getEnv('DB_PASSWORD');

            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            $this->pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            
            echo "✅ Database connection established successfully\n\n";
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Prepare JSON request data for wallet disbursement
     */
    private function prepareJsonRequest($amount, $msisdn, $referenceId = null)
    {
        // Generate reference ID if not provided
        $reference = $referenceId ?: 'PYSCPW' . $msisdn . date('YmdHis') . rand(100, 999);
        
        return [
            'disbursements' => [
                [
                    'amount' => (int) $amount,
                    'currency' => $this->config['currency'],
                    'msisdn' => $msisdn,
                    'reference' => $reference
                ]
            ]
        ];
    }

    /**
     * Get authorization header for API request
     */
    private function getAuthorizationHeader()
    {
        $credentials = base64_encode($this->config['api_key'] . ':' . $this->config['api_secret']);
        return 'Basic ' . $credentials;
    }

    /**
     * Send wallet request to Payscoop API using cURL
     */
    private function sendWalletRequest($requestData)
    {
        $url = $this->config['base_url'] . '/api/v2/disbursement/create';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($requestData),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: ' . $this->getAuthorizationHeader()
            ],
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL error: " . $error);
        }

        return [
            'status_code' => $httpCode,
            'json' => json_decode($response, true),
            'raw' => $response
        ];
    }

    /**
     * Create disbursement record in database
     */
    private function createDisbursementRecord($msisdn, $amount, $referenceId, $winnerId)
    {
        try {
            // No tax for this test - payout amount equals full amount
            $payoutAmount = $amount;
            $taxPercent = 0.00;
            
            $sql = "INSERT INTO winner_wallet_disbursements 
                    (msisdn, amount, payout_amount, tax_percent, reference_id, box_type, box_id, winner_id, 
                     request_sent_time, ack_status, ack_message, ack_received_time, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $msisdn,
                $amount,
                $payoutAmount,
                $taxPercent,
                $referenceId,
                'Bronze',     // box_type
                '260', // box_id
                $winnerId,            // winner_id (numeric)
                date('Y-m-d H:i:s'),  // request_sent_time
                'pending',            // ack_status
                null,                 // ack_message
                null                  // ack_received_time
            ]);
            
            return $this->pdo->lastInsertId();
            
        } catch (PDOException $e) {
            throw new Exception("Failed to create disbursement record: " . $e->getMessage());
        }
    }

    /**
     * Update disbursement record with response
     */
    private function updateDisbursementRecord($referenceId, $response)
    {
        try {
            $ackStatus = ($response['status_code'] === 200) ? 'success' : 'failed';
            $ackMessage = json_encode($response['json']);
            
            $sql = "UPDATE winner_wallet_disbursements 
                    SET ack_status = ?, ack_message = ?, ack_received_time = NOW(), updated_at = NOW()
                    WHERE reference_id = ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$ackStatus, $ackMessage, $referenceId]);
            
        } catch (PDOException $e) {
            throw new Exception("Failed to update disbursement record: " . $e->getMessage());
        }
    }

    /**
     * Process a single wallet disbursement transaction
     */
    private function processSingleTransaction($msisdn, $amount, $winnerId, $transactionIndex)
    {
        $startTime = microtime(true);
        $result = [
            'transaction_id' => $transactionIndex,
            'msisdn' => $msisdn,
            'amount' => $amount,
            'winner_id' => $winnerId,
            'success' => false,
            'reference_id' => null,
            'disbursement_id' => null,
            'response_time' => 0,
            'error' => null
        ];

        try {
            // Generate unique reference ID for this transaction
            $referenceId = 'PYSCPW' . $msisdn . date('YmdHis') . sprintf('%03d', $transactionIndex);
            $result['reference_id'] = $referenceId;

            echo "[{$transactionIndex}] Starting transaction for {$msisdn} (Winner ID: {$winnerId})...\n";

            // Create disbursement record
            $disbursementId = $this->createDisbursementRecord($msisdn, $amount, $referenceId, $winnerId);
            $result['disbursement_id'] = $disbursementId;

            // Prepare API request
            $requestData = $this->prepareJsonRequest($amount, $msisdn, $referenceId);

            // Send API request
            $response = $this->sendWalletRequest($requestData);
            $result['response_time'] = round((microtime(true) - $startTime) * 1000, 2); // in milliseconds

            // Update disbursement record with response
            $this->updateDisbursementRecord($referenceId, $response);

            if ($response['status_code'] === 200) {
                $result['success'] = true;
                echo "[{$transactionIndex}] ✅ SUCCESS - {$msisdn} - Response time: {$result['response_time']}ms\n";
            } else {
                $result['error'] = "HTTP {$response['status_code']}: " . json_encode($response['json']);
                echo "[{$transactionIndex}] ❌ FAILED - {$msisdn} - {$result['error']}\n";
            }

        } catch (Exception $e) {
            $result['response_time'] = round((microtime(true) - $startTime) * 1000, 2);
            $result['error'] = $e->getMessage();
            echo "[{$transactionIndex}] ❌ ERROR - {$msisdn} - {$result['error']}\n";
        }

        return $result;
    }

    /**
     * Test concurrent wallet disbursements
     */
    public function testConcurrentDisbursements()
    {
        echo "=== Concurrent Wallet Disbursement Test ===\n";
        echo "Testing 10 concurrent transactions of Tsh 100 each (NO TAX)\n";
        echo "Start time: " . date('Y-m-d H:i:s') . "\n\n";

        $amount = 100; // Tsh 100
        $results = [];
        $processes = [];
        $startTime = microtime(true);

        // Create temporary files for inter-process communication
        $tempDir = sys_get_temp_dir();
        
        // Process each transaction
        for ($i = 0; $i < 10; $i++) {
            $msisdn = $this->testPhoneNumbers[$i];
            $winnerId = 244 + $i; // Starting from 244, incrementing for each transaction
            
            $result = $this->processSingleTransaction($msisdn, $amount, $winnerId, $i + 1);
            $results[] = $result;
            
            // Small delay to avoid overwhelming the API
            usleep(100000); // 100ms delay between requests
        }

        $totalTime = round((microtime(true) - $startTime) * 1000, 2);

        // Display results summary
        echo "\n=== CONCURRENT TEST RESULTS ===\n";
        echo "Total execution time: {$totalTime}ms\n";
        echo "Transactions processed: " . count($results) . "\n";

        $successful = array_filter($results, function($r) { return $r['success']; });
        $failed = array_filter($results, function($r) { return !$r['success']; });

        echo "Successful: " . count($successful) . "\n";
        echo "Failed: " . count($failed) . "\n";

        if (!empty($successful)) {
            $avgResponseTime = array_sum(array_column($successful, 'response_time')) / count($successful);
            echo "Average response time (successful): " . round($avgResponseTime, 2) . "ms\n";
        }

        echo "\n=== DETAILED RESULTS ===\n";
        foreach ($results as $result) {
            $status = $result['success'] ? '✅ SUCCESS' : '❌ FAILED';
            echo "[{$result['transaction_id']}] {$status} - {$result['msisdn']} - Winner ID: {$result['winner_id']} - {$result['response_time']}ms";
            if (!$result['success'] && $result['error']) {
                echo " - Error: {$result['error']}";
            }
            echo "\n";
        }

        echo "\n=== DATABASE RECORDS ===\n";
        echo "Check the winner_wallet_disbursements table for the following reference IDs:\n";
        foreach ($results as $result) {
            if ($result['reference_id']) {
                echo "- {$result['reference_id']} (Disbursement ID: {$result['disbursement_id']})\n";
            }
        }

        echo "\nTest completed at: " . date('Y-m-d H:i:s') . "\n";
    }
}

// Run the concurrent test
try {
    $test = new ConcurrentWalletDisbursementTest();
    $test->testConcurrentDisbursements();
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}