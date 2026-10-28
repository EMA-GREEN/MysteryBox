<?php

namespace App\Http\Controllers;

use App\Models\MysteryBox;
use App\Models\MysteryBoxAwardNotification;
use App\Models\Payment;
use App\Models\OpenBoxNotification;
use App\Models\Winner;
use App\Models\WinnerEntry;
use App\Models\WinnerAirtimeDisbursement;
use App\Models\WinnerWalletDisbursement;
use Illuminate\Http\Request;
use App\Services\SmsService;
use App\Services\PointsRedemptionService;
use Illuminate\Support\Facades\Log;
use App\Models\Subscriber;
use Illuminate\Http\JsonResponse;
use App\Models\WinnerWalletDisbursementNotification;
use App\Models\WinnerAirtimeDisbursementNotification;
use App\Models\MobileMoneyTransactionNotification;
use App\Models\WinnerJackpotEntry;
use App\Models\WinnerPhysicalPrize;

class MysteryBoxController extends Controller
{
    public function __construct()
    {
        
    }

    /**
     * Handle the incoming Mystery Box Award Notification from Yas (MNO).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function receiveMysteryBoxAwardNotification(Request $request)
    {
        // Receive notification from Yas (MNO) when user makes a mobile money transaction
        $data = $request->all();

        // Save the MysteryBoxAwardNotification
        MysteryBoxAwardNotification::create([
            'msisdn' => $data['msisdn'],
            'box_type' => $data['box_type'],
            'request_id' => $data['request_id'],
            'status' => 'awarded',
            'publish_status' => false
        ]);

        Log::info('Received Mystery Box Award Notification:', $data);

        // Return a JSON response
        return response()->json([
            'status' => 'success',
            'message' => 'Data received successfully',
            'data' => $data,
        ], 200); // 200 = OK
    }

    public function openMysteryBox($msisdn, $mysteryBoxId)
    {
        // Update or create subscriber record
        Subscriber::findOrCreate($msisdn, [
            'sub_channel' => 'SMS'
        ]);

        $mysteryBox = MysteryBox::findOrFail($mysteryBoxId);

        Log::info("Opening mystery box", [
            'msisdn' => $msisdn,
            'box_id' => $mysteryBoxId
        ]);

        // Find the specific Mystery Box by box_id
        $mysteryBox = MysteryBox::where('id', $mysteryBoxId)
            ->where('msisdn', $msisdn)
            ->whereIn('status', ['pending', 'awarded'])
            ->first();

        if (!$mysteryBox) {
            $errorMsg = config('messages.mystery_box.error.invalid');
            (new SmsService)->sendSms($msisdn, $errorMsg);
            return response()->json(['error' => $errorMsg], 400);
        }

        if ($mysteryBox->status === 'opened') {
            $errorMsg = config('messages.mystery_box.error.already_opened', 'This box has already been opened.');
            (new SmsService)->sendSms($msisdn, $errorMsg);
            return response()->json(['error' => $errorMsg], 400);
        }

        // Process the box opening using the winning logic
        $result = $this->processBoxOpening($mysteryBox->box_type);
        
        // Log the result
        Log::info('Box opening result', [
            'msisdn' => $msisdn,
            'box_id' => $mysteryBox->id,
            'box_type' => $mysteryBox->box_type,
            'result' => $result
        ]);
        
        // Update the box status
        $mysteryBox->status = 'opened';
        $mysteryBox->result = $result['status'];
        if ($result['status'] === 'win' && !empty($result['rewards'])) {
            // Group rewards by type for better organization
            $groupedRewards = collect($result['rewards'])->groupBy('type')->map(function($rewards) {
                return $rewards->map(function($reward) {
                    if (isset($reward['amount'])) {
                        return number_format($reward['amount']);
                    } elseif (isset($reward['points'])) {
                        return $reward['points'];
                    } elseif (isset($reward['gadget'])) {
                        return ucfirst($reward['gadget']);
                    } elseif (isset($reward['jackpot'])) {
                        return number_format($reward['jackpot']);
                    }
                    return null;
                })->filter()->values();
            })->map(function($values, $type) {
                $formattedType = ucfirst(str_replace('_', ' ', $type));
                if ($values->count() > 1) {
                    return $formattedType . ' (' . $values->implode(', ') . ')';
                }
                return $formattedType . ' (' . $values->first() . ')';
            })->values();

            $mysteryBox->prize = $groupedRewards->implode(', ');

            // Increment loyalty points if any were won
            foreach ($result['rewards'] as $reward) {
                if ($reward['type'] === 'loyalty_points' && isset($reward['points'])) {
                    $subscriber = Subscriber::where('msisdn', $msisdn)->first();
                    if ($subscriber) {
                        $subscriber->incrementLoyaltyPoints($reward['points']);
                    }
                }
            }
        } else {
            $mysteryBox->prize = null;
        }
        $mysteryBox->save();

        // Store winner details if win
        if ($result['status'] === 'win') {
            // Get the cash reward if any
            $cashReward = collect($result['rewards'])->first(function($reward) {
                return $reward['type'] === 'cash' && isset($reward['amount']);
            });

            // Get the airtime reward if any
            $airtimeReward = collect($result['rewards'])->first(function($reward) {
                return $reward['type'] === 'airtime' && isset($reward['amount']);
            });

            // Get the primary prize (first non-jackpot reward)
            $primaryReward = collect($result['rewards'])->first(function($reward) {
                return $reward['type'] !== 'jackpot_entry';
            });

            $primaryPrizeType = $this->determinePrimaryPrizeType($result['rewards']);

            $winner = WinnerEntry::create([
                'msisdn' => $msisdn,
                'box_type' => $mysteryBox->box_type,
                'prize' => $mysteryBox->prize,
                'prize_type' => $primaryPrizeType,
                'amount' => $mysteryBox->amount,
                'awarded_at' => now(),
                'box_id' => $mysteryBox->id
            ]);

            // Automatically populate winners table for airtime and wallet prizes
            if (in_array($primaryPrizeType, ['airtime', 'wallet', 'cash'])) {
                Winner::create([
                    'msisdn' => $msisdn,
                    'winner_type' => $primaryPrizeType,
                    'prize_description' => $mysteryBox->prize,
                    'prize_value' => $mysteryBox->amount,
                    'source_table' => 'winner_entries',
                    'source_id' => $winner->id,
                    'box_id' => $mysteryBox->id,
                    'box_type' => $mysteryBox->box_type,
                    'airtime_disbursement_status' => 'pending',
                    'wallet_disbursement_status' => 'pending',
                    'won_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // Create wallet disbursement notification if there's a cash reward
            if ($cashReward) {
                WinnerWalletDisbursementNotification::create([
                    'msisdn' => $msisdn,
                    'amount' => $cashReward['amount'],
                    'box_type' => $mysteryBox->box_type,
                    'box_id' => $mysteryBox->id,
                    'winner_id' => $winner->id,
                    'request_id' => 'MYSTRYBX' . $msisdn . date('YmdHis'),
                    'publish_status' => false
                ]);
            }

            // Create airtime disbursement notification if there's an airtime reward
            if ($airtimeReward) {
                WinnerAirtimeDisbursementNotification::create([
                    'msisdn' => $msisdn,
                    'amount' => $airtimeReward['amount'],
                    'box_type' => $mysteryBox->box_type,
                    'box_id' => $mysteryBox->id,
                    'winner_id' => $winner->id,
                    'request_id' => 'MYSTRYBX' . $msisdn . date('YmdHis'),
                    'publish_status' => false
                ]);
            }

            // Create jackpot entries if there are jackpot rewards
            $jackpotRewards = collect($result['rewards'])->filter(function($reward) {
                return $reward['type'] === 'jackpot_entry';
            });
            
            foreach ($jackpotRewards as $jackpotReward) {
                $drawPeriod = $jackpotReward['jackpot'] ?? 'daily'; // daily, weekly, monthly
                
                // Check if user already has an entry for this draw period (scoped to current period window)
                $now = now();
                switch ($drawPeriod) {
                    case 'daily':
                        $periodStart = $now->copy()->startOfDay();
                        $periodEnd = $now->copy()->endOfDay();
                        break;
                    case 'weekly':
                        $periodStart = $now->copy()->startOfWeek();
                        $periodEnd = $now->copy()->endOfWeek();
                        break;
                    case 'monthly':
                        $periodStart = $now->copy()->startOfMonth();
                        $periodEnd = $now->copy()->endOfMonth();
                        break;
                    default:
                        $periodStart = $now->copy()->startOfDay();
                        $periodEnd = $now->copy()->endOfDay();
                        break;
                }

                $existingEntry = WinnerJackpotEntry::where('msisdn', $msisdn)
                    ->where('draw_period', $drawPeriod)
                    ->where('status', 'pending')
                    ->first();
                
                if ($existingEntry) {
                    // Update existing entry with higher amount if new amount is higher
                    if (($jackpotReward['amount'] ?? 0) > $existingEntry->jackpot_amount) {
                        $existingEntry->update([
                            'jackpot_amount' => $jackpotReward['amount'] ?? 0,
                            'box_id' => $mysteryBox->id,
                            'box_type' => $mysteryBox->box_type,
                            'winner_id' => $winner->id
                        ]);
                    }
                } else {
                    // Create new entry
                    WinnerJackpotEntry::create([
                        'msisdn' => $msisdn,
                        'winner_id' => $winner->id,
                        'box_id' => $mysteryBox->id,
                        'box_type' => $mysteryBox->box_type,
                        'jackpot_amount' => $jackpotReward['amount'] ?? 0,
                        'draw_period' => $drawPeriod,
                        'entry_date' => $now,
                        'draw_date' => $periodEnd->toDateString(),
                        'status' => 'pending'
                    ]);
                }
            }

            // Create physical prize records if there are physical rewards
            $physicalRewards = collect($result['rewards'])->filter(function($reward) {
                return in_array($reward['type'], ['5g_router', 'feature_phone', 'tech_gadget']);
            });
            
            foreach ($physicalRewards as $physicalReward) {
                WinnerPhysicalPrize::create([
                    'msisdn' => $msisdn,
                    'winner_id' => $winner->id,
                    'box_id' => $mysteryBox->id,
                    'box_type' => $mysteryBox->box_type,
                    'device_type' => $physicalReward['type'],
                    'specific_item' => $physicalReward['gadget'] ?? null,
                    'status' => 'pending'
                ]);
            }
        }

        // Prepare message based on result
        if ($result['status'] === 'win') {
            // Check if rewards contain only loyalty points
            $hasOnlyLoyaltyPoints = true;
            $hasOtherRewards = false;
            
            foreach ($result['rewards'] as $reward) {
                if ($reward['type'] !== 'loyalty_points') {
                    $hasOnlyLoyaltyPoints = false;
                    $hasOtherRewards = true;
                    break;
                }
            }
            
            // Format prize description for SMS
            $formattedPrize = $this->formatPrizeForSms($result['rewards']);
            
            // Determine which win message to use
            if ($hasOnlyLoyaltyPoints) {
                $message = str_replace('{prize}', $formattedPrize, config('messages.sms.box_win_loyalty_only'));
            } else {
                $message = str_replace('{prize}', $formattedPrize, config('messages.sms.box_win_with_other_rewards'));
            }
            
            // Check if this is a point redemption and prepend the points used message
            if ($mysteryBox->source === MysteryBox::SOURCE_POINT_REDEEM) {
                // Get the redeem points for this box type
                $boxTypes = config('boxes.types');
                $redeemPoints = null;
                
                // Find the box config by name
                foreach ($boxTypes as $boxId => $boxConfig) {
                    if (strcasecmp($boxConfig['name'], $mysteryBox->box_type) === 0) {
                        $redeemPoints = $boxConfig['redeem_points'];
                        break;
                    }
                }
                
                if ($redeemPoints) {
                    $pointsUsedPrefix = config('messages.sms.redeem.points_used_prefix');
                    $pointsUsedMessage = str_replace('{redeem_points}', $redeemPoints, $pointsUsedPrefix);
                    $message = $pointsUsedMessage . ' ' . lcfirst($message);
                }
            }
        } else {
            $message = config('messages.sms.box_loss');
        }

        // Notify the user about the result
        (new SmsService)->sendSms($msisdn, $message);

        return response()->json([
            'message' => config('messages.mystery_box.success.opened'),
            'result_message' => $message,
            'result' => $result
        ], 200);
    }

    private function processBoxOpening($box_type)
    {
        $boxTypes = config('boxes.types');
        // Try to find by numeric key first
        $boxConfig = $boxTypes[$box_type] ?? null;
        // If not found, try to find by name (case-insensitive)
        if (!$boxConfig) {
            $boxConfig = collect($boxTypes)->first(function ($box) use ($box_type) {
                return strcasecmp($box['name'], $box_type) === 0;
            });
        }
        if (!$boxConfig) {
            return [
                'status' => 'error',
                'message' => 'Invalid box type'
            ];
        }

        $boxAmount = $boxConfig['amount'];
        $winChance = $boxConfig['win_chance'];
        $monetaryRewardLimit = config('boxes.monetary_reward_limit', 30); // Default to 30% if not set
        $maxGuaranteedMonetaryReward = $boxAmount * ($monetaryRewardLimit / 100);
        $rewardsConfig = $boxConfig['rewards'] ?? [];
        
        // Generate random number for win chance check
        $randomNumber = mt_rand(1, 100);
        $isWin = $randomNumber <= $winChance;
        
        $result = [
            'status' => 'loss',
            'box_type' => $box_type,
            'rewards' => [],
            'random_number' => $randomNumber,
            'win_chance' => $winChance,
            'is_win' => $isWin
        ];

        $rewards = [];
        $totalMonetaryReward = 0; // Track total monetary rewards

        // Process guaranteed rewards - these are always given (regardless of is_enabled status)
        if (!empty($rewardsConfig['guaranteed'])) {
            foreach ($rewardsConfig['guaranteed'] as $reward) {
                switch ($reward['type']) {
                    case 'loyalty_points':
                        $points = isset($reward['min'], $reward['max']) ? mt_rand($reward['min'], $reward['max']) : ($reward['points'] ?? 0);
                        $rewards[] = array_merge($reward, ['points' => $points]);
                        break;
                    case 'jackpot_entry':
                        $count = $reward['count'] ?? 1;
                        $rewards[] = array_merge($reward, ['count' => $count]);
                        break;
                    case 'cash':
                    case 'airtime':
                        $min = $reward['min'] ?? 0;
                        $max = $reward['max'] ?? 0;
                        
                        // Calculate remaining available amount
                        $remainingLimit = $maxGuaranteedMonetaryReward - $totalMonetaryReward;
                        
                        // Only generate reward if there's enough remaining limit
                        if ($remainingLimit > 0) {
                            // Adjust min and max to fit within remaining limit
                            $adjustedMin = max($min, 1); // Ensure minimum is at least 1
                            $adjustedMax = min($max, $remainingLimit);
                            
                            // Only generate if adjusted min is valid
                            if ($adjustedMin <= $adjustedMax) {
                                $amount = mt_rand($adjustedMin, $adjustedMax);
                                $totalMonetaryReward += $amount;
                                $rewards[] = array_merge($reward, ['amount' => $amount]);
                            }
                        }
                        break;
                    default:
                        $rewards[] = $reward;
                }
            }
        }

        // If win chance check passed, add one random possible reward
        if ($isWin && !empty($rewardsConfig['possible'])) {
            // Check if possible rewards section is enabled
            $possibleConfig = $rewardsConfig['possible'];
            if (isset($possibleConfig['is_enabled']) && !$possibleConfig['is_enabled']) {
                $isWin = false; // Skip possible rewards if section is disabled
            } else {
                $possibleRewards = $possibleConfig['rewards'] ?? $possibleConfig;
                
                // Filter enabled individual rewards
                $enabledPossibleRewards = array_filter($possibleRewards, function($reward) {
                    return !isset($reward['is_enabled']) || $reward['is_enabled'];
                });
                
                // If no enabled possible rewards, skip
                if (empty($enabledPossibleRewards)) {
                    $isWin = false;
                } else {
                    $possibleReward = $enabledPossibleRewards[array_rand($enabledPossibleRewards)];
            
                    switch ($possibleReward['type']) {
                        case 'cash':
                        case 'airtime':
                            $amount = isset($possibleReward['min'], $possibleReward['max']) 
                                ? mt_rand($possibleReward['min'], $possibleReward['max']) 
                                : ($possibleReward['amount'] ?? 0);
                            $rewards[] = array_merge($possibleReward, ['amount' => $amount]);
                            break;
                        case 'loyalty_points':
                            $points = isset($possibleReward['min'], $possibleReward['max']) 
                                ? mt_rand($possibleReward['min'], $possibleReward['max']) 
                                : ($possibleReward['points'] ?? 0);
                            $rewards[] = array_merge($possibleReward, ['points' => $points]);
                            break;
                        case 'tech_gadget':
                            $gadgets = $possibleReward['gadgets'] ?? ['smartphone', 'tablet', 'smartwatch'];
                            $gadget = $gadgets[array_rand($gadgets)];
                            $rewards[] = array_merge($possibleReward, ['gadget' => $gadget]);
                            break;
                        default:
                            $rewards[] = $possibleReward;
                    }
                }
            }
        }

        // Determine win/loss
        $result['rewards'] = $rewards;
        $result['status'] = !empty($rewards) ? 'win' : 'loss';
        return $result;
    }

    /**
     * Get pending or awarded mystery boxes for a given MSISDN
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPendingBoxes(Request $request)
    {
        try {
            $msisdn = $request->input('msisdn');
            
            if (!$msisdn) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'MSISDN is required'
                ], 400);
            }

            // Build the query for pending boxes
            $query = MysteryBox::where('msisdn', $msisdn)
                ->whereIn('status', ['pending', 'awarded']);

            // Apply award expiry time limit only to awarded boxes
            $awardExpiryHours = config('boxes.award_expiry_hours');
            $awardExpiryEnabled = config('boxes.award_expiry_enabled');
            
            if ($awardExpiryEnabled) {
                $cutoffTime = now()->subHours($awardExpiryHours);
                
                // Apply expiry filter only to awarded boxes
                $query->where(function($q) use ($cutoffTime) {
                    $q->where('status', 'pending') // Include all pending boxes
                      ->orWhere(function($subQ) use ($cutoffTime) {
                          $subQ->where('status', 'awarded')
                               ->where('created_at', '>=', $cutoffTime); // Only awarded boxes within expiry time
                      });
                });
                
                Log::info('Applied award expiry filter to getPendingBoxes', [
                    'msisdn' => $msisdn,
                    'award_expiry_hours' => $awardExpiryHours,
                    'cutoff_time' => $cutoffTime->toDateTimeString()
                ]);
            }

            $boxes = $query->orderBy('id', 'desc')
                ->get()
                ->map(function ($box) {
                    return [
                        'id' => $box->id,
                        'box_type' => $box->box_type,
                        'amount' => $box->amount,
                        'status' => $box->status,
                        'created_at' => $box->created_at->format('Y-m-d H:i:s'),
                        'expires_at' => $box->expires_at ? $box->expires_at->format('Y-m-d H:i:s') : null
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'msisdn' => $msisdn,
                    'boxes' => $boxes,
                    'total_boxes' => $boxes->count()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching pending boxes: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch pending boxes'
            ], 500);
        }
    }

    /**
     * Get mystery box status
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getStatus(Request $request): JsonResponse
    {
        $request->validate([
            'msisdn' => 'required|string',
            'box_type' => 'required|string',
            'box_id' => 'nullable|string'
        ]);

        $msisdn = $request->msisdn;
        $boxType = $request->box_type;
        $boxId = $request->box_id;

        if ($boxId) {
            // Get mystery box by box_id
            $mysteryBox = MysteryBox::where('id', $boxId)
                ->where('msisdn', $msisdn)
                ->where('box_type', $boxType)
                ->first();

            if (!$mysteryBox) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Box not found'
                ], 404);
            }

            // Get payment for this specific box
            $payment = Payment::where('box_id', $mysteryBox->id)->latest()->first();
            
            if (!$payment) {
                return response()->json([
                    'status' => 'error',
                    'payment_details' => 'Payment not found for this box'
                ], 404);
            }

            // Get winner details
            $winner = Winner::where('box_id', $mysteryBox->id)->first();

            $payment_details = $payment->callback_description;

            if(strpos($payment->callback_description, "low balance") !== false){
                $payment_details = config('messages.sms.low_balance');
            }

            $response = [
                'status' => 'success',
                'data' => [
                    'box_id' => $mysteryBox->id,
                    'box_type' => $mysteryBox->box_type,
                    'payment_status' => $payment->status,
                    'payment_amount' => $payment->amount,
                    'payment_details' => $payment_details,
                    'payment_date' => $payment->created_at
                ]
            ];

            if ($payment->status === 'success') {
                $response['data']['win_status'] = $winner ? 'WON' : 'NOT_WON';
                $response['data']['prize'] = $winner ? $winner->prize : null;
                $response['data']['win_date'] = $winner ? $winner->awarded_at : null;
            }

            return response()->json($response);
        }

        // Get latest payment for the msisdn
        $latestPayment = Payment::where('msisdn', $msisdn)
            ->where('box_type', $boxType)
            ->latest()
            ->first();

        if (!$latestPayment) {
            return response()->json([
                'status' => 'error',
                'payment_details' => 'No payment found for this box type'
            ], 404);
        }

        $payment_details = $latestPayment->callback_description ?? 'Payment was not successful';
        if(strpos($latestPayment->callback_description, "low balance") !== false){
            $payment_details = config('messages.sms.low_balance');
        }

        // If payment was not successful, return payment status
        if ($latestPayment->status !== 'success') {
            return response()->json([
                'status' => 'error',
                'payment_status' => $latestPayment->status,
                'payment_details' => $payment_details
            ]);
        }

        // Get mystery box for successful payment
        $mysteryBox = MysteryBox::where('msisdn', $msisdn)
            ->where('box_type', $boxType)
            ->where('id', $latestPayment->box_id)
            ->first();

        if (!$mysteryBox) {
            return response()->json([
                'status' => 'error',
                'payment_details' => 'No mystery box found for this payment'
            ], 404);
        }

        // Get winner details if box was opened
        $winner = Winner::where('box_id', $mysteryBox->id)->first();

        $response = [
            'status' => 'success',
            'data' => [
                'box_id' => $mysteryBox->id,
                'box_type' => $mysteryBox->box_type,
                'payment_status' => $payment_status,
                'payment_amount' => $latestPayment->amount,
                'payment_details' => $latestPayment->callback_description,
                'payment_date' => $latestPayment->created_at
            ]
        ];

        if ($latestPayment->status === 'success') {
            $response['data']['win_status'] = $winner ? 'WON' : 'NOT_WON';
            $response['data']['prize'] = $winner ? $winner->prize : null;
            $response['data']['win_date'] = $winner ? $winner->awarded_at : null;
        }

        return response()->json($response);
    }

    /**
     * Checkpoint endpoint - Get subscriber loyalty points by MSISDN
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkpoint(Request $request): JsonResponse
    {
        try {
            // Validate the request
            $request->validate([
                'msisdn' => 'required|string|regex:/^255[0-9]{9}$/|min:12|max:12',
            ]);

            $msisdn = $request->input('msisdn');

            Log::info('Checkpoint request received', [
                'msisdn' => $msisdn,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // Check if subscriber exists by MSISDN
            $subscriber = Subscriber::where('msisdn', $msisdn)->first();

            if (!$subscriber) {
                Log::info('Subscriber not found for checkpoint', ['msisdn' => $msisdn]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Subscriber not found'
                ], 404);
            }

            Log::info('Subscriber found for checkpoint', [
                'msisdn' => $msisdn,
                'subscriber_id' => $subscriber->id,
                'loyalty_points' => $subscriber->loyalty_points
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Checkpoint successful',
                'data' => [
                    'msisdn' => $subscriber->msisdn,
                    'subscriber_id' => $subscriber->id,
                    'loyalty_points' => $subscriber->loyalty_points,
                    'activity_status' => $subscriber->activity_status,
                    'customer_type' => $subscriber->customer_type,
                    'last_interaction' => $subscriber->last_interaction
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Checkpoint error', [
                'msisdn' => $request->input('msisdn'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Checkpoint failed'
            ], 500);
        }
    }

    /**
     * Check box eligibility for a subscriber by MSISDN
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkBoxEligibility(Request $request): JsonResponse
    {
        try {
            // Validate the request
            $request->validate([
                'msisdn' => 'required|string|regex:/^255[0-9]{9}$/|min:12|max:12',
            ]);

            $msisdn = $request->input('msisdn');

            Log::info('Check box eligibility request received', [
                'msisdn' => $msisdn,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // Check if subscriber exists by MSISDN
            $subscriber = Subscriber::where('msisdn', $msisdn)->first();

            if (!$subscriber) {
                Log::info('Subscriber not found for box eligibility check', ['msisdn' => $msisdn]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Subscriber not found'
                ], 404);
            }

            // Use PointsRedemptionService to check eligibility
            $pointsRedemptionService = new PointsRedemptionService();
            $eligibility = $pointsRedemptionService->checkRedemptionEligibility($msisdn);

            Log::info('Box eligibility check completed', [
                'msisdn' => $msisdn,
                'subscriber_id' => $subscriber->id,
                'loyalty_points' => $subscriber->loyalty_points,
                'eligible' => $eligibility['eligible'],
                'available_boxes_count' => count($eligibility['available_boxes'])
            ]);

            if (!$eligibility['eligible']) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Not enough points',
                    'data' => [
                        'msisdn' => $subscriber->msisdn,
                        'subscriber_id' => $subscriber->id,
                        'loyalty_points' => $subscriber->loyalty_points,
                        'eligible' => false,
                        'available_boxes' => []
                    ]
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Subscriber is eligible for box redemption',
                'data' => [
                    'msisdn' => $subscriber->msisdn,
                    'subscriber_id' => $subscriber->id,
                    'loyalty_points' => $eligibility['loyalty_points'],
                    'eligible' => true,
                    'available_boxes' => $eligibility['available_boxes']
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Box eligibility check error', [
                'msisdn' => $request->input('msisdn'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Box eligibility check failed'
            ], 500);
        }
    }

    /**
     * Redeem a mystery box via APP
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function redeemBoxApp(Request $request): JsonResponse
    {
        try {
            // Validate the request
            $request->validate([
                'msisdn' => 'required|string|regex:/^255[0-9]{9}$/|min:12|max:12',
                'box_choice' => 'required|string|in:1,2,3,4,5'
            ]);

            $msisdn = $request->input('msisdn');
            $boxChoice = $request->input('box_choice');

            Log::info('APP box redemption request received', [
                'msisdn' => $msisdn,
                'box_choice' => $boxChoice,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // Check if subscriber exists by MSISDN
            $subscriber = Subscriber::where('msisdn', $msisdn)->first();

            if (!$subscriber) {
                Log::info('Subscriber not found for APP box redemption', ['msisdn' => $msisdn]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Subscriber not found'
                ], 404);
            }

            // Use PointsRedemptionService to redeem box with 'APP' source
            $pointsRedemptionService = new PointsRedemptionService();
            $redemptionResult = $pointsRedemptionService->redeemBox($msisdn, $boxChoice, 'APP');

            Log::info('APP box redemption completed', [
                'msisdn' => $msisdn,
                'box_choice' => $boxChoice,
                'subscriber_id' => $subscriber->id,
                'success' => $redemptionResult['success'],
                'request_id' => $redemptionResult['request_id'] ?? null
            ]);

            if (!$redemptionResult['success']) {
                return response()->json([
                    'status' => 'error',
                    'message' => $redemptionResult['message']
                ], 400);
            }

            return response()->json([
                'status' => 'success',
                'message' => $redemptionResult['message'],
                'data' => [
                    'msisdn' => $msisdn,
                    'box_choice' => $boxChoice,
                    'request_id' => $redemptionResult['request_id'] ?? null,
                    'mystery_box_id' => $redemptionResult['box_id'] ?? null,
                    'remaining_points' => $redemptionResult['remaining_points'] ?? null,
                    'result_message' => $redemptionResult['result_message'] ?? null
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('APP box redemption error', [
                'msisdn' => $request->input('msisdn'),
                'box_choice' => $request->input('box_choice'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Box redemption failed'
            ], 500);
        }
    }

    /**
     * Get all boxes for a subscriber by MSISDN
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getBoxesByMsisdn(Request $request): JsonResponse
    {
        try {
            // Validate the request
            $request->validate([
                'msisdn' => 'required|string|regex:/^[0-9]+$/|min:10|max:15',
            ]);

            $msisdn = $request->input('msisdn');

            Log::info('Get boxes by MSISDN request received', [
                'msisdn' => $msisdn,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // Get all boxes for the subscriber
            $boxes = MysteryBox::where('msisdn', $msisdn)
                ->orderBy('id', 'desc')
                ->get();

            Log::info('Boxes retrieved for subscriber', [
                'msisdn' => $msisdn,
                'box_count' => $boxes->count()
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Boxes retrieved successfully',
                'data' => [
                    'msisdn' => $msisdn,
                    'total_boxes' => $boxes->count(),
                    'boxes' => $boxes->toArray()
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get boxes by MSISDN error', [
                'msisdn' => $request->input('msisdn'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve boxes'
            ], 500);
        }
    }

    /**
     * Get winnings by MSISDN with date filtering
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getWinningsByMsisdn(Request $request): JsonResponse
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

            Log::info('Get winnings by MSISDN request received', [
                'msisdn' => $msisdn,
                'from' => $from,
                'to' => $to,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // Build the query
            $query = Winner::where('msisdn', $msisdn);

            // Apply date filtering if not "all"
            if ($from !== 'all' && $to !== 'all') {
                // Validate date format
                if (!strtotime($from) || !strtotime($to)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invalid date format. Use YYYY-MM-DD or "all"'
                    ], 422);
                }

                $query->whereBetween('awarded_at', [$from . ' 00:00:00', $to . ' 23:59:59']);
            }

            // Get winnings ordered by awarded date (newest first)
            $winnings = $query->orderBy('awarded_at', 'desc')->get();

            Log::info('Winnings retrieved for subscriber', [
                'msisdn' => $msisdn,
                'winning_count' => $winnings->count(),
                'from' => $from,
                'to' => $to
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Winnings retrieved successfully',
                'data' => [
                    'msisdn' => $msisdn,
                    'total_winnings' => $winnings->count(),
                    'total_amount' => $winnings->sum('amount'),
                    'date_filter' => [
                        'from' => $from,
                        'to' => $to
                    ],
                    'winnings' => $winnings->toArray()
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get winnings by MSISDN error', [
                'msisdn' => $request->input('msisdn'),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve winnings'
            ], 500);
        }
    }

    /**
     * Get airtime disbursements by MSISDN with date filtering
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getAirtimeDisbursementsByMsisdn(Request $request): JsonResponse
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

            Log::info('Get airtime disbursements by MSISDN request received', [
                'msisdn' => $msisdn,
                'from' => $from,
                'to' => $to,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // Build the query - only successful disbursements
            $query = WinnerAirtimeDisbursement::where('msisdn', $msisdn)
                ->where('response_status', 'SUCCESS');

            // Apply date filtering if not "all"
            if ($from !== 'all' && $to !== 'all') {
                // Validate date format
                if (!strtotime($from) || !strtotime($to)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invalid date format. Use YYYY-MM-DD or "all"'
                    ], 422);
                }

                $query->whereBetween('request_sent_time', [$from . ' 00:00:00', $to . ' 23:59:59']);
            }

            // Get disbursements ordered by request time (newest first)
            $disbursements = $query->orderBy('request_sent_time', 'desc')->get();

            Log::info('Airtime disbursements retrieved for subscriber', [
                'msisdn' => $msisdn,
                'disbursement_count' => $disbursements->count(),
                'from' => $from,
                'to' => $to
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Airtime disbursements retrieved successfully',
                'data' => [
                    'msisdn' => $msisdn,
                    'total_disbursements' => $disbursements->count(),
                    'total_amount' => $disbursements->sum('amount'),
                    'date_filter' => [
                        'from' => $from,
                        'to' => $to
                    ],
                    'disbursements' => $disbursements->toArray()
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get airtime disbursements by MSISDN error', [
                'msisdn' => $request->input('msisdn'),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve airtime disbursements'
            ], 500);
        }
    }

    /**
     * Get wallet disbursements by MSISDN with date filtering
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getWalletDisbursementsByMsisdn(Request $request): JsonResponse
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

            Log::info('Get wallet disbursements by MSISDN request received', [
                'msisdn' => $msisdn,
                'from' => $from,
                'to' => $to,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // Build the query - only successful disbursements
            $query = WinnerWalletDisbursement::where('msisdn', $msisdn)
                ->where('response_status', 'SUCCESS');

            // Apply date filtering if not "all"
            if ($from !== 'all' && $to !== 'all') {
                // Validate date format
                if (!strtotime($from) || !strtotime($to)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Invalid date format. Use YYYY-MM-DD or "all"'
                    ], 422);
                }

                $query->whereBetween('request_sent_time', [$from . ' 00:00:00', $to . ' 23:59:59']);
            }

            // Get disbursements ordered by request time (newest first)
            $disbursements = $query->orderBy('request_sent_time', 'desc')->get();

            Log::info('Wallet disbursements retrieved for subscriber', [
                'msisdn' => $msisdn,
                'disbursement_count' => $disbursements->count(),
                'from' => $from,
                'to' => $to
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Wallet disbursements retrieved successfully',
                'data' => [
                    'msisdn' => $msisdn,
                    'total_disbursements' => $disbursements->count(),
                    'total_amount' => $disbursements->sum('amount'),
                    'total_payout_amount' => $disbursements->sum('payout_amount'),
                    'date_filter' => [
                        'from' => $from,
                        'to' => $to
                    ],
                    'disbursements' => $disbursements->toArray()
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Get wallet disbursements by MSISDN error', [
                'msisdn' => $request->input('msisdn'),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve wallet disbursements'
            ], 500);
        }
    }

    /**
     * Determine the primary prize type based on rewards with priority order
     * Priority: cash > airtime > hardware (5g_router, feature_phone, tech_gadget) > jackpot_entry > loyalty_points
     *
     * @param array $rewards
     * @return string
     */
    private function determinePrimaryPrizeType(array $rewards): string
    {
        // Priority: cash > airtime > hardware (5g_router, feature_phone, tech_gadget) > jackpot_entry > loyalty_points
        $primaryPrizeType = 'loyalty_points'; // default (most common)
        
        // Check for rewards in priority order
        $cashReward = collect($rewards)->first(function($reward) {
            return $reward['type'] === 'cash';
        });
        
        $airtimeReward = collect($rewards)->first(function($reward) {
            return $reward['type'] === 'airtime';
        });
        
        $hardwareReward = collect($rewards)->first(function($reward) {
            return in_array($reward['type'], ['5g_router', 'feature_phone', 'tech_gadget']);
        });
        
        $jackpotReward = collect($rewards)->first(function($reward) {
            return $reward['type'] === 'jackpot_entry';
        });
        
        if ($cashReward) {
            $primaryPrizeType = 'cash';
        } elseif ($airtimeReward) {
            $primaryPrizeType = 'airtime';
        } elseif ($hardwareReward) {
            $primaryPrizeType = $hardwareReward['type']; // Use the specific hardware type
        } elseif ($jackpotReward) {
            $primaryPrizeType = 'jackpot_entry';
        }
        
        return $primaryPrizeType;
    }

    /**
     * Format prize description for SMS messages using configurable formats
     *
     * @param array $rewards
     * @return string
     */
    private function formatPrizeForSms(array $rewards): string
    {
        $formattedRewards = [];
        $rewardFormats = config('messages.sms.reward_formats');
        $jackpotPeriods = config('messages.sms.jackpot_periods');

        foreach ($rewards as $reward) {
            switch ($reward['type']) {
                case 'airtime':
                    if (isset($reward['amount'])) {
                        $format = $rewardFormats['airtime'];
                        $formattedRewards[] = str_replace('{amount}', number_format($reward['amount']), $format);
                    }
                    break;

                case 'cash':
                    if (isset($reward['amount'])) {
                        $format = $rewardFormats['cash'];
                        $formattedRewards[] = str_replace('{amount}', number_format($reward['amount']), $format);
                    }
                    break;

                case 'jackpot_entry':
                    if (isset($reward['jackpot']) && isset($reward['amount'])) {
                        $format = $rewardFormats['jackpot_entry'];
                        $jackpotPeriod = $jackpotPeriods[$reward['jackpot']] ?? $reward['jackpot'];
                        $formattedRewards[] = str_replace(
                            ['{jackpot}', '{amount}'],
                            [$jackpotPeriod, number_format($reward['amount'])],
                            $format
                        );
                    }
                    break;

                case 'loyalty_points':
                    if (isset($reward['points'])) {
                        $formattedRewards[] = "point {$reward['points']}";
                    }
                    break;

                default:
                    // For other reward types, use the original format
                    if (isset($reward['amount'])) {
                        $formattedRewards[] = ucfirst(str_replace('_', ' ', $reward['type'])) . " (" . number_format($reward['amount']) . ")";
                    } elseif (isset($reward['points'])) {
                        $formattedRewards[] = ucfirst(str_replace('_', ' ', $reward['type'])) . " ({$reward['points']})";
                    } elseif (isset($reward['gadget'])) {
                        $formattedRewards[] = ucfirst(str_replace('_', ' ', $reward['type'])) . " (" . ucfirst($reward['gadget']) . ")";
                    }
                    break;
            }
        }

        return implode(', ', $formattedRewards);
    }
}
