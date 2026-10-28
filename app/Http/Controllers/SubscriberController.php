<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use App\Models\RenewalSubscriber;
use App\Models\Unsubscriber;
use App\Services\MpushSubsManageService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SubscriberController extends Controller
{
    public function __construct(private MpushSubsManageService $mpushSubsManageService)
    {
    }

        /**
     * Get subscriber details by MSISDN (read-only).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function details(Request $request): JsonResponse
    {
        $request->validate([
            'msisdn' => 'required|string|regex:/^[0-9]+$/|min:10|max:15',
        ]);

        $msisdn = $request->input('msisdn');

        $subscriber = Subscriber::where('msisdn', $msisdn)->first();
        if (!$subscriber) {
            return response()->json([
                'status' => 'error',
                'message' => 'Subscriber not found',
            ], 404);
        }

        $renewalSubscriber = RenewalSubscriber::where('msisdn', $msisdn)->first();

        return response()->json([
            'status' => 'success',
            'message' => 'Subscriber details retrieved successfully',
            'data' => $this->subscriberDetails($subscriber, $renewalSubscriber),
        ], 200);
    }

    
    /**
     * Unsubscribe a subscriber by MSISDN (sets activity_status = INACTIVE).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        $request->validate([
            'msisdn' => 'required|string|regex:/^[0-9]+$/|min:10|max:15',
        ]);

        $msisdn = $request->input('msisdn');

        $subscriber = Subscriber::where('msisdn', $msisdn)->first();
        if (!$subscriber) {
            return response()->json([
                'status' => 'error',
                'message' => 'Subscriber not found',
            ], 404);
        }

        $subscriber->update(['activity_status' => 'INACTIVE']);
        $this->mpushSubsManageService->notifyUnsubscription($msisdn);

        $renewalSubscriber = RenewalSubscriber::where('msisdn', $msisdn)->first();
        if ($renewalSubscriber) {
            $renewalSubscriber->update(['activity_status' => 'INACTIVE']);
        }

        // Record unsubscription in unsubscribers table
        Unsubscriber::create([
            'msisdn' => (int) $msisdn,
            'channel' => Unsubscriber::CHANNEL_ADMIN,
            'mo_id' => null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Subscriber unsubscribed successfully',
            'data' => $this->subscriberDetails($subscriber->fresh(), $renewalSubscriber ? $renewalSubscriber->fresh() : null),
        ], 200);
    }


    /**
     * Blacklist a subscriber by MSISDN (sets activity_status = INACTIVE, dnd_status = ON).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function blacklist(Request $request): JsonResponse
    {
        $request->validate([
            'msisdn' => 'required|string|regex:/^[0-9]+$/|min:10|max:15',
        ]);

        $msisdn = $request->input('msisdn');

        $subscriber = Subscriber::where('msisdn', $msisdn)->first();
        if (!$subscriber) {
            return response()->json([
                'status' => 'error',
                'message' => 'Subscriber not found',
            ], 404);
        }

        $subscriber->update([
            'activity_status' => 'INACTIVE',
            'dnd_status' => 'ON',
        ]);
        $this->mpushSubsManageService->notifyUnsubscription($msisdn);

        $renewalSubscriber = RenewalSubscriber::where('msisdn', $msisdn)->first();
        if ($renewalSubscriber) {
            $renewalSubscriber->update([
                'activity_status' => 'INACTIVE',
                'dnd_status' => 'ON',
            ]);
        }

        // Record blacklist (unsubscription) in unsubscribers table
        Unsubscriber::create([
            'msisdn' => (int) $msisdn,
            'channel' => Unsubscriber::CHANNEL_ADMIN,
            'mo_id' => null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Subscriber blacklisted successfully',
            'data' => $this->subscriberDetails($subscriber->fresh(), $renewalSubscriber ? $renewalSubscriber->fresh() : null),
        ], 200);
    }

    /**
     * Build subscriber details array for JSON response.
     *
     * @param Subscriber $subscriber
     * @param RenewalSubscriber|null $renewalSubscriber
     * @return array
     */
    private function subscriberDetails(Subscriber $subscriber, ?RenewalSubscriber $renewalSubscriber): array
    {
        $data = [
            'msisdn' => $subscriber->msisdn,
            'activity_status' => $subscriber->activity_status,
            'dnd_status' => $subscriber->dnd_status,
            'customer_type' => $subscriber->customer_type,
            'sub_channel' => $subscriber->sub_channel,
            'last_interaction' => $subscriber->last_interaction ? $subscriber->last_interaction->format('c') : null,
            'loyalty_points' => $subscriber->loyalty_points,
            'record_date' => $subscriber->record_date ? $subscriber->record_date->format('c') : null,
        ];

        if ($renewalSubscriber) {
            $data['renewal_subscriber'] = [
                'msisdn' => $renewalSubscriber->msisdn,
                'activity_status' => $renewalSubscriber->activity_status,
                'dnd_status' => $renewalSubscriber->dnd_status,
                'priority' => $renewalSubscriber->priority,
                'retry_count' => $renewalSubscriber->retry_count,
                'publish_status' => $renewalSubscriber->publish_status,
            ];
        }

        return $data;
    }
}
