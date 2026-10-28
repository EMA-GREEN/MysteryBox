<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'msisdn',
        'user_token',
        'token_expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'user_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'token_expires_at' => 'datetime',
        ];
    }

    /**
     * Generate a new JWT user token with 3-hour expiry
     *
     * @return string
     */
    public function generateUserToken(): string
    {
        $payload = [
            'user_id' => $this->id,
            'msisdn' => $this->msisdn,
            'name' => $this->name,
            'iat' => time(),
            'exp' => time() + (3 * 60 * 60), // 3 hours
            'iss' => config('app.name', 'MysteryBox'),
            'aud' => 'mysterybox_api'
        ];

        $key = config('app.key');
        $algorithm = 'HS256';

        try {
            // Generate JWT token
            $token = JWT::encode($payload, $key, $algorithm);
            $expiresAt = Carbon::createFromTimestamp($payload['exp']);

            // Update the user record with the new token
            $updated = $this->update([
                'user_token' => $token,
                'token_expires_at' => $expiresAt,
            ]);

            if (!$updated) {
                Log::error('Failed to update user with JWT token', [
                    'user_id' => $this->id,
                    'msisdn' => $this->msisdn
                ]);
                throw new \Exception('Failed to save JWT token to database');
            }

            // Refresh the model to ensure we have the latest data
            $this->refresh();

            // Verify the token was actually saved
            if ($this->user_token !== $token) {
                Log::error('JWT token verification failed after save', [
                    'user_id' => $this->id,
                    'msisdn' => $this->msisdn,
                    'expected_token' => substr($token, 0, 50) . '...',
                    'actual_token' => substr($this->user_token ?? 'NULL', 0, 50) . '...'
                ]);
                throw new \Exception('JWT token was not saved correctly to database');
            }

            // Log successful token generation
            Log::info('JWT user token generated successfully', [
                'user_id' => $this->id,
                'msisdn' => $this->msisdn,
                'token' => substr($token, 0, 50) . '...',
                'expires_at' => $expiresAt,
                'update_success' => $updated,
                'token_saved' => true
            ]);

            return $token;

        } catch (\Exception $e) {
            Log::error('Error generating JWT user token', [
                'user_id' => $this->id,
                'msisdn' => $this->msisdn,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Validate and decode a JWT token
     *
     * @param string $token
     * @return array|false
     */
    public static function validateJwtToken(string $token)
    {
        try {
            $key = config('app.key');
            $decoded = JWT::decode($token, new Key($key, 'HS256'));
            
            // Convert to array for easier handling
            $payload = json_decode(json_encode($decoded), true);
            
            // Check if token is expired
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                Log::warning('JWT token is expired', [
                    'token' => substr($token, 0, 50) . '...',
                    'exp' => $payload['exp'],
                    'current_time' => time()
                ]);
                return false;
            }

            return $payload;
        } catch (\Exception $e) {
            Log::error('JWT token validation failed', [
                'token' => substr($token, 0, 50) . '...',
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check if the user token is valid and not expired
     *
     * @return bool
     */
    public function isUserTokenValid(): bool
    {
        if (!$this->user_token || !$this->token_expires_at) {
            return false;
        }

        // First check database expiration
        if (!$this->token_expires_at->isFuture()) {
            return false;
        }

        // Then validate JWT token
        $payload = self::validateJwtToken($this->user_token);
        if (!$payload) {
            return false;
        }

        // Verify the token belongs to this user
        return $payload['user_id'] == $this->id && $payload['msisdn'] == $this->msisdn;
    }

    /**
     * Revoke the current user token
     *
     * @return void
     */
    public function revokeUserToken(): void
    {
        $this->update([
            'user_token' => null,
            'token_expires_at' => null,
        ]);
    }

    /**
     * Find user by MSISDN
     *
     * @param string $msisdn
     * @return User|null
     */
    public static function findByMsisdn(string $msisdn): ?User
    {
        return static::where('msisdn', $msisdn)->first();
    }
}
