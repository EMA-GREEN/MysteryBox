<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CreateAccessToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'token:create {--force : Force token creation even if current token is valid}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create or update the latest access token with 10-day expiry';

    /**
     * MNO services configuration
     */
    private $mnoConfig;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Set timezone
        date_default_timezone_set('Africa/Dar_es_Salaam');
        
        // Get MNO services configuration
        $this->mnoConfig = config('mno_services.yas');
        
        if (!$this->mnoConfig) {
            $this->error('MNO services configuration not found');
            return 1;
        }

        $this->info('=== Starting Access Token Creation Process ===');
        $this->info('Token Endpoint: ' . $this->mnoConfig['token_endpoint']);
        $this->info('Username: ' . $this->mnoConfig['username']);
        $this->info('Timezone: Africa/Dar_es_Salaam');
        $this->info('Expiry: 10 days from creation');

        // Test database connection
        try {
            DB::connection()->getPdo();
            $this->info('✓ Database connection successful');
        } catch (\Exception $e) {
            $this->error('Cannot connect to database: ' . $e->getMessage());
            return 1;
        }

        // Show current token status
        $this->showTokenStatus();

        // Check if we need to create/update token
        if (!$this->shouldCreateToken()) {
            $this->info('Current token is still valid. Use --force to create new token anyway.');
            return 0;
        }

        // Get new token from API
        $newTokenData = $this->getNewToken();

        if (!$newTokenData) {
            $this->error('Failed to get new token from API');
            return 1;
        }

        // Update or create token
        if ($this->updateLatestToken(
            $newTokenData['access_token'],
            $newTokenData['refresh_token']
        )) {
            $this->info('✓ Token creation/update completed successfully');
        } else {
            $this->error('Token creation/update failed');
            return 1;
        }

        // Show final status
        $this->info('=== Final Token Status ===');
        $this->showTokenStatus();

        $this->info('=== Access Token Creation Process Completed ===');
        return 0;
    }

    /**
     * Check if we should create a new token
     */
    private function shouldCreateToken()
    {
        if ($this->option('force')) {
            return true;
        }

        $latestToken = $this->getLatestToken();
        
        if (!$latestToken) {
            return true;
        }

        if (!$latestToken->EXPIRES_AT) {
            return true;
        }

        $expiresAt = Carbon::parse($latestToken->EXPIRES_AT);
        $now = Carbon::now();

        // Create new token if current one expires tomorrow (within 1 day)
        $daysUntilExpiry = $now->diffInDays($expiresAt);
        
        if ($daysUntilExpiry <= 1) {
            $this->info("Token expires in {$daysUntilExpiry} day(s). Token update needed.");
            return true;
        }
        
        $this->info("Token is still valid for {$daysUntilExpiry} day(s). No update needed.");
        return false;
    }

    /**
     * Get new token from API
     */
    private function getNewToken()
    {
        $this->info('Requesting new token from API...');

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
            $this->error('cURL Error: ' . $error);
            Log::error('Token API cURL Error: ' . $error);
            return false;
        }

        if ($httpCode !== 200) {
            $this->error('HTTP Error: ' . $httpCode);
            $this->error('Response: ' . $tokenResponse);
            Log::error('Token API HTTP Error: ' . $httpCode . ' - ' . $tokenResponse);
            return false;
        }

        $tokenResponseArray = json_decode($tokenResponse, true);

        if (!$tokenResponseArray) {
            $this->error('Failed to decode JSON response');
            $this->error('Response: ' . $tokenResponse);
            Log::error('Token API JSON decode failed: ' . $tokenResponse);
            return false;
        }

        if (!isset($tokenResponseArray['token'])) {
            $this->error('Token not found in response');
            $this->error('Response: ' . json_encode($tokenResponseArray));
            Log::error('Token API response missing token: ' . json_encode($tokenResponseArray));
            return false;
        }

        $this->info('✓ Successfully obtained new token');
        Log::info('Successfully obtained new token from API');

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
                $this->info('No existing token found. Creating new token record...');
                return $this->createNewToken($accessToken, $refreshToken);
            }

            $this->info('Updating latest token (ID: ' . $latestToken->ID . ')');

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

            $this->info('✓ Updated latest token (ID: ' . $latestToken->ID . ')');
            $this->info('  Expires at: ' . $expiry);
            Log::info('Updated latest token (ID: ' . $latestToken->ID . ') expires at: ' . $expiry);
            return true;

        } catch (\Exception $e) {
            $this->error('Failed to update latest token: ' . $e->getMessage());
            Log::error('Failed to update latest token: ' . $e->getMessage());
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

            $this->info('✓ Created new token record (ID: ' . $id . ')');
            $this->info('  Expires at: ' . $expiry);
            Log::info('Created new token record (ID: ' . $id . ') expires at: ' . $expiry);
            return true;

        } catch (\Exception $e) {
            $this->error('Failed to create new token: ' . $e->getMessage());
            Log::error('Failed to create new token: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Show current token status
     */
    private function showTokenStatus()
    {
        $this->info('=== Current Token Status ===');

        $tokens = DB::table('user_access_token')
            ->orderBy('ID', 'desc')
            ->limit(5) // Show only last 5 tokens
            ->get();

        if ($tokens->isEmpty()) {
            $this->info('No tokens found in database');
            return;
        }

        $headers = ['ID', 'Status', 'Created At', 'Expires At'];
        $rows = [];

        foreach ($tokens as $token) {
            $expiresAt = $token->EXPIRES_AT ? Carbon::parse($token->EXPIRES_AT) : null;
            $status = 'Unknown';

            if ($expiresAt) {
                $now = Carbon::now();
                if ($expiresAt->isPast()) {
                    $status = 'EXPIRED';
                } elseif ($now->diffInDays($expiresAt) <= 1) {
                    $status = 'EXPIRING TOMORROW';
                } else {
                    $status = 'VALID';
                }
            }

            $rows[] = [
                $token->ID . ($token->ID === $tokens->first()->ID ? ' (LATEST)' : ''),
                $status,
                $token->CREATED_AT ? Carbon::parse($token->CREATED_AT)->format('Y-m-d H:i:s') : 'N/A',
                $expiresAt ? $expiresAt->format('Y-m-d H:i:s') : 'Never'
            ];
        }

        $this->table($headers, $rows);
    }
} 