<?php

/**
 * Create Access Token Script
 * This script creates or updates the latest access token with 10-day expiry
 * Only updates if token is expiring tomorrow (within 1 day)
 * 
 * Usage: php create_access_token.php
 */

// Load Laravel environment
require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CreateAccessToken
{
    private $mnoConfig;
    
    public function __construct()
    {
        // Set timezone
        date_default_timezone_set('Africa/Dar_es_Salaam');
        
        // Get MNO services configuration
        $this->mnoConfig = config('mno_services.yas');
    }
    
    /**
     * Log message using Laravel's default logging
     */
    private function logMessage($message)
    {
        $timestamp = Carbon::now()->format('Y-m-d H:i:s');
        $formattedMessage = "[{$timestamp}] {$message}";
        echo $formattedMessage . PHP_EOL;
        
        // Use Laravel's default logging
        Log::info($message);
    }
    
    /**
     * Check if token needs to be updated (expiring tomorrow)
     */
    private function shouldUpdateToken()
    {
        $latestToken = $this->getLatestToken();
        
        if (!$latestToken) {
            $this->logMessage("No existing token found. Token creation needed.");
            return true;
        }
        
        if (!$latestToken->EXPIRES_AT) {
            $this->logMessage("Token has no expiry date. Token update needed.");
            return true;
        }
        
        $expiresAt = Carbon::parse($latestToken->EXPIRES_AT);
        $now = Carbon::now();
        
        // Check if token expires tomorrow (within 1 day)
        $daysUntilExpiry = $now->diffInDays($expiresAt);
        
        if ($daysUntilExpiry <= 1) {
            $this->logMessage("Token expires in {$daysUntilExpiry} day(s). Token update needed.");
            return true;
        }
        
        $this->logMessage("Token is still valid for {$daysUntilExpiry} day(s). No update needed.");
        return false;
    }
    
    /**
     * Get new token from API
     */
    private function getNewToken()
    {
        $this->logMessage("Requesting new token from API...");
        
        $tokenUrl = $this->mnoConfig['token_endpoint'];
        $username = $this->mnoConfig['username'];
        $password = $this->mnoConfig['password'];
        
        $tokenRequest = json_encode([
            'username' => $username,
            'password' => $password
        ]);
        
        $headers = [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($tokenRequest),
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'X-Requested-With: XMLHttpRequest'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $tokenRequest);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $tokenResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            $this->logMessage("✗ cURL Error: {$error}");
            return false;
        }
        
        if ($httpCode !== 200) {
            $this->logMessage("✗ HTTP Error: {$httpCode}");
            $this->logMessage("Response: {$tokenResponse}");
            return false;
        }
        
        $tokenResponseArray = json_decode($tokenResponse, true);
        
        if (!$tokenResponseArray) {
            $this->logMessage("✗ Failed to decode JSON response");
            $this->logMessage("Response: {$tokenResponse}");
            return false;
        }
        
        if (!isset($tokenResponseArray['token'])) {
            $this->logMessage("✗ Token not found in response");
            $this->logMessage("Response: " . json_encode($tokenResponseArray));
            return false;
        }
        
        $this->logMessage("✓ Successfully obtained new token");
        
        return [
            'access_token' => $tokenResponseArray['token'],
            'refresh_token' => $tokenResponseArray['refreshToken'] ?? null
        ];
    }
    
    /**
     * Get the latest token from database
     */
    private function getLatestToken()
    {
        return DB::table('user_access_token')
            ->orderBy('ID', 'desc')
            ->first();
    }
    
    /**
     * Update the latest token in database
     */
    private function updateLatestToken($accessToken, $refreshToken = null)
    {
        try {
            $latestToken = $this->getLatestToken();
            
            if (!$latestToken) {
                $this->logMessage("No existing token found. Creating new token record...");
                return $this->createNewToken($accessToken, $refreshToken);
            }
            
            $this->logMessage("Updating latest token (ID: {$latestToken->ID})");
            
            // Set expiry to 10 days from now
            $expiry = date("Y-m-d H:i:s", strtotime("+10 days"));
            
            $updateData = [
                'ACCESS_TOKEN' => $accessToken,
                'CREATED_AT' => Carbon::now(),
                'EXPIRES_AT' => $expiry
            ];
            
            if ($refreshToken) {
                $updateData['REFERESH_TOKEN'] = $refreshToken;
            }
            
            DB::table('user_access_token')
                ->where('ID', $latestToken->ID)
                ->update($updateData);
            
            $this->logMessage("✓ Updated latest token (ID: {$latestToken->ID})");
            $this->logMessage("  Expires at: {$expiry}");
            return true;
            
        } catch (Exception $e) {
            $this->logMessage("✗ Failed to update latest token: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create new token record if none exists
     */
    private function createNewToken($accessToken, $refreshToken = null)
    {
        try {
            // Set expiry to 10 days from now
            $expiry = date("Y-m-d H:i:s", strtotime("+10 days"));
            
            $insertData = [
                'ACCESS_TOKEN' => $accessToken,
                'CREATED_AT' => Carbon::now(),
                'EXPIRES_AT' => $expiry
            ];
            
            if ($refreshToken) {
                $insertData['REFERESH_TOKEN'] = $refreshToken;
            }
            
            $id = DB::table('user_access_token')->insertGetId($insertData);
            
            $this->logMessage("✓ Created new token record (ID: {$id})");
            $this->logMessage("  Expires at: {$expiry}");
            return true;
            
        } catch (Exception $e) {
            $this->logMessage("✗ Failed to create new token: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Show current token status
     */
    private function showTokenStatus()
    {
        $this->logMessage("=== Current Token Status ===");
        
        $tokens = DB::table('user_access_token')
            ->orderBy('ID', 'desc')
            ->limit(5) // Show only last 5 tokens
            ->get();
        
        if ($tokens->isEmpty()) {
            $this->logMessage("No tokens found in database");
            return;
        }
        
        foreach ($tokens as $token) {
            $expiresAt = $token->EXPIRES_AT ? Carbon::parse($token->EXPIRES_AT) : null;
            $status = 'Unknown';
            
            if ($expiresAt) {
                $now = Carbon::now();
                $daysUntilExpiry = $now->diffInDays($expiresAt);
                
                if ($daysUntilExpiry < 0) {
                    $status = 'EXPIRED';
                } elseif ($daysUntilExpiry <= 1) {
                    $status = 'EXPIRING TOMORROW';
                } else {
                    $status = 'VALID';
                }
            }
            
            $this->logMessage("ID: {$token->ID} | Status: {$status} | Expires: " . 
                ($expiresAt ? $expiresAt->format('Y-m-d H:i:s') : 'Never') . 
                ($token->ID === $tokens->first()->ID ? ' (LATEST)' : ''));
        }
    }
    
    /**
     * Main execution method
     */
    public function run()
    {
        $this->logMessage("=== Starting Access Token Creation Process ===");
        $this->logMessage("Token Endpoint: " . $this->mnoConfig['token_endpoint']);
        $this->logMessage("Username: " . $this->mnoConfig['username']);
        $this->logMessage("Timezone: Africa/Dar_es_Salaam");
        $this->logMessage("Expiry: 10 days from creation");
        $this->logMessage("Update Logic: Only if expiring tomorrow (within 1 day)");
        
        // Test database connection
        try {
            DB::connection()->getPdo();
            $this->logMessage("✓ Database connection successful");
        } catch (Exception $e) {
            $this->logMessage("✗ Cannot connect to database: " . $e->getMessage());
            exit(1);
        }
        
        // Show current token status
        $this->showTokenStatus();
        
        // Check if token needs to be updated
        if (!$this->shouldUpdateToken()) {
            $this->logMessage("✓ Token is still valid. No update needed.");
            $this->logMessage("=== Access Token Creation Process Completed ===");
            return;
        }
        
        // Get new token from API
        $newTokenData = $this->getNewToken();
        
        if (!$newTokenData) {
            $this->logMessage("✗ Failed to get new token from API");
            exit(1);
        }
        
        // Update or create token
        if ($this->updateLatestToken(
            $newTokenData['access_token'],
            $newTokenData['refresh_token']
        )) {
            $this->logMessage("✓ Token creation/update completed successfully");
        } else {
            $this->logMessage("✗ Token creation/update failed");
            exit(1);
        }
        
        // Show final status
        $this->logMessage("=== Final Token Status ===");
        $this->showTokenStatus();
        
        $this->logMessage("=== Access Token Creation Process Completed ===");
    }
}

// Run the token creator
$creator = new CreateAccessToken();
$creator->run(); 