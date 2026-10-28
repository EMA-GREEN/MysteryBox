<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AppController extends Controller
{
    public function receiveNotification(Request $request)
    {
        try {
            $msisdn = $request->input('msisdn');
            $action = $request->input('action');
            $boxType = $request->input('box_type');
            $boxId = $request->input('box_id');

            if (!$msisdn || !$action) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'MSISDN and action are required'
                ], 400);
            }

            if (!in_array($action, [AppNotification::ACTION_PURCHASE, AppNotification::ACTION_OPEN])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid action'
                ], 400);
            }

            // Validate box_type and box_id based on action
            if ($action === AppNotification::ACTION_OPEN) {
                if (!$boxType || !$boxId) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Box type and box ID are required for OPEN action'
                    ], 400);
                }
            }

            AppNotification::create([
                'msisdn' => $msisdn,
                'action' => $action,
                'publish_status' => false,
                'notif_id' => Str::uuid()->toString(),
                'box_type' => $boxType,
                'box_id' => $boxId
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Incoming Request under process'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process request'
            ], 500);
        }
    }
} 