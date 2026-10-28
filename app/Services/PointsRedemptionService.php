<?php

namespace App\Services;

use App\Models\Subscriber;
use App\Models\MysteryBox;
use App\Models\SmsNotification;
use App\Http\Controllers\MysteryBoxController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PointsRedemptionService
{
    /**
     * Check if subscriber can redeem points and get available boxes
     */
    public function checkRedemptionEligibility($msisdn)
    {
        $subscriber = Subscriber::where('msisdn', $msisdn)->first();
        
        if (!$subscriber) {
            return [
                'eligible' => false,
                'message' => config('messages.sms.redeem.low_points'),
                'available_boxes' => []
            ];
        }

        $loyaltyPoints = $subscriber->loyalty_points;
        $availableBoxes = $this->getAvailableBoxesForPoints($loyaltyPoints);

        if (empty($availableBoxes)) {
            return [
                'eligible' => false,
                'message' => config('messages.sms.redeem.low_points'),
                'available_boxes' => []
            ];
        }

        return [
            'eligible' => true,
            'loyalty_points' => $loyaltyPoints,
            'available_boxes' => $availableBoxes
        ];
    }

    /**
     * Get available boxes based on loyalty points
     */
    private function getAvailableBoxesForPoints($loyaltyPoints)
    {
        $boxTypes = config('boxes.types');
        $availableBoxes = [];

        foreach ($boxTypes as $boxId => $box) {
            // Skip hidden boxes
            if (isset($box['hidden']) && $box['hidden'] === true) {
                continue;
            }
            
            if ($loyaltyPoints >= $box['redeem_points']) {
                $availableBoxes[] = [
                    'id' => $boxId,
                    'display_name' => $box['display_name'],
                    'redeem_points' => $box['redeem_points'],
                    'amount' => $box['amount']
                ];
            }
        }

        // Sort by redeem points (lowest first)
        usort($availableBoxes, function($a, $b) {
            return $a['redeem_points'] <=> $b['redeem_points'];
        });

        return $availableBoxes;
    }

    /**
     * Generate redemption message
     */
    public function generateRedemptionMessage($msisdn)
    {
        $eligibility = $this->checkRedemptionEligibility($msisdn);
        
        if (!$eligibility['eligible']) {
            return $eligibility['message'];
        }

        $header = config('messages.sms.redeem.header');
        $openText = config('messages.sms.redeem.open_text');
        $pointsText = config('messages.sms.redeem.points_text');
        $footer = config('messages.sms.redeem.footer');
        $loyaltyPoints = $eligibility['loyalty_points'];
        $availableBoxes = $eligibility['available_boxes'];

        $message = "{$header} {$loyaltyPoints}\n";
        
        foreach ($availableBoxes as $index => $box) {
            $boxNumber = $index + 1;
            $message .= "{$boxNumber} - {$openText} {$box['display_name']} {$pointsText} {$box['redeem_points']}\n";
        }
        
        $message .= $footer;

        return $message;
    }

    /**
     * Process box redemption
     */
    public function redeemBox($msisdn, $boxChoice, $source)
    {
        $subscriber = Subscriber::where('msisdn', $msisdn)->first();
        
        if (!$subscriber) {
            return [
                'success' => false,
                'message' => config('messages.sms.redeem.low_points')
            ];
        }

        // Validate box choice
        if (!isset(config('boxes.types')[$boxChoice])) {
            return [
                'success' => false,
                'message' => config('messages.sms.redeem.invalid_choice')
            ];
        }

        $boxType = config('boxes.types')[$boxChoice];
        $requiredPoints = $boxType['redeem_points'];
        $currentPoints = $subscriber->loyalty_points;

        if ($currentPoints < $requiredPoints) {
            return [
                'success' => false,
                'message' => config('messages.sms.redeem.low_points')
            ];
        }

        try {
            DB::beginTransaction();

            // Use loyalty points (this will deduct and track automatically)
            if (!$subscriber->useLoyaltyPoints($requiredPoints)) {
                throw new \Exception('Insufficient points');
            }

            // Generate request ID based on source
            $date = date('YmdHis');
            $prefix = ($source === 'SMS') ? 'MYBXI' : (($source === 'APP') ? 'MYBXA' : 'MYBXI');
            $requestId = $prefix . $date . $msisdn;

            // Create mystery box
            $mysteryBox = MysteryBox::create([
                'msisdn' => $msisdn,
                'box_type' => $boxType['name'], // Use box name instead of numerical choice
                'request_id' => $requestId,
                'amount' => $boxType['amount'], // Add the box amount
                'source' => MysteryBox::SOURCE_POINT_REDEEM
            ]);

            // Process the box opening using MysteryBoxController (get rewards, winnings, etc.)
            $mysteryBoxController = new MysteryBoxController();
            $openBoxResponse = $mysteryBoxController->openMysteryBox($msisdn, $mysteryBox->id);
            
            // Extract result_message from the response
            $resultMessage = null;
            if ($openBoxResponse && $openBoxResponse->getStatusCode() === 200) {
                $responseData = json_decode($openBoxResponse->getContent(), true);
                $resultMessage = $responseData['result_message'] ?? null;
            }

            DB::commit();

            Log::info('Box redeemed successfully', [
                'msisdn' => $msisdn,
                'box_type' => $boxChoice,
                'points_used' => $requiredPoints,
                'total_points_used' => $subscriber->points_used,
                'remaining_points' => $subscriber->loyalty_points,
                'result_message' => $resultMessage
            ]);

            return [
                'success' => true,
                'message' => config('messages.sms.redeem.success'),
                'box_id' => $mysteryBox->id,
                'request_id' => $requestId,
                'remaining_points' => $subscriber->loyalty_points,
                'result_message' => $resultMessage
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error redeeming box', [
                'msisdn' => $msisdn,
                'box_choice' => $boxChoice,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Kuna tatizo. Jaribu tena.'
            ];
        }
    }

}
