<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Staff extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\StaffFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'staff';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
        'role',
        'staff_token',
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
        'staff_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'token_expires_at' => 'datetime',
        ];
    }

    /**
     * Generate a new JWT staff token with 3-hour expiry
     *
     * @return string
     */
    public function generateStaffToken(): string
    {
        $payload = [
            'staff_id' => $this->id,
            'email' => $this->email,
            'username' => $this->username,
            'name' => $this->name,
            'role' => $this->role,
            'iat' => time(),
            'exp' => time() + (3 * 60 * 60), // 3 hours
            'iss' => config('app.name', 'MysteryBox'),
            'aud' => 'mysterybox_staff_api'
        ];

        $key = config('app.key');
        $algorithm = 'HS256';

        try {
            // Generate JWT token
            $token = JWT::encode($payload, $key, $algorithm);
            $expiresAt = Carbon::createFromTimestamp($payload['exp']);

            // Update the staff record with the new token
            $updated = $this->update([
                'staff_token' => $token,
                'token_expires_at' => $expiresAt,
            ]);

            if (!$updated) {
                Log::error('Failed to update staff with JWT token', [
                    'staff_id' => $this->id,
                    'email' => $this->email
                ]);
                throw new \Exception('Failed to save JWT token to database');
            }

            // Refresh the model to ensure we have the latest data
            $this->refresh();

            // Verify the token was actually saved
            if ($this->staff_token !== $token) {
                Log::error('JWT token verification failed after save', [
                    'staff_id' => $this->id,
                    'email' => $this->email,
                    'expected_token' => substr($token, 0, 50) . '...',
                    'actual_token' => substr($this->staff_token ?? 'NULL', 0, 50) . '...'
                ]);
                throw new \Exception('JWT token was not saved correctly to database');
            }

            // Log successful token generation
            Log::info('JWT staff token generated successfully', [
                'staff_id' => $this->id,
                'email' => $this->email,
                'role' => $this->role,
                'token' => substr($token, 0, 50) . '...',
                'expires_at' => $expiresAt,
                'update_success' => $updated,
                'token_saved' => true
            ]);

            return $token;

        } catch (\Exception $e) {
            Log::error('Error generating JWT staff token', [
                'staff_id' => $this->id,
                'email' => $this->email,
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
     * Check if the staff token is valid and not expired
     *
     * @return bool
     */
    public function isStaffTokenValid(): bool
    {
        if (!$this->staff_token || !$this->token_expires_at) {
            return false;
        }

        // First check database expiration
        if (!$this->token_expires_at->isFuture()) {
            return false;
        }

        // Then validate JWT token
        $payload = self::validateJwtToken($this->staff_token);
        if (!$payload) {
            return false;
        }

        // Verify the token belongs to this staff member
        return $payload['staff_id'] == $this->id && $payload['email'] == $this->email;
    }

    /**
     * Revoke the current staff token
     *
     * @return void
     */
    public function revokeStaffToken(): void
    {
        $this->update([
            'staff_token' => null,
            'token_expires_at' => null,
        ]);
    }

    /**
     * Find staff by email
     *
     * @param string $email
     * @return Staff|null
     */
    public static function findByEmail(string $email): ?Staff
    {
        return static::where('email', $email)->first();
    }

    /**
     * Find staff by username
     *
     * @param string $username
     * @return Staff|null
     */
    public static function findByUsername(string $username): ?Staff
    {
        return static::where('username', $username)->first();
    }
}
