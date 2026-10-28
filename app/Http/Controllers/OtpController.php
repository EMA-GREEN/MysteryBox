<?php

namespace App\Http\Controllers;

use App\Models\Otp;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OtpController extends Controller
{
    protected $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Send OTP to the provided MSISDN
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sendOtp(Request $request): JsonResponse
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'msisdn' => 'required|string|regex:/^[0-9]+$/|min:10|max:15',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => config('messages.otp.error.validation_failed'),
                    'errors' => $validator->errors()
                ], 422);
            }

            $msisdn = $request->input('msisdn');

            Log::info('OTP send request received', [
                'msisdn' => $msisdn,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // Check if there's already a valid OTP for this MSISDN
            if (Otp::hasValidOtp($msisdn)) {
                $existingOtp = Otp::getLatestValidOtp($msisdn);
                $remainingTime = now()->diffInSeconds($existingOtp->expires_at);
                
                if ($remainingTime > 60) { // More than 1 minute remaining
                    return response()->json([
                        'status' => 'error',
                        'message' => config('messages.otp.error.rate_limited'),
                        'data' => [
                            'remaining_seconds' => $remainingTime
                        ]
                    ], 429);
                }
            }

            // Generate new OTP
            $otp = Otp::generateOtp($msisdn, 5); // 5 minutes expiry

            // Send SMS with OTP using configurable message
            $message = str_replace(
                ['{otp_code}', '{expiry_minutes}'],
                [$otp->otp_code, 5],
                config('messages.otp.sms_message')
            );
            
            $smsSent = $this->smsService->sendSms($msisdn, $message);

            if (!$smsSent) {
                Log::error('Failed to send SMS for OTP', [
                    'msisdn' => $msisdn,
                    'otp_id' => $otp->id
                ]);

                // Even if SMS fails, we still return the OTP for testing purposes
                // In production, you might want to handle this differently
                return response()->json([
                    'status' => 'warning',
                    'message' => config('messages.otp.success.sms_failed'),
                    'data' => [
                        'msisdn' => $msisdn,
                        'otp_code' => $otp->otp_code,
                        'expires_at' => $otp->expires_at->toISOString(),
                        'expires_in_minutes' => 5,
                        'sms_sent' => false
                    ]
                ], 200);
            }

            Log::info('OTP sent successfully via SMS', [
                'msisdn' => $msisdn,
                'otp_id' => $otp->id,
                'expires_at' => $otp->expires_at,
                'sms_sent' => true
            ]);

            return response()->json([
                'status' => 'success',
                'message' => config('messages.otp.success.sent'),
                'data' => [
                    'msisdn' => $msisdn,
                    'otp_code' => $otp->otp_code,
                    'expires_at' => $otp->expires_at->toISOString(),
                    'expires_in_minutes' => 5,
                    'sms_sent' => true
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('OTP send error', [
                'msisdn' => $request->input('msisdn'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => config('messages.otp.error.send_failed')
            ], 500);
        }
    }

    /**
     * Verify OTP for the provided MSISDN
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'msisdn' => 'required|string|regex:/^[0-9]+$/|min:10|max:15',
                'otp' => 'required|string|size:6|regex:/^[0-9]+$/',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => config('messages.otp.error.validation_failed'),
                    'errors' => $validator->errors()
                ], 422);
            }

            $msisdn = $request->input('msisdn');
            $otpCode = $request->input('otp');

            Log::info('OTP verification request received', [
                'msisdn' => $msisdn,
                'otp_code' => $otpCode,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // Verify OTP
            $isVerified = Otp::verifyOtp($msisdn, $otpCode);

            if ($isVerified) {
                Log::info('OTP verification successful', [
                    'msisdn' => $msisdn
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => config('messages.otp.success.verified'),
                    'data' => [
                        'msisdn' => $msisdn,
                        'is_verified' => true,
                        'verified_at' => now()->toISOString()
                    ]
                ], 200);
            } else {
                Log::warning('OTP verification failed', [
                    'msisdn' => $msisdn,
                    'otp_code' => $otpCode
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => config('messages.otp.error.invalid'),
                    'data' => [
                        'msisdn' => $msisdn,
                        'is_verified' => false
                    ]
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error('OTP verification error', [
                'msisdn' => $request->input('msisdn'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => config('messages.otp.error.verify_failed')
            ], 500);
        }
    }

    /**
     * Get OTP status for the provided MSISDN
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getOtpStatus(Request $request): JsonResponse
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'msisdn' => 'required|string|regex:/^[0-9]+$/|min:10|max:15',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => config('messages.otp.error.validation_failed'),
                    'errors' => $validator->errors()
                ], 422);
            }

            $msisdn = $request->input('msisdn');

            // Check if there's a valid OTP
            $hasValidOtp = Otp::hasValidOtp($msisdn);
            $otpData = null;

            if ($hasValidOtp) {
                $otp = Otp::getLatestValidOtp($msisdn);
                $otpData = [
                    'expires_at' => $otp->expires_at->toISOString(),
                    'remaining_seconds' => now()->diffInSeconds($otp->expires_at),
                    'remaining_minutes' => now()->diffInMinutes($otp->expires_at)
                ];
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'msisdn' => $msisdn,
                    'has_valid_otp' => $hasValidOtp,
                    'otp_data' => $otpData
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('OTP status check error', [
                'msisdn' => $request->input('msisdn'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => config('messages.otp.error.status_check_failed')
            ], 500);
        }
    }
} 