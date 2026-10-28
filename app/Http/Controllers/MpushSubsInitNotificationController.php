<?php

namespace App\Http\Controllers;

use App\Models\MpushSubsInitNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MpushSubsInitNotificationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'msisdn' => 'required|string|regex:/^[0-9]+$/|min:10|max:15',
                'channel' => 'required|string|in:SMS,USSD,APP',
                'ussd_data' => 'nullable|string',
            ]);

            $notification = MpushSubsInitNotification::create([
                'MSISDN' => (int) $validated['msisdn'],
                'CHANNEL' => $validated['channel'],
                'USSD_DATA' => $validated['ussd_data'] ?? null,
                'IS_OUTGOING' => 1,
            ]);

            Log::info('Mpush subs init notification stored', [
                'id' => $notification->ID,
                'msisdn' => $validated['msisdn'],
                'channel' => $validated['channel'],
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Notification stored successfully',
                'data' => [
                    'id' => $notification->ID,
                    'msisdn' => $notification->MSISDN,
                    'channel' => $notification->CHANNEL,
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to store mpush subs init notification: ' . $e->getMessage(), [
                'payload' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to store notification',
            ], 500);
        }
    }
}
