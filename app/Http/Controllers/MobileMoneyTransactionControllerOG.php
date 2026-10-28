<?php

namespace App\Http\Controllers;

use App\Models\MobileMoneyTransactionNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;

class MobileMoneyTransactionController extends Controller
{
    /**
     * Rate limit configuration for mobile money notifications
     */
    private const RATE_LIMIT_KEY = 'mobile_money_notifications';

    /**
     * Receive mobile money transaction notification
     */
    public function receiveNotification(Request $request)
    {
        // Apply rate limiting
        $rateLimitResponse = $this->applyRateLimit($request);
        if ($rateLimitResponse !== null) {
            return $rateLimitResponse;
        }

        try {
            Log::info('Mobile money transaction notification received', [
                'ip' => $request->ip(),
                'headers' => $request->headers->all(),
                'data' => $request->all()
            ]);

            // Validate required fields
            $data = $request->validate([
                'SourceCustomerMobile' => 'required|string',
                'BrandName' => 'required|string',
                'OriginalAmount' => 'required|numeric|min:0',
                'SalesOrderNumber' => 'required|string',
                'SalesOrderStatus' => 'required|string',
                'TransactionDate' => 'required',
            ]);

            // Process TransactionDate - handle both ISO datetime format and numeric timestamp
            $transactionDateTime = $data['TransactionDate'];
            
            // Check if it's already in ISO datetime format (Y-m-d H:i:s)
            $isValidIsoFormat = false;
            try {
                $testDate = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $transactionDateTime);
                $isValidIsoFormat = $testDate && $testDate->format('Y-m-d H:i:s') === $transactionDateTime;
            } catch (\Exception $e) {
                $isValidIsoFormat = false;
            }
            
            if (!$isValidIsoFormat) {
                // Not in ISO format, try to convert from numeric timestamp
                if (is_numeric($transactionDateTime)) {
                    try {
                        // Convert timestamp from milliseconds to ISO datetime format
                        $timestamp = $transactionDateTime / 1000;
                        
                        // Validate timestamp is reasonable (between 1970 and 2100)
                        if ($timestamp < 0 || $timestamp > 4102444800) {
                            throw new \Exception('Timestamp out of reasonable range');
                        }
                        
                        $transactionDateTime = \Carbon\Carbon::createFromTimestamp($timestamp)->format('Y-m-d H:i:s');
                        
                        // Double-check the conversion
                        if (!\Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $transactionDateTime)) {
                            throw new \Exception('Invalid timestamp conversion result');
                        }
                        
                    } catch (\Exception $e) {
                        Log::warning('Failed to convert numeric timestamp', [
                            'original_timestamp' => $data['TransactionDate'],
                            'error' => $e->getMessage(),
                            'ip' => $request->ip()
                        ]);
                        $transactionDateTime = now()->format('Y-m-d H:i:s');
                    }
                } else {
                    // Try to parse as a general date format
                    try {
                        $transactionDateTime = \Carbon\Carbon::parse($data['TransactionDate'])->format('Y-m-d H:i:s');
                    } catch (\Exception $e) {
                        Log::warning('Failed to parse transaction date', [
                            'original_value' => $data['TransactionDate'],
                            'error' => $e->getMessage(),
                            'ip' => $request->ip()
                        ]);
                        $transactionDateTime = now()->format('Y-m-d H:i:s');
                    }
                }
            }
            
            // Final validation of the datetime format
            if (!$transactionDateTime || $transactionDateTime === '1970-01-01 00:00:00' || !\Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $transactionDateTime)) {
                Log::warning('Invalid transaction date conversion - using current time', [
                    'original_value' => $data['TransactionDate'],
                    'converted_datetime' => $transactionDateTime,
                    'ip' => $request->ip()
                ]);
                $transactionDateTime = now()->format('Y-m-d H:i:s');
            }

            // Create the notification record with mapped fields
            MobileMoneyTransactionNotification::create([
                'msisdn' => $data['SourceCustomerMobile'],
                'txn_type' => $data['BrandName'],
                'amount' => $data['OriginalAmount'],
                'reference' => $data['SalesOrderNumber'],
                'status' => $data['SalesOrderStatus'],
                'received_at' => $transactionDateTime,
                'publish_status' => false,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Mobile money transaction notification received'
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Mobile money transaction notification validation failed', [
                'errors' => $e->errors(),
                'data' => $request->all()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error processing mobile money transaction notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->all()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process notification'
            ], 500);
        }
    }

    /**
     * Apply rate limiting to the request
     */
    private function applyRateLimit(Request $request)
    {
        $key = $this->getRateLimitKey($request);
        
        // Check if rate limit is exceeded
        $rateLimit = config('rate_limits.mobile_money_notifications.requests_per_second');
        if (RateLimiter::tooManyAttempts($key, $rateLimit)) {
            $retryAfter = RateLimiter::availableIn($key);
            
            Log::warning('Rate limit exceeded for mobile money notifications', [
                'ip' => $request->ip(),
                'key' => $key,
                'retry_after' => $retryAfter,
                'limit' => $rateLimit
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Too many mobile money transaction notifications. Please try again later.',
                'retry_after' => $retryAfter
            ], 429);
        }

        // Increment the rate limit counter
        RateLimiter::hit($key, 1); // 1 second window
        
        return null; // No rate limit exceeded
    }

    /**
     * Get the rate limit key for the request
     */
    private function getRateLimitKey(Request $request): string
    {
        // Use IP address as the primary identifier
        $ip = $request->ip();
        
        // You can customize this key based on your needs:
        // - Global limit: return self::RATE_LIMIT_KEY;
        // - Per-IP limit: return self::RATE_LIMIT_KEY . ':' . $ip;
        // - Per-user limit: return self::RATE_LIMIT_KEY . ':' . ($request->user()?->id ?? $ip);
        
        return self::RATE_LIMIT_KEY . ':' . $ip;
    }

    /**
     * Get current rate limit status
     */
    public function getRateLimitStatus(Request $request)
    {
        $key = $this->getRateLimitKey($request);
        $rateLimit = config('rate_limits.mobile_money_notifications.requests_per_second');
        $remaining = RateLimiter::remaining($key, $rateLimit);
        $retryAfter = RateLimiter::availableIn($key);
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'rate_limit' => $rateLimit,
                'remaining_attempts' => $remaining,
                'retry_after_seconds' => $retryAfter > 0 ? $retryAfter : 0,
                'reset_time' => $retryAfter > 0 ? now()->addSeconds($retryAfter)->toISOString() : null
            ]
        ]);
    }

    /**
     * Get notification by MSISDN
     */
    public function getByMsisdn($msisdn)
    {
        try {
            $notification = MobileMoneyTransactionNotification::byMsisdn($msisdn)->first();

            if (!$notification) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Notification not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $notification
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error retrieving notification by MSISDN', [
                'msisdn' => $msisdn,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve notification'
            ], 500);
        }
    }

    /**
     * Get notification by reference
     */
    public function getByReference($reference)
    {
        try {
            $notification = MobileMoneyTransactionNotification::byReference($reference)->first();

            if (!$notification) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Notification not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $notification
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error retrieving notification by reference', [
                'reference' => $reference,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve notification'
            ], 500);
        }
    }

    /**
     * Get recent notifications
     */
    public function getRecent($limit = 10)
    {
        try {
            $notifications = MobileMoneyTransactionNotification::latest()
                ->limit($limit)
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $notifications,
                'count' => $notifications->count()
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error retrieving recent notifications', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve notifications'
            ], 500);
        }
    }

    /**
     * Get notification statistics
     */
    public function getStatistics()
    {
        try {
            $stats = [
                'total_notifications' => MobileMoneyTransactionNotification::count(),
                'published_notifications' => MobileMoneyTransactionNotification::published()->count(),
                'unpublished_notifications' => MobileMoneyTransactionNotification::unpublished()->count(),
                'today_notifications' => MobileMoneyTransactionNotification::whereDate('created_at', today())->count(),
                'this_week_notifications' => MobileMoneyTransactionNotification::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                'this_month_notifications' => MobileMoneyTransactionNotification::whereMonth('created_at', now()->month)->count(),
            ];

            return response()->json([
                'status' => 'success',
                'data' => $stats
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error retrieving notification statistics', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve statistics'
            ], 500);
        }
    }
}
