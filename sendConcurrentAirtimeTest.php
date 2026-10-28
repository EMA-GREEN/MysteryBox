<?php

/**
 * Concurrent Airtime Disbursement Test Script
 * 
 * This script tests concurrent airtime disbursements to multiple phone numbers
 * using the PayScoop API. It sends Tsh 500 airtime to 10 transactions across
 * the specified phone numbers.
 */

class ConcurrentAirtimeDisbursementTest
{
    private $config;
    private $pdo;
    private $testPhoneNumbers;

    public function __construct()
    {
        // Load environment variables
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
            'api_secret' => $paycoopConfig['api_secret'], // Keep consistent naming
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
     * Prepare JSON request data for airtime disbursement
     */
    private function prepareJsonRequest($amount, $msisdn, $referenceId = null)
    {
        // Generate reference ID if not provided
        $reference = $referenceId ?: 'PYSCPA' . $msisdn . date('YmdHis') . rand(100, 999);
        
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
     * Send airtime request to Payscoop API using cURL
     */
    private function sendAirtimeRequest($requestData)
    {
        $url = $this->config['base_url'] . '/api/v2/airtime/requests/create';
        
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
     * Create airtime disbursement record in database
     */
    private function createAirtimeDisbursementRecord($msisdn, $amount, $referenceId, $winnerId)
    {
        try {
            $sql = "INSERT INTO winner_airtime_disbursements 
                    (msisdn, amount, reference_id, box_type, box_id, winner_id, 
                     request_sent_time, ack_status, ack_message, ack_received_time, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $msisdn,
                $amount,
                $referenceId,
                'Bronze',     // box_type
                '260',        // box_id
                $winnerId,    // winner_id (numeric)
                date('Y-m-d H:i:s'),  // request_sent_time
                'pending',    // ack_status
                null,         // ack_message
                null          // ack_received_time
            ]);
            
            return $this->pdo->lastInsertId();
            
        } catch (PDOException $e) {
            throw new Exception("Failed to create airtime disbursement record: " . $e->getMessage());
        }
    }

    /**
     * Update airtime disbursement record with response
     */
    private function updateAirtimeDisbursementRecord($referenceId, $response)
    {
        try {
            $ackStatus = ($response['status_code'] === 200) ? 'success' : 'failed';
            $ackMessage = json_encode($response['json']);
            
            $sql = "UPDATE winner_airtime_disbursements 
                    SET ack_status = ?, ack_message = ?, ack_received_time = NOW(), updated_at = NOW()
                    WHERE reference_id = ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$ackStatus, $ackMessage, $referenceId]);
            
        } catch (PDOException $e) {
            throw new Exception("Failed to update airtime disbursement record: " . $e->getMessage());
        }
    }

    /**
     * Process a single airtime transaction
     */
    private function processSingleTransaction($index, $msisdn, $amount, $winnerId)
    {
        $startTime = microtime(true);
        
        try {
            // Generate unique reference ID
            $referenceId = 'PYSCPA' . $msisdn . date('YmdHis') . sprintf('%03d', $index);
            
            // Create database record first
            $disbursementId = $this->createAirtimeDisbursementRecord($msisdn, $amount, $referenceId, $winnerId);
            
            // Prepare request data
            $requestData = $this->prepareJsonRequest($amount, $msisdn, $referenceId);
            
            // Send airtime request
            $response = $this->sendAirtimeRequest($requestData);
            
            // Update database record with response
            $this->updateAirtimeDisbursementRecord($referenceId, $response);
            
            $endTime = microtime(true);
            $duration = ($endTime - $startTime) * 1000; // Convert to milliseconds
            
            if ($response['status_code'] === 200) {
                echo "[{$index}] ✅ SUCCESS - {$msisdn} - Winner ID: {$winnerId} - {$duration}ms\n";
                return [
                    'success' => true,
                    'msisdn' => $msisdn,
                    'winner_id' => $winnerId,
                    'reference_id' => $referenceId,
                    'disbursement_id' => $disbursementId,
                    'duration' => $duration,
                    'response' => $response['json']
                ];
            } else {
                $errorMsg = "HTTP {$response['status_code']}: " . json_encode($response['json']);
                echo "[{$index}] ❌ FAILED - {$msisdn} - {$errorMsg}\n";
                return [
                    'success' => false,
                    'msisdn' => $msisdn,
                    'winner_id' => $winnerId,
                    'reference_id' => $referenceId,
                    'disbursement_id' => $disbursementId,
                    'duration' => $duration,
                    'error' => $errorMsg
                ];
            }
            
        } catch (Exception $e) {
            $endTime = microtime(true);
            $duration = ($endTime - $startTime) * 1000;
            
            echo "[{$index}] ❌ FAILED - {$msisdn} - Exception: " . $e->getMessage() . "\n";
            return [
                'success' => false,
                'msisdn' => $msisdn,
                'winner_id' => $winnerId,
                'reference_id' => $referenceId ?? 'N/A',
                'disbursement_id' => $disbursementId ?? 'N/A',
                'duration' => $duration,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test concurrent airtime disbursements
     */
    public function testConcurrentAirtime()
    {
        $amount = 500; // Tsh 500 for airtime
        $totalTransactions = 10;
        $winnerId = 244; // Starting winner ID
        
        echo "=== Concurrent Airtime Disbursement Test ===\n";
        echo "Testing {$totalTransactions} concurrent transactions of Tsh {$amount} each\n";
        echo "Start time: " . date('Y-m-d H:i:s') . "\n\n";
        
        $results = [];
        $overallStartTime = microtime(true);
        
        // Process transactions with minimal delay to simulate concurrency
        for ($i = 1; $i <= $totalTransactions; $i++) {
            $msisdn = $this->testPhoneNumbers[$i - 1];
            $currentWinnerId = $winnerId + ($i - 1);
            
            echo "[{$i}] Starting airtime transaction for {$msisdn} (Winner ID: {$currentWinnerId})...\n";
            
            $result = $this->processSingleTransaction($i, $msisdn, $amount, $currentWinnerId);
            $results[] = $result;
            
            // Small delay to avoid overwhelming the API (100ms)
            usleep(100000);
        }
        
        $overallEndTime = microtime(true);
        $totalDuration = ($overallEndTime - $overallStartTime) * 1000;
        
        // Display summary
        $this->displayResults($results, $totalDuration);
    }

    /**
     * Display test results summary
     */
    private function displayResults($results, $totalDuration)
    {
        $successful = array_filter($results, function($r) { return $r['success']; });
        $failed = array_filter($results, function($r) { return !$r['success']; });
        
        echo "\n=== CONCURRENT AIRTIME TEST RESULTS ===\n";
        echo "Total execution time: " . number_format($totalDuration, 2) . "ms\n";
        echo "Transactions processed: " . count($results) . "\n";
        echo "Successful: " . count($successful) . "\n";
        echo "Failed: " . count($failed) . "\n\n";
        
        echo "=== DETAILED RESULTS ===\n";
        foreach ($results as $index => $result) {
            $status = $result['success'] ? '✅ SUCCESS' : '❌ FAILED';
            $duration = number_format($result['duration'], 2);
            $error = $result['success'] ? '' : " - Error: {$result['error']}";
            
            echo "[" . ($index + 1) . "] {$status} - {$result['msisdn']} - Winner ID: {$result['winner_id']} - {$duration}ms{$error}\n";
        }
        
        echo "\n=== DATABASE RECORDS ===\n";
        echo "Check the winner_airtime_disbursements table for the following reference IDs:\n";
        foreach ($results as $index => $result) {
            if (isset($result['reference_id']) && $result['reference_id'] !== 'N/A') {
                echo "- {$result['reference_id']} (Disbursement ID: {$result['disbursement_id']})\n";
            }
        }
        
        echo "\nTest completed at: " . date('Y-m-d H:i:s') . "\n";
    }
}

// Run the test
try {
    $test = new ConcurrentAirtimeDisbursementTest();
    $test->testConcurrentAirtime();
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    exit(1);
}