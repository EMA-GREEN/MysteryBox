<?php

/**
 * Wallet Disbursement Test Script
 * 
 * This script tests the wallet disbursement functionality by sending
 * Tsh 100 to phone number 255656692469 using the same methods as
 * WalletDisbursementConsumer.php
 */
class WalletDisbursementTest
{
    private $config;
    private $pdo;

    public function __construct()
    {
        // Load environment variables from .env file (needed for database connection)
        $this->loadEnvFile();
        
        // Load configuration from payscoop.php config file
        $this->loadPayScoopConfig();

        // Setup database connection
        $this->setupDatabase();
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
            'secret_key' => $paycoopConfig['api_secret'], // Note: config uses 'api_secret'
            'base_url' => $paycoopConfig['base_url'],
            'wallet_endpoint' => $paycoopConfig['endpoints']['wallet'],
            'currency' => $paycoopConfig['currency'],
            'timeout' => $paycoopConfig['timeout'],
            'retry_attempts' => $paycoopConfig['retry_attempts'],
            'retry_delay' => $paycoopConfig['retry_delay']
        ];

        echo "Configuration loaded from payscoop.php config file.\n";
        echo "API Key: " . substr($this->config['api_key'], 0, 8) . "...\n";
        echo "Base URL: " . $this->config['base_url'] . "\n";
        echo "Currency: " . $this->config['currency'] . "\n";
    }

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
            
            echo "Database connection established successfully.\n";
        } catch (PDOException $e) {
            echo "Database connection failed: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    /**
     * Simple .env file loader
     */
    private function loadEnvFile()
    {
        $envFile = __DIR__ . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value, " \t\n\r\0\x0B\"'");
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
     * Prepare JSON request data for wallet disbursement
     * Based on prepareJsonRequest method from WalletDisbursementConsumer
     */
    private function prepareJsonRequest($amount, $msisdn, $referenceId = null)
    {
        // Generate reference ID if not provided
        $reference = $referenceId ?: 'PYSCPW' . $msisdn . date('YmdHis');
        
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
     * Based on getAuthorizationHeader method from WalletDisbursementConsumer
     */
    private function getAuthorizationHeader()
    {
        $credentials = base64_encode($this->config['api_key'] . ':' . $this->config['secret_key']);
        return 'Basic ' . $credentials;
    }

    /**
     * Send wallet request to Payscoop API using cURL
     * Based on sendWalletRequest method from WalletDisbursementConsumer
     */
    private function sendWalletRequest($requestData)
    {
        $url = $this->config['base_url'] . $this->config['wallet_endpoint'];
        
        echo "Sending request to: " . $url . "\n";
        echo "Request data: " . json_encode($requestData, JSON_PRETTY_PRINT) . "\n";
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($requestData),
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: ' . $this->getAuthorizationHeader()
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL Error: " . $error);
        }
        
        return [
            'status_code' => $httpCode,
            'body' => $response,
            'json' => json_decode($response, true)
        ];
    }

    // Create disbursement record
    private function createDisbursementRecord($msisdn, $amount, $payoutAmount, $taxPercent, $referenceId)
    {
        try {
            $sql = "INSERT INTO winner_wallet_disbursements (
                msisdn, amount, payout_amount, tax_percent, reference_id, 
                box_type, box_id, winner_id, request_sent_time, 
                ack_status, ack_message, ack_received_time,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $msisdn,
                $amount,
                $payoutAmount,
                $taxPercent,
                $referenceId,
                'Platinum',           // box_type
                '260',       // box_id
                244,                  // winner_id
                date('Y-m-d H:i:s'),  // request_sent_time
                'pending',            // ack_status
                null,                 // ack_message
                null                  // ack_received_time
            ]);
            
            $disbursementId = $this->pdo->lastInsertId();
            echo "✅ Disbursement record created with ID: {$disbursementId}\n";
            return $disbursementId;
            
        } catch (PDOException $e) {
            echo "❌ Failed to create disbursement record: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    // Update disbursement record with response
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
            
            echo "✅ Disbursement record updated with response status: {$ackStatus}\n";
            
        } catch (PDOException $e) {
            echo "❌ Failed to update disbursement record: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Test wallet disbursement to send Tsh 100 to 255656692469
     */
    public function testWalletDisbursement()
    {
        echo "=== Wallet Disbursement Test ===\n";
        
        // Test data
        $msisdn = '255656692469';
        $amount = 500; // Tsh 100
        $taxPercent = 18.0;
        $payoutAmount = $amount - ($amount * $taxPercent / 100);
        $referenceId = 'PYSCPW' . $msisdn . date('YmdHis');
        
        echo "MSISDN: {$msisdn}\n";
        echo "Amount: {$amount} TZS\n";
        echo "Tax Percent: {$taxPercent}%\n";
        echo "Payout Amount: {$payoutAmount} TZS\n";
        echo "Reference ID: {$referenceId}\n\n";
        
        try {
            // Create disbursement record
            echo "--- Creating Disbursement Record ---\n";
            $disbursementId = $this->createDisbursementRecord($msisdn, $amount, $payoutAmount, $taxPercent, $referenceId);
            
            echo "\n--- Sending API Request ---\n";
            $requestData = $this->prepareJsonRequest($payoutAmount, $msisdn, $referenceId);
            $response = $this->sendWalletRequest($requestData);
            
            // Update disbursement record with response
            echo "\n--- Updating Disbursement Record ---\n";
            $this->updateDisbursementRecord($referenceId, $response);
            
            echo "\n--- Final Results ---\n";
            $statusCode = $response['status_code'];
            $responseData = $response['json'];
            
            echo "Response Status Code: " . $statusCode . "\n";
            echo "Response Body: " . $response['body'] . "\n";
            echo "Response Data: " . json_encode($responseData, JSON_PRETTY_PRINT) . "\n";
            
            if ($statusCode >= 200 && $statusCode < 300) {
                echo "\n✅ SUCCESS: Wallet disbursement request sent successfully!\n";
                echo "Status: " . ($responseData['status'] ?? 'Unknown') . "\n";
                echo "Message: " . ($responseData['message'] ?? 'No message') . "\n";
            } else {
                echo "\n❌ ERROR: Wallet disbursement request failed!\n";
                echo "Error: " . ($responseData['message'] ?? 'Unknown error') . "\n";
                echo "HTTP Status: " . $statusCode . "\n";
            }
            
        } catch (\Exception $e) {
            echo "\n❌ EXCEPTION: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
        }
    }

    /**
     * Get dummy response for testing (when API is not available)
     * Based on getDummyResponse method from WalletDisbursementConsumer
     */
    private function getDummyResponse()
    {
        return [
            'message' => 'Disbursement created successfully',
            'status' => 'success',
            'statusCode' => 200
        ];
    }

    /**
     * Test with dummy response (for testing without hitting the actual API)
     */
    public function testWithDummyResponse()
    {
        echo "=== Wallet Disbursement Test (Dummy Response) ===\n";
        echo "Amount: Tsh 100\n";
        echo "Phone Number: 255656692469\n";
        echo "Currency: " . $this->config['currency'] . "\n";
        echo "================================================\n\n";

        try {
            // Prepare request data
            $amount = 100; // Tsh 100
            $msisdn = '255656692469';
            $referenceId = 'PYSCPW' . $msisdn . date('YmdHis');
            
            echo "Generated Reference ID: " . $referenceId . "\n\n";
            
            $requestData = $this->prepareJsonRequest($amount, $msisdn, $referenceId);
            
            echo "Request data that would be sent:\n";
            echo json_encode($requestData, JSON_PRETTY_PRINT) . "\n\n";
            
            // Use dummy response instead of actual API call
            $responseData = $this->getDummyResponse();
            
            echo "Dummy Response Data: " . json_encode($responseData, JSON_PRETTY_PRINT) . "\n";
            echo "\n✅ SUCCESS: Dummy wallet disbursement test completed!\n";
            echo "Status: " . ($responseData['status'] ?? 'Unknown') . "\n";
            echo "Message: " . ($responseData['message'] ?? 'No message') . "\n";
            
        } catch (\Exception $e) {
            echo "\n❌ EXCEPTION: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
        }
    }
}

// Run the test
if (php_sapi_name() === 'cli') {
    echo "Starting Wallet Disbursement Test...\n\n";
    
    $test = new WalletDisbursementTest();
    
    // Test with actual API call
    $test->testWalletDisbursement();
    
    // Uncomment the line below to test with dummy response instead
    // $test->testWithDummyResponse();
    
    echo "\nTest completed.\n";
}