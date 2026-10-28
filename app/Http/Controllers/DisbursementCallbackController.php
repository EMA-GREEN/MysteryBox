<?php

namespace App\Http\Controllers;

use App\Models\AirtimeDisbursementCallback;
use App\Models\WalletDisbursementCallback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DisbursementCallbackController extends Controller
{
    /**
     * Handle airtime disbursement callback
     */
    public function airtimeCallback(Request $request)
    {
        try {
            Log::info('Airtime disbursement callback received', [
                'ip' => $request->ip(),
                'data' => $request->all()
            ]);

            // Validate required fields
            $request->validate([
                'msisdn' => 'required|string',
                'amount' => 'required|numeric',
            ]);

            // Store callback data with default values for optional fields
            $callback = AirtimeDisbursementCallback::create([
                'reference_id' => $request->original_reference ?? null,
                'msisdn' => $request->msisdn,
                'amount' => $request->amount,
                'status' => $request->status ?? null,
                'message' => $request->message ?? null,
                'transaction_id' => $request->transaction_id ?? null,
                'balance' => $request->balance ?? null,
                'raw_response' => $request->all(),
                'callback_source' => $request->header('User-Agent') ?? null,
                'ip_address' => $request->ip(),
                'callback_received_at' => now(),
                'publish_status' => false
            ]);

            Log::info('Airtime disbursement callback stored successfully', [
                'callback_id' => $callback->id,
                'reference_id' => $request->original_reference,
                'status' => $request->status
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Callback received successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error processing airtime disbursement callback', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error processing callback'
            ], 500);
        }
    }

    /**
     * Handle wallet disbursement callback
     */
    public function walletCallback(Request $request)
    {
        try {
            Log::info('Wallet disbursement callback received', [
                'ip' => $request->ip(),
                'data' => $request->all()
            ]);

            // Validate required fields
            $request->validate([
                'msisdn' => 'required|string',
                'amount' => 'required|numeric',
            ]);

            // Store callback data with default values for optional fields
            $callback = WalletDisbursementCallback::create([
                'reference_id' => $request->original_reference ?? null,
                'msisdn' => $request->msisdn,
                'amount' => $request->amount,
                'status' => $request->status ?? null,
                'message' => $request->message ?? null,
                'transaction_id' => $request->transaction_id ?? null,
                'balance' => $request->balance ?? null,
                'raw_response' => $request->all(),
                'callback_source' => $request->header('User-Agent') ?? null,
                'ip_address' => $request->ip(),
                'callback_received_at' => now(),
                'publish_status' => false
            ]);

            Log::info('Wallet disbursement callback stored successfully', [
                'callback_id' => $callback->id,
                'reference_id' => $request->original_reference,
                'status' => $request->status
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Callback received successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error processing wallet disbursement callback', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error processing callback'
            ], 500);
        }
    }


}
