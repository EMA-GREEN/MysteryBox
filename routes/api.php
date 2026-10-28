<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\MysteryBoxController;
use App\Http\Controllers\SmsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OtpController;
use App\Http\Middleware\PaymentCallbackMiddleware;
use App\Http\Controllers\AppController;
use App\Http\Controllers\BoxStatusController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\MobileMoneyTransactionController;
use App\Http\Controllers\SubscriberController;
use App\Http\Controllers\MpushSubsInitNotificationController;
use App\Http\Controllers\YasFreeBoxController;

// Handle OPTIONS requests for CORS preflight
Route::options('{any}', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin, Access-Control-Request-Method, Access-Control-Request-Headers')
        ->header('Access-Control-Allow-Credentials', 'false')
        ->header('Access-Control-Max-Age', '86400');
})->where('any', '.*');

// Authentication routes (no middleware)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/auth/msisdn', [AuthController::class, 'authenticate']);
Route::post('/auth/validate-token', [AuthController::class, 'validateToken']);

// Staff authentication routes (no middleware)
Route::post('/staff/auth/login', [AuthController::class, 'staffLogin']);
Route::post('/staff/auth/validate-token', [AuthController::class, 'validateStaffToken']);
Route::post('/staff/auth/logout', [AuthController::class, 'staffLogout']);

// Legacy admin routes (backward compatibility) - redirect to staff routes
Route::post('/admin/auth/login', [AuthController::class, 'staffLogin']);
Route::post('/admin/auth/validate-token', [AuthController::class, 'validateStaffToken']);
Route::post('/admin/auth/logout', [AuthController::class, 'staffLogout']);

// OTP routes (no middleware)
Route::prefix('otp')->group(function () {
    Route::post('/send', [OtpController::class, 'sendOtp']);
    Route::post('/verify', [OtpController::class, 'verifyOtp']);
    Route::post('/status', [OtpController::class, 'getOtpStatus']);
});

// Mystery Box routes (no middleware)
Route::post('/checkpoint', [MysteryBoxController::class, 'checkpoint']);
Route::post('/check-box-eligibility', [MysteryBoxController::class, 'checkBoxEligibility']);
Route::post('/redeem-box-app', [MysteryBoxController::class, 'redeemBoxApp']);
Route::post('/boxes/msisdn', [MysteryBoxController::class, 'getBoxesByMsisdn']);
Route::post('/winnings/msisdn', [MysteryBoxController::class, 'getWinningsByMsisdn']);
Route::post('/airtime-disbursements/msisdn', [MysteryBoxController::class, 'getAirtimeDisbursementsByMsisdn']);
Route::post('/wallet-disbursements/msisdn', [MysteryBoxController::class, 'getWalletDisbursementsByMsisdn']);
Route::post('/mobile-money-transaction-notification', [MobileMoneyTransactionController::class, 'receiveNotification']);

// Subscriber management (details / unsubscribe / blacklist)
Route::post('/subscriber/details', [SubscriberController::class, 'details']);
Route::post('/subscriber/unsubscribe', [SubscriberController::class, 'unsubscribe']);
Route::post('/subscriber/blacklist', [SubscriberController::class, 'blacklist']);

// Mpush subscription init notifications
Route::post('/mpush/subs-init-notification', [MpushSubsInitNotificationController::class, 'store']);

// Yas free box (Mixx App / Yas App redirects)
Route::prefix('yas/free-box')->group(function () {
    Route::get('/status', [YasFreeBoxController::class, 'status']);
    Route::post('/status', [YasFreeBoxController::class, 'status']);
    Route::post('/open', [YasFreeBoxController::class, 'open']);
});

// Mobile Money Transaction routes (no middleware)
Route::prefix('mobile-money')->group(function () {
    Route::post('/notification', [MobileMoneyTransactionController::class, 'receiveNotification']);
    Route::get('/notification/conversation/{conversationId}', [MobileMoneyTransactionController::class, 'getByConversationId']);
    Route::get('/notification/reference/{externalReference}', [MobileMoneyTransactionController::class, 'getByExternalReference']);
    Route::get('/notification/order/{salesOrderNumber}', [MobileMoneyTransactionController::class, 'getBySalesOrderNumber']);
    Route::get('/notifications/recent/{limit?}', [MobileMoneyTransactionController::class, 'getRecent']);
    Route::get('/notifications/statistics', [MobileMoneyTransactionController::class, 'getStatistics']);
    Route::get('/rate-limit-status', [MobileMoneyTransactionController::class, 'getRateLimitStatus']);
});

// Protected routes
// Route::middleware('auth:sanctum')->group(function () {
    // Authentication routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Group all API routes under a common prefix for better organization
    Route::prefix('payments')->group(function () {
        Route::post('/initiate-payment', [PaymentController::class, 'initiatePayment']);
    });

    Route::prefix('mystery-box')->group(function () {
        Route::post('/receive-mystery-box-award-notification', [MysteryBoxController::class, 'receiveMysteryBoxAwardNotification']);
        Route::post('/open-box/{msisdn}/{box_id}', [MysteryBoxController::class, 'openMysteryBox']);
        Route::post('/pending', [MysteryBoxController::class, 'getPendingBoxes']);
    });

    // Default API response
    Route::get('/', function () {
        return response()->json(['message' => 'Welcome to the Mystery Box API'], 200);
    });

    // CORS test endpoint
    Route::get('/cors-test', function () {
        return response()->json([
            'message' => 'CORS is working!',
            'timestamp' => now()->toISOString(),
            'headers' => request()->headers->all()
        ], 200);
    });
// });

// Public routes (no authentication required)
Route::prefix('payments')->group(function () {
    Route::post('/payment-callback', [PaymentController::class, 'paymentCallback'])
        ->middleware(PaymentCallbackMiddleware::class);
});

Route::prefix('sms')->group(function () {
    Route::get('/receive-sms', [SmsController::class, 'receiveSms']);
    Route::get('/receive-mo', [SmsController::class, 'receiveMo']);
    Route::get('/receive-dlr', [SmsController::class, 'receiveDlr']);
});

// App routes
Route::prefix('app')->group(function () {
    Route::post('/notification', [AppController::class, 'receiveNotification']);
});

Route::post('/box/status', [MysteryBoxController::class, 'getStatus']);

// Payment routes (no middleware)
Route::post('/payments/msisdn', [PaymentController::class, 'getPaymentsByMsisdn']);

// Disbursement callback routes (no middleware)
Route::prefix('disbursement')->group(function () {
    Route::post('/airtime/callback', [App\Http\Controllers\DisbursementCallbackController::class, 'airtimeCallback']);
    Route::post('/wallet/callback', [App\Http\Controllers\DisbursementCallbackController::class, 'walletCallback']);
});

// Admin routes for reporting
Route::prefix('admin')->group(function () {
    Route::post('/jackpot-entries', [AdminController::class, 'getJackpotEntries']);
    Route::post('/physical-prizes', [AdminController::class, 'getPhysicalPrizes']);
    Route::post('/normal-winnings', [AdminController::class, 'getNormalWinnings']);
    Route::post('/dashboard/stats', [AdminController::class, 'getDashboardStats']);
    Route::post('/wallet-disbursements', [AdminController::class, 'getWalletDisbursements']);
    Route::post('/airtime-disbursements', [AdminController::class, 'getAirtimeDisbursements']);
});

/*Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');*/
