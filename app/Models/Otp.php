<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class Otp extends Model
{
    protected $fillable = [
        'msisdn',
        'otp_code',
        'expires_at',
        'is_verified',
        'verified_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'is_verified' => 'boolean'
    ];

    /**
     * Generate a new OTP for the given MSISDN
     *
     * @param string $msisdn
     * @param int $expiryMinutes
     * @return Otp
     */
    public static function generateOtp(string $msisdn, int $expiryMinutes = 5): Otp
    {
        // Invalidate any existing unverified OTPs for this MSISDN
        self::where('msisdn', $msisdn)
            ->where('is_verified', false)
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()->subMinute()]);

        // Generate a 6-digit OTP
        $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Set expiry time
        $expiresAt = now()->addMinutes($expiryMinutes);

        // Create new OTP record
        $otp = self::create([
            'msisdn' => $msisdn,
            'otp_code' => $otpCode,
            'expires_at' => $expiresAt,
            'is_verified' => false
        ]);

        Log::info('OTP generated', [
            'msisdn' => $msisdn,
            'otp_id' => $otp->id,
            'expires_at' => $expiresAt
        ]);

        return $otp;
    }

    /**
     * Verify OTP for the given MSISDN
     *
     * @param string $msisdn
     * @param string $otpCode
     * @return bool
     */
    public static function verifyOtp(string $msisdn, string $otpCode): bool
    {
        $otp = self::where('msisdn', $msisdn)
            ->where('otp_code', $otpCode)
            ->where('is_verified', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otp) {
            Log::warning('OTP verification failed', [
                'msisdn' => $msisdn,
                'otp_code' => $otpCode,
                'reason' => 'Invalid or expired OTP'
            ]);
            return false;
        }

        // Mark OTP as verified
        $otp->update([
            'is_verified' => true,
            'verified_at' => now()
        ]);

        Log::info('OTP verified successfully', [
            'msisdn' => $msisdn,
            'otp_id' => $otp->id
        ]);

        return true;
    }

    /**
     * Check if there's a valid unverified OTP for the MSISDN
     *
     * @param string $msisdn
     * @return bool
     */
    public static function hasValidOtp(string $msisdn): bool
    {
        return self::where('msisdn', $msisdn)
            ->where('is_verified', false)
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * Get the latest valid OTP for the MSISDN
     *
     * @param string $msisdn
     * @return Otp|null
     */
    public static function getLatestValidOtp(string $msisdn): ?Otp
    {
        return self::where('msisdn', $msisdn)
            ->where('is_verified', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();
    }

    /**
     * Clean up expired OTPs
     *
     * @return int
     */
    public static function cleanupExpiredOtps(): int
    {
        $deleted = self::where('expires_at', '<', now())->delete();
        
        if ($deleted > 0) {
            Log::info('Expired OTPs cleaned up', ['count' => $deleted]);
        }
        
        return $deleted;
    }
} 