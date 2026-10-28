<?php

namespace App\Http\Controllers;

use App\Models\PaymentNotification;
use App\Models\PaymentCallback;
use App\Models\RenewalPaymentCallback;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Subscriber;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function __construct()
    {
        
    }

    public function initiatePayment(Request $request)
    {
        $date = date("YmdHis");
        $allBoxTypes = config('boxes.types');
        
        // Filter out hidden boxes for validation
        $visibleBoxTypes = [];
        foreach ($allBoxTypes as $id => $box) {
            if (!isset($box['hidden']) || $box['hidden'] !== true) {
                $visibleBoxTypes[] = $id;
            }
        }
        
        $request->validate([
            'msisdn' => 'required|string',
            'box_type' => 'required|string|in:' . implode(',', $visibleBoxTypes),
            'payment_type' => 'required|string',
            'channel' => 'required|string|in:APP,SMS',
        ]);

        // Update or create subscriber record
        Subscriber::findOrCreate($request->msisdn, [
            'sub_channel' => $request->channel
        ]);

        $boxTypes = config('boxes.types');

        if (!isset($boxTypes[$request->input('box_type')])) {
            return response()->json(['error' => config('messages.payment.error.invalid_box')], 400);
        }

        $box = $boxTypes[$request->input('box_type')];
        // $requestId = \Illuminate\Support\Str::uuid()->toString();

        $msisdn = $request->input('msisdn');
        $requestId = "MYBXI".$date.$msisdn;

        $boxAmount = $box['amount'];

        // if($msisdn == "255676589824" || $msisdn == "255656692469"){
        //     $boxAmount = 10;
        // }

        $payment = PaymentNotification::create([
            'request_id' => $requestId,
            'msisdn' => $msisdn,
            'amount' => $boxAmount,
            'box_type' => $box['name'],
            'payment_type' => $request->input('payment_type'),
            'publish_status' => false,
            'channel' => $request->input('channel'),
            'box_id' => $request->input('box_id')
        ]);

        return response()->json(['message' => config('messages.payment.success.initiate'), 'payment_id' => $payment->request_id]);
    }

    public function paymentCallback(Request $request)
    {
        try {
            $data = $request->all();
            
            Log::info('Received payment callback', $data);

            // Validate required fields
            if (!isset($data['requestId']) || !isset($data['requestParam']['data'])) {
                return response()->json([
                    'error' => 'Invalid callback data',
                    'details' => [
                        'requestId_exists' => isset($data['requestId']),
                        'requestParam_exists' => isset($data['requestParam']),
                        'data_exists' => isset($data['requestParam']['data']),
                        'raw_data' => $data
                    ]
                ], 400);
            }

            // Extract request parameters
            $requestParams = collect($data['requestParam']['data'])->mapWithKeys(function ($item) {
                return [strtolower($item['name']) => $item['value']];
            });

            // Validate required parameters
            $requiredParams = ['msisdn', 'chargeamount', 'channel'];
            $missingParams = array_filter($requiredParams, function($param) use ($requestParams) {
                return !isset($requestParams[strtolower($param)]);
            });

            if (!empty($missingParams)) {
                return response()->json([
                    'error' => 'Missing required parameters',
                    'details' => [
                        'missing_params' => $missingParams,
                        'received_params' => $requestParams->keys()->toArray(),
                        'raw_params' => collect($data['requestParam']['data'])->pluck('name')->toArray()
                    ]
                ], 400);
            }

            // Create callback record
            $amount = $requestParams['chargeamount'] ?? 'null';
            $amount = ($amount === 'null' || $amount === null) ? 0 : floatval($amount);
            
            // Check if Reason parameter exists and has a value - if so, treat as failed
            $reason = $requestParams['reason'] ?? null;
            $hasReason = !empty($reason);
            
            // Determine success: failed if Reason exists OR amount is 0
            $isSuccess = !$hasReason && $amount !== 0;
            
            // Set details based on Reason or amount
            if ($hasReason) {
                $details = $reason; // Use the Reason value as failure reason
            } else {
                $details = $isSuccess ? 
                    'Payment successfully' : 
                    'Payment failed';
            }

            $billingsystem = $requestParams['billingsystem'] ?? null;

            // Check request_id prefix and save to appropriate table
            if (str_starts_with($data['requestId'], 'MYBXI')) {
                // Save to PaymentCallback table
                $callback = PaymentCallback::create([
                    'msisdn' => $requestParams['msisdn'],
                    'request_id' => $data['requestId'],
                    'amount' => $amount,
                    'channel' => $requestParams['channel'],
                    'billing_system' => $billingsystem,
                    'status' => $isSuccess ? 'success' : 'failed',
                    'details' => $details,
                    'publish_status' => false
                ]);
            } elseif (str_starts_with($data['requestId'], 'MYBXR')) {
                // Save to RenewalPaymentCallback table
                $callback = RenewalPaymentCallback::create([
                    'msisdn' => $requestParams['msisdn'],
                    'request_id' => $data['requestId'],
                    'amount' => $amount,
                    'channel' => $requestParams['channel'],
                    'status' => $isSuccess ? 'success' : 'failed',
                    'details' => $details,
                    'publish_status' => false
                ]);
            } else {
                // Default to PaymentCallback table for other prefixes
                $callback = PaymentCallback::create([
                    'msisdn' => $requestParams['msisdn'],
                    'request_id' => $data['requestId'],
                    'amount' => $amount,
                    'channel' => $requestParams['channel'],
                    'billing_system' => $billingsystem,
                    'status' => $isSuccess ? 'success' : 'failed',
                    'details' => $details,
                    'publish_status' => false
                ]);
            }

            //Log::info('Created callback record', [
                //'callback_id' => $callback->id,
                //'status' => $callback->status,
                //'msisdn' => $callback->msisdn
            //]);

            // Prepare response data
            $responseData = [];
            $responseData['requestId'] = $data['requestId'];
            $responseData['responseId'] = 'GreenTelecom' . date('YmdHis') . mt_rand(1000, 9999);
            $responseData['responseTimeStamp'] = date('YmdHis');
            $responseData['operation'] = 'CP_NOTIFICATION';
            $responseData['responseParam']['status'] = "0";
            $responseData['responseParam']['statusCode'] = "0000";
            $responseData['responseParam']['description'] = "Success";

            return response()->json($responseData);
        } catch (\Exception $e) {
            //Log::error('Error processing payment callback: ' . $e->getMessage(), [
                //'error' => $e->getMessage(),
                //'request' => $request->all()
            //]);

            // Return error response in the same format
            $responseData = [];
            $responseData['requestId'] = $data['requestId'] ?? '';
            $responseData['responseId'] = 'GreenTelecom' . date('YmdHis') . mt_rand(1000, 9999);
            $responseData['responseTimeStamp'] = date('YmdHis');
            $responseData['operation'] = 'CP_NOTIFICATION';
            $responseData['responseParam']['status'] = "1";
            $responseData['responseParam']['statusCode'] = "9999";
            // $responseData['responseParam']['description'] = "Internal server error";
            $responseData['responseParam']['description'] = $e->getMessage();

            return response()->json($responseData, 500);
        }
    }

    /**
     * Get payments by MSISDN with date filtering
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getPaymentsByMsisdn(Request $request)
    {
        try {
            // Validate the request
            $request->validate([
                'msisdn' => 'required|string|regex:/^[0-9]+$/|min:10|max:15',
                'from' => 'required|string',
                'to' => 'required|string',
            ]);

            $msisdn = $request->input('msisdn');
            $from = $request->input('from');
            $to = $request->input('to');

            Log::info('Get payments by MSISDN request received', [
                'msisdn' => $msisdn,
                'from' => $from,
                'to' => $to,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            $allPayments = collect();

            // If "all" is specified, get payments from all available tables
            if ($from === 'all' && $to === 'all') {
                // Get current date payments
                $currentPayments = Payment::where('msisdn', $msisdn)->get();
                $allPayments = $allPayments->merge($currentPayments);

                // Get historical payments from date-specific tables
                // Check for available historical tables (no arbitrary limit)
                $currentDate = now();
                $maxDaysToCheck = config('payment.max_historical_days', 365); // Configurable, default 1 year
                
                for ($i = 1; $i <= $maxDaysToCheck; $i++) {
                    $date = $currentDate->copy()->subDays($i);
                    $tableName = 'payments_' . $date->format('Ymd');
                    
                    if (Schema::hasTable($tableName)) {
                        try {
                            $historicalPayments = DB::table($tableName)
                                ->where('msisdn', $msisdn)
                                ->get();
                            $allPayments = $allPayments->merge($historicalPayments);
                            
                            Log::info("Found historical payments in table {$tableName}", [
                                'count' => $historicalPayments->count(),
                                'date' => $date->format('Y-m-d')
                            ]);
                        } catch (\Exception $e) {
                            Log::warning("Could not query table {$tableName}: " . $e->getMessage());
                        }
                    } else {
                        // If we haven't found any tables for several consecutive days, 
                        // we might have reached the end of historical data
                        if ($i > 10 && $allPayments->count() === $currentPayments->count()) {
                            Log::info("No more historical payment tables found after {$i} days, stopping search");
                            break;
                        }
                    }
                }
            } else {
                // Validate date format
                if (!strtotime($from) || !strtotime($to)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invalid date format. Use YYYY-MM-DD or "all"'
                    ], 422);
                }

                $fromDate = \Carbon\Carbon::parse($from);
                $toDate = \Carbon\Carbon::parse($to);
                $currentDate = now();

                // Iterate through each date in the range
                for ($date = $fromDate->copy(); $date->lte($toDate); $date->addDay()) {
                    if ($date->isSameDay($currentDate)) {
                        // Current date - use main payments table
                        $dayPayments = Payment::where('msisdn', $msisdn)
                            ->whereDate('created_at', $date->format('Y-m-d'))
                            ->get();
                        $allPayments = $allPayments->merge($dayPayments);
                    } else {
                        // Historical date - use date-specific table
                        $tableName = 'payments_' . $date->format('Ymd');
                        
                        if (Schema::hasTable($tableName)) {
                            try {
                                $dayPayments = DB::table($tableName)
                                    ->where('msisdn', $msisdn)
                                    ->get();
                                $allPayments = $allPayments->merge($dayPayments);
                            } catch (\Exception $e) {
                                Log::warning("Could not query table {$tableName}: " . $e->getMessage());
                            }
                        }
                    }
                }
            }

            // Sort all payments by created_at (newest first)
            $sortedPayments = $allPayments->sortByDesc('created_at')->values();

            Log::info('Payments retrieved for subscriber', [
                'msisdn' => $msisdn,
                'payment_count' => $sortedPayments->count(),
                'from' => $from,
                'to' => $to
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Payments retrieved successfully',
                'data' => [
                    'msisdn' => $msisdn,
                    'total_records' => $sortedPayments->count(),
                    'date_filter' => [
                        'from' => $from,
                        'to' => $to
                    ],
                    'payments' => $sortedPayments->map(function ($payment) {
                        return [
                            'id' => $payment->id,
                            'request_id' => $payment->request_id,
                            'msisdn' => $payment->msisdn,
                            'amount' => $payment->amount,
                            'box_type' => $payment->box_type,
                            'box_id' => $payment->box_id,
                            'status' => $payment->status,
                            'payment_type' => $payment->payment_type,
                            'channel' => $payment->channel,
                            'created_at' => $payment->created_at,
                            'updated_at' => $payment->updated_at
                        ];
                    })
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get payments by MSISDN error', [
                'msisdn' => $request->input('msisdn'),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve payments'
            ], 500);
        }
    }
}
