<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WinnerJackpotEntry;
use App\Models\WinnerPhysicalPrize;
use App\Models\Winner;
use App\Models\WinnerAirtimeDisbursement;
use App\Models\WinnerWalletDisbursement;
use App\Models\Subscriber;
use App\Models\AirtimeDisbursementCallback;
use App\Models\WalletDisbursementCallback;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminController extends Controller
{
    /**
     * Get filtered jackpot entries
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getJackpotEntries(Request $request)
    {
        // Validate request parameters
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:pending,completed,cancelled,all',
            'draw_type' => 'required|string|in:daily,weekly,monthly,grand,all',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $query = WinnerJackpotEntry::query();

            // Filter by status
            $status = $request->input('status');
            if ($status !== 'all') {
                $query->where('status', $status);
            }

            // Filter by draw type
            $drawType = $request->input('draw_type');
            if ($drawType !== 'all') {
                $query->where('draw_period', $drawType);
            }

            // Filter by date range
            $fromDate = $request->input('from_date');
            $toDate = $request->input('to_date');

            $query->where('entry_date', '>=', Carbon::parse($fromDate)->startOfDay());
            $query->where('entry_date', '<=', Carbon::parse($toDate)->endOfDay());

            // Get results with pagination
            $entries = $query->orderBy('entry_date', 'desc')
                           ->paginate(50);

            return response()->json([
                'success' => true,
                'message' => 'Jackpot entries retrieved successfully',
                'data' => $entries->items(),
                'pagination' => [
                    'current_page' => $entries->currentPage(),
                    'last_page' => $entries->lastPage(),
                    'per_page' => $entries->perPage(),
                    'total' => $entries->total(),
                    'from' => $entries->firstItem(),
                    'to' => $entries->lastItem(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving jackpot entries',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get filtered physical prizes
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPhysicalPrizes(Request $request)
    {
        // Validate request parameters
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:pending,shipped,delivered,cancelled,all',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $query = WinnerPhysicalPrize::query();

            // Filter by status
            $status = $request->input('status');
            if ($status !== 'all') {
                $query->where('status', $status);
            }

            // Filter by date range
            $fromDate = $request->input('from_date');
            $toDate = $request->input('to_date');

            $query->where('created_at', '>=', Carbon::parse($fromDate)->startOfDay());
            $query->where('created_at', '<=', Carbon::parse($toDate)->endOfDay());

            // Get results with pagination
            $prizes = $query->orderBy('created_at', 'desc')
                          ->paginate(50);

            return response()->json([
                'success' => true,
                'message' => 'Physical prizes retrieved successfully',
                'data' => $prizes->items(),
                'pagination' => [
                    'current_page' => $prizes->currentPage(),
                    'last_page' => $prizes->lastPage(),
                    'per_page' => $prizes->perPage(),
                    'total' => $prizes->total(),
                    'from' => $prizes->firstItem(),
                    'to' => $prizes->lastItem(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving physical prizes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get filtered normal winnings (airtime or wallet disbursements)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getNormalWinnings(Request $request)
    {
        // Validate request parameters
        $validator = Validator::make($request->all(), [
            'status' => 'required|string',
            'prize_type' => 'required|string|in:airtime,wallet,all',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $prizeType = $request->input('prize_type');
            $status = $request->input('status');
            $fromDate = Carbon::parse($request->input('from_date'))->startOfDay();
            $toDate = Carbon::parse($request->input('to_date'))->endOfDay();

            // Handle prize_type = "all" by aggregating both airtime and wallet winnings
            if ($prizeType === 'all') {
                // Base queries
                $airtimeQuery = WinnerAirtimeDisbursement::query();
                $walletQuery = WinnerWalletDisbursement::query();

                // Filter by callback_status (status field)
                if ($status !== 'all') {
                    $airtimeQuery->where('callback_status', $status);
                    $walletQuery->where('callback_status', $status);
                }

                // Filter by date range
                $airtimeQuery->whereBetween('created_at', [$fromDate, $toDate]);
                $walletQuery->whereBetween('created_at', [$fromDate, $toDate]);

                // Get all results (we'll paginate manually after merging)
                $airtimeWinnings = $airtimeQuery->orderBy('created_at', 'desc')->get();
                $walletWinnings = $walletQuery->orderBy('created_at', 'desc')->get();

                // Map to unified structure
                $airtimeData = $airtimeWinnings->map(function ($winning) {
                    return [
                        'id' => $winning->id,
                        'winner_id' => $winning->winner_id,
                        'msisdn' => $winning->msisdn,
                        'box_id' => $winning->box_id,
                        'box_type' => $winning->box_type,
                        'amount' => (float) $winning->amount,
                        'prize_type' => 'airtime',
                        'status' => $winning->callback_status,
                        'status_description' => $winning->callback_message,
                        'created_at' => $winning->created_at ? $winning->created_at->toISOString() : null,
                        'updated_at' => $winning->updated_at ? $winning->updated_at->toISOString() : null,
                    ];
                });

                $walletData = $walletWinnings->map(function ($winning) {
                    $data = [
                        'id' => $winning->id,
                        'winner_id' => $winning->winner_id,
                        'msisdn' => $winning->msisdn,
                        'box_id' => $winning->box_id,
                        'box_type' => $winning->box_type,
                        'amount' => (float) $winning->amount,
                        'prize_type' => 'wallet',
                        'status' => $winning->callback_status,
                        'status_description' => $winning->callback_message,
                        'created_at' => $winning->created_at ? $winning->created_at->toISOString() : null,
                        'updated_at' => $winning->updated_at ? $winning->updated_at->toISOString() : null,
                    ];

                    if (isset($winning->payout_amount)) {
                        $data['payout_amount'] = (float) $winning->payout_amount;
                        $data['tax_percent'] = (float) $winning->tax_percent;
                    }

                    return $data;
                });

                // Merge and sort by created_at desc
                $allData = $airtimeData->concat($walletData)->sortByDesc('created_at')->values();

                // Manual pagination
                $perPage = 25;
                $currentPage = max(1, (int) $request->input('page', 1));
                $total = $allData->count();
                $offset = ($currentPage - 1) * $perPage;
                $paginatedData = $allData->slice($offset, $perPage)->values();

                $lastPage = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
                $from = $total === 0 ? null : $offset + 1;
                $to = $total === 0 ? null : min($offset + $perPage, $total);

                return response()->json([
                    'success' => true,
                    'message' => 'Normal winnings retrieved successfully',
                    'data' => $paginatedData,
                    'pagination' => [
                        'current_page' => $currentPage,
                        'last_page' => $lastPage,
                        'per_page' => $perPage,
                        'total' => $total,
                        'from' => $from,
                        'to' => $to,
                    ]
                ]);
            }

            // Select the appropriate model based on prize_type
            if ($prizeType === 'airtime') {
                $query = WinnerAirtimeDisbursement::query();
            } else {
                $query = WinnerWalletDisbursement::query();
            }

            // Filter by callback_status (status field)
            if ($status !== 'all') {
                $query->where('callback_status', $status);
            }

            // Filter by date range
            $query->whereBetween('created_at', [$fromDate, $toDate]);

            // Get results with pagination
            $winnings = $query->orderBy('created_at', 'desc')
                              ->paginate(25);

            // Format the response data
            $formattedData = $winnings->map(function ($winning) use ($prizeType) {
                $data = [
                    'id' => $winning->id,
                    'winner_id' => $winning->winner_id,
                    'msisdn' => $winning->msisdn,
                    'box_id' => $winning->box_id,
                    'box_type' => $winning->box_type,
                    'amount' => (float) $winning->amount,
                    'prize_type' => $prizeType,
                    'status' => $winning->callback_status,
                    'status_description' => $winning->callback_message,
                    'created_at' => $winning->created_at ? $winning->created_at->toISOString() : null,
                    'updated_at' => $winning->updated_at ? $winning->updated_at->toISOString() : null,
                ];

                // Add wallet-specific fields
                if ($prizeType === 'wallet' && isset($winning->payout_amount)) {
                    $data['payout_amount'] = (float) $winning->payout_amount;
                    $data['tax_percent'] = (float) $winning->tax_percent;
                }

                return $data;
            });

            return response()->json([
                'success' => true,
                'message' => 'Normal winnings retrieved successfully',
                'data' => $formattedData->values()->all(),
                'pagination' => [
                    'current_page' => $winnings->currentPage(),
                    'last_page' => $winnings->lastPage(),
                    'per_page' => $winnings->perPage(),
                    'total' => $winnings->total(),
                    'from' => $winnings->firstItem(),
                    'to' => $winnings->lastItem(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving normal winnings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get dashboard statistics
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDashboardStats(Request $request)
    {
        // existing implementation unchanged
        $validator = Validator::make($request->all(), [
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $fromDate = Carbon::parse($request->input('from_date'))->startOfDay();
            $toDate = Carbon::parse($request->input('to_date'))->endOfDay();

            $totalSubs = Subscriber::where('record_date', '>=', $fromDate)
                                   ->where('record_date', '<=', $toDate)
                                   ->count();

            $totalActiveSubs = Subscriber::where('activity_status', 'ACTIVE')
                                         ->where('record_date', '>=', $fromDate)
                                         ->where('record_date', '<=', $toDate)
                                         ->count();

            $totalGivenAirtime = WinnerAirtimeDisbursement::where('created_at', '>=', $fromDate)
                                                          ->where('created_at', '<=', $toDate)
                                                          ->where('callback_status', 'COMPLETED')
                                                          ->sum('amount');

            $totalGivenCashWallet = WinnerWalletDisbursement::where('created_at', '>=', $fromDate)
                                                            ->where('created_at', '<=', $toDate)
                                                            ->where('callback_status', 'COMPLETED')
                                                            ->sum('payout_amount');

            $latestAirtimeCallback = AirtimeDisbursementCallback::orderBy('id', 'desc')->first();
            $totalAirtimeBalance = null;
            if ($latestAirtimeCallback && $latestAirtimeCallback->raw_response) {
                $rawResponse = is_string($latestAirtimeCallback->raw_response)
                    ? json_decode($latestAirtimeCallback->raw_response, true)
                    : $latestAirtimeCallback->raw_response;
                $totalAirtimeBalance = isset($rawResponse['available_balance'])
                    ? (float) $rawResponse['available_balance']
                    : null;
            }

            $latestWalletCallback = WalletDisbursementCallback::orderBy('id', 'desc')->first();
            $totalWalletBalance = null;
            if ($latestWalletCallback && $latestWalletCallback->raw_response) {
                $rawResponse = is_string($latestWalletCallback->raw_response)
                    ? json_decode($latestWalletCallback->raw_response, true)
                    : $latestWalletCallback->raw_response;
                $totalWalletBalance = isset($rawResponse['available_balance'])
                    ? (float) $rawResponse['available_balance']
                    : null;
            }

            return response()->json([
                'success' => true,
                'message' => 'Dashboard statistics retrieved successfully',
                'data' => [
                    'total_subs' => (int) $totalSubs,
                    'total_active_subs' => (int) $totalActiveSubs,
                    'total_given_airtime' => (float) $totalGivenAirtime,
                    'total_given_cash_wallet' => (float) $totalGivenCashWallet,
                    'total_airtime_balance' => $totalAirtimeBalance,
                    'total_wallet_balance' => $totalWalletBalance,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving dashboard statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get wallet disbursements filtered by date range and callback_status
     */
    public function getWalletDisbursements(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'status' => 'required|string', // callback_status or "all"
            'page' => 'required|integer|min:1',
            'per_page' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $status = $request->input('status');
            $fromDate = Carbon::parse($request->input('from_date'))->startOfDay();
            $toDate = Carbon::parse($request->input('to_date'))->endOfDay();
            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 10);

            $query = WinnerWalletDisbursement::query();

            if ($status !== 'all') {
                $query->where('callback_status', $status);
            }

            // Use created_at for date filtering (callback_received_time can be NULL)
            $query->whereBetween('created_at', [$fromDate, $toDate]);

            $disbursements = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'message' => 'Wallet disbursements retrieved successfully',
                'data' => $disbursements->items(),
                'pagination' => [
                    'current_page' => $disbursements->currentPage(),
                    'last_page' => $disbursements->lastPage(),
                    'per_page' => $disbursements->perPage(),
                    'total' => $disbursements->total(),
                    'from' => $disbursements->firstItem(),
                    'to' => $disbursements->lastItem(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving wallet disbursements',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get airtime disbursements filtered by date range and callback_status
     */
    public function getAirtimeDisbursements(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'status' => 'required|string', // callback_status or "all"
            'page' => 'required|integer|min:1',
            'per_page' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $status = $request->input('status');
            $fromDate = Carbon::parse($request->input('from_date'))->startOfDay();
            $toDate = Carbon::parse($request->input('to_date'))->endOfDay();
            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 10);

            $query = WinnerAirtimeDisbursement::query();

            if ($status !== 'all') {
                $query->where('callback_status', $status);
            }

            // Use created_at for date filtering (callback_received_time can be NULL)
            $query->whereBetween('created_at', [$fromDate, $toDate]);

            $disbursements = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'message' => 'Airtime disbursements retrieved successfully',
                'data' => $disbursements->items(),
                'pagination' => [
                    'current_page' => $disbursements->currentPage(),
                    'last_page' => $disbursements->lastPage(),
                    'per_page' => $disbursements->perPage(),
                    'total' => $disbursements->total(),
                    'from' => $disbursements->firstItem(),
                    'to' => $disbursements->lastItem(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving airtime disbursements',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
