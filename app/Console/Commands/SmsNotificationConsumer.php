<?php

namespace App\Console\Commands;

use App\Http\Controllers\MysteryBoxController;
use App\Http\Controllers\PaymentController;
use App\Models\Payment;
use App\Models\SmsNotification;
use App\Services\SmsService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Illuminate\Support\Facades\Log;
use App\Models\SmsLog;
use Illuminate\Support\Facades\DB;
use App\Models\Subscriber;
use App\Models\RenewalSubscriber;
use App\Models\MysteryBox;
use App\Models\Unsubscriber;
use App\Services\PointsRedemptionService;
use App\Services\MpushSubsManageService;

class SmsNotificationConsumer extends Command
{
    protected $signature = 'sms:consume-notifications {--consumer-id=1}';
    protected $description = 'Consume SMS notifications from RabbitMQ queue';

    public function handle()
    {
        // date_default_timezone_set('Africa/Dar_es_Salaam');
        $consumerId = $this->option('consumer-id');
        $this->info(sprintf('Starting SMS Notification Consumer #%d...', $consumerId));

        try {
            // Get queue settings
            $queueSettings = config('queue_settings.sms_notification.consumer');
            $queueName = $queueSettings['queue_name'];
            $prefetchCount = $queueSettings['prefetch_count'];

            // Create RabbitMQ connection
            $connection = new AMQPStreamConnection(
                config('rabbitmq.host'),
                config('rabbitmq.port'),
                config('rabbitmq.user'),
                config('rabbitmq.password')
            );

            $channel = $connection->channel();

            // Declare queue
            $channel->queue_declare($queueName, false, true, false, false);

            // Set prefetch count for fair distribution
            $channel->basic_qos(null, $prefetchCount, null);

            $this->info(sprintf('Consumer #%d waiting for SMS notifications...', $consumerId));

            // Define callback function
            $callback = function (AMQPMessage $message) use ($consumerId) {
                try {
                    $data = json_decode($message->getBody(), true);
                    $msisdn = $data['msisdn'];
                    // Normalize the message by trimming and replacing multiple spaces with single space
                    $text = preg_replace('/\s+/', ' ', trim(strtoupper($data['message'])));
                    
                    $this->info(sprintf(
                        'Consumer #%d processing SMS notification - MSISDN: %s, Message: %s',
                        $consumerId,
                        $msisdn,
                        $text
                    ));

                    $smsService = new SmsService();
                    $pointsRedemptionService = new PointsRedemptionService();
                    $mpushSubsManageService = app(MpushSubsManageService::class);

                    // Update or create subscriber record
                    $subscriber = Subscriber::findOrCreate($msisdn, [
                        'sub_channel' => 'SMS'
                    ]);
                    
                    // Ensure subscriber has activity_status and dnd_status set
                    if (!$subscriber->activity_status) {
                        $subscriber->update(['activity_status' => 'ACTIVE']);
                        $subscriber->refresh();
                    }
                    if (!$subscriber->dnd_status) {
                        $subscriber->update(['dnd_status' => 'OFF']);
                        $subscriber->refresh();
                    }
                    
                    // Skip processing for existing subscribers with dnd_status=ON (blacklisted)
                    if (!$subscriber->wasRecentlyCreated && $subscriber->dnd_status === 'ON') {
                        Log::info("Skipping processing for blacklisted subscriber (dnd_status=ON): $msisdn");
                        $message->ack();
                        return;
                    }
                    
                    // Update or create renewal_subscriber record starts
                    $renewalSubscriber = RenewalSubscriber::where('msisdn', $msisdn)->first();
                    
                    if (!$renewalSubscriber) {
                        // Create new renewal subscriber
                        $renewalSubscriber = RenewalSubscriber::create([
                            'msisdn' => $msisdn,
                            'activity_status' => $subscriber->activity_status ?? 'ACTIVE',
                            'dnd_status' => $subscriber->dnd_status ?? 'OFF',
                            'priority' => 1,
                            'retry_count' => 0,
                            'publish_status' => false,
                            'last_renewal_billing' => null,
                            'next_retrial_period' => null,
                        ]);
                    } else {
                        // Update existing renewal subscriber's activity and dnd status to match subscriber
                        $renewalSubscriber->update([
                            'activity_status' => $subscriber->activity_status ?? 'ACTIVE',
                            'dnd_status' => $subscriber->dnd_status ?? 'OFF',
                        ]);
                    }
                    // Update or create renewal_subscriber record ends
                    
                    // Check if keyword is not unsubscription and reactivate if subscriber is INACTIVE
                    if (!in_array($text, config('keywords.unsubscribe')) && $subscriber->activity_status === 'INACTIVE') {
                        // Reactivate subscriber
                        $subscriber->update(['activity_status' => 'ACTIVE']);
                        Log::info("Subscriber reactivated: $msisdn");
                        $mpushSubsManageService->notifySubscription($msisdn);
                        
                        // Reactivate renewal_subscribers
                        DB::table('renewal_subscribers')
                            ->where('msisdn', $msisdn)
                            ->where('activity_status', 'INACTIVE')
                            ->update(['activity_status' => 'ACTIVE']);
                        Log::info("Renewal subscriber reactivated: $msisdn");
                        
                        // Send welcome again message, then terms message
                        $welcomeAgainMessage = config('messages.sms.welcome_again_message');
                        Log::info("Sending welcome again SMS to reactivated subscriber: $msisdn");
                        $smsService->sendSms($msisdn, $welcomeAgainMessage);
                        // sleep(3);
                        $termsMessage = config('messages.sms.terms_message');
                        Log::info("Sending terms SMS to reactivated subscriber: $msisdn");
                        $smsService->sendSms($msisdn, $termsMessage);
                    }
                    
                    // Check if this is a new subscriber and send welcome message
                    if ($subscriber->wasRecentlyCreated) {
                        $mpushSubsManageService->notifySubscription($msisdn);
                        $welcomeMessage = config('messages.sms.welcome_message');
                        $cleanMessage = preg_replace('/\s+/', ' ', trim($welcomeMessage));
                        Log::info("Sending welcome SMS to new subscriber: $msisdn, Message: " . $cleanMessage);
                        $smsService->sendSms($msisdn, $welcomeMessage);
                        // sleep(3);
                        $termsMessage = config('messages.sms.terms_message');
                        Log::info("Sending terms SMS to new subscriber: $msisdn");
                        $smsService->sendSms($msisdn, $termsMessage);
                    } else {
                        // Update last_interaction for existing subscribers
                        $subscriber->update(['last_interaction' => now()]);
                    }

                    // Check if this is an unsubscription keyword
                    if (in_array($text, config('keywords.unsubscribe'))) {
                        $this->info(sprintf('Consumer #%d: Unsubscription keyword detected', $consumerId));
                        // Update subscriber activity_status to INACTIVE
                        $subscriber->update(['activity_status' => 'INACTIVE']);
                        Log::info("Subscriber unsubscribed: $msisdn");
                        $mpushSubsManageService->notifyUnsubscription($msisdn);
                        
                        // Update renewal_subscribers activity_status to INACTIVE
                        DB::table('renewal_subscribers')
                            ->where('msisdn', $msisdn)
                            ->update(['activity_status' => 'INACTIVE']);
                        Log::info("Renewal subscriber unsubscribed: $msisdn");
                        
                        // Create unsubscriber record
                        $notificationId = $data['notification_id'] ?? null;
                        Unsubscriber::create([
                            'msisdn' => $msisdn,
                            'channel' => Unsubscriber::CHANNEL_SMS,
                            'mo_id' => $notificationId,
                        ]);
                        Log::info("Unsubscriber record created: $msisdn, notification_id: $notificationId");
                        
                        // Send unsubscription SMS
                        $unsubscriptionMessage = config('messages.sms.unsubscription_message', 'You have been successfully unsubscribed from our service.');
                        $cleanMessage = preg_replace('/\s+/', ' ', trim($unsubscriptionMessage));
                        Log::info("Sending unsubscription SMS to: $msisdn, Message: " . $cleanMessage);
                        $smsService->sendSms($msisdn, $unsubscriptionMessage);
                    } elseif (in_array($text, config('keywords.redeem'))) {
                        $this->info(sprintf('Consumer #%d: Redeem keyword detected', $consumerId));
                        $response = $pointsRedemptionService->generateRedemptionMessage($msisdn);
                        $cleanMessage = preg_replace('/\s+/', ' ', trim($response));
                        Log::info("Sending redemption SMS to: $msisdn, Message: " . $cleanMessage);
                        $smsService->sendSms($msisdn, $response);
                    } elseif (in_array($text, config('keywords.purchase')) || in_array($text, config('keywords.open_box'))) {
                        $this->info(sprintf('Consumer #%d: Purchase/Open box keyword detected', $consumerId));
                        $response = $this->generateBoxSelectionMessage();
                        $cleanMessage = preg_replace('/\s+/', ' ', trim($response));
                        Log::info("Sending SMS to: $msisdn, Message: " . $cleanMessage);
                        $smsService->sendSms($msisdn, $response);
                    } elseif (in_array($text, $this->getVisibleBoxTypes()) && $this->isRedemptionResponse($msisdn)) {
                        $this->info(sprintf('Consumer #%d: Box type "%s" detected as redemption response', $consumerId, $text));
                        // Handle redemption box choice
                        $redemptionResult = $pointsRedemptionService->redeemBox($msisdn, $text, 'SMS');
                        $cleanMessage = preg_replace('/\s+/', ' ', trim($redemptionResult['message']));
                        // Log::info("Sending redemption result SMS to: $msisdn, Message: " . $cleanMessage);
                        if(!$redemptionResult['success']){
                            $smsService->sendSms($msisdn, $redemptionResult['message']);
                        }
                    } elseif (in_array($text, $this->getVisibleBoxTypes())) {
                           $this->info(sprintf('Consumer #%d: Box type "%s" detected, checking previous message context...', $consumerId, $text));
                           
                           // Get the most recent non-numeric message to determine payment type
                           $mostRecentNonNumeric = SmsNotification::where('msisdn', $msisdn)
                               ->whereRaw('TRIM(message) NOT REGEXP \'^[0-9]+$\'') // Filter out numeric-only messages
                               ->orderBy('created_at', 'desc')
                               ->first();

                         if (!$mostRecentNonNumeric) {
                             // No previous non-numeric message: treat this as the *start* of the flow
                             // and show the user the box selection menu instead of invalid sequence.
                             $this->info(sprintf(
                                 'Consumer #%d: No previous non-numeric message for MSISDN: %s, sending box selection menu',
                                 $consumerId,
                                 $msisdn
                             ));

                             $response = $this->generateBoxSelectionMessage();
                             $cleanMessage = preg_replace('/\s+/', ' ', trim($response));
                             // Log::info("Sending SMS to: $msisdn, Message: " . $cleanMessage);
                             $smsService->sendSms($msisdn, $response);
                             // Do NOT return here; we still want to reach the ack() below
                         }

                         $this->info(sprintf('Consumer #%d: Previous non-numeric message found: "%s"', $consumerId, $mostRecentNonNumeric->message));

                         // Check if the most recent non-numeric message is an open_box keyword
                         $previousText = strtoupper(trim($mostRecentNonNumeric->message));
                         $openBoxKeywords = config('keywords.open_box');
                         $isOpenBoxKeyword = in_array($previousText, $openBoxKeywords);
                         $payment_type = $isOpenBoxKeyword 
                             ? Payment::PAYMENT_TYPE_OPEN 
                             : Payment::PAYMENT_TYPE_PURCHASE;
                         
                         $this->info(sprintf('Consumer #%d: Payment type determined: %s (isOpenBoxKeyword: %s)', $consumerId, $payment_type, $isOpenBoxKeyword ? 'true' : 'false'));

                        // Get latest awarded mystery box for msisdn only if payment type is OPEN
                        $latestAwardedBox = null;
                        if ($payment_type === Payment::PAYMENT_TYPE_OPEN) {
                            $query = MysteryBox::where('msisdn', $msisdn)
                                ->where('status', 'awarded'); //we only try to open awarded boxes, as purchased boxes are automatically opened
                            
                            // Apply award expiry time limit if enabled
                            $awardExpiryHours = config('boxes.award_expiry_hours');
                            $awardExpiryEnabled = config('boxes.award_expiry_enabled');
                            
                            if ($awardExpiryEnabled) {
                                $cutoffTime = now()->subHours($awardExpiryHours);
                                $query->where('created_at', '>=', $cutoffTime);
                                
                                Log::info('Applying award expiry time limit', [
                                    'msisdn' => $msisdn,
                                    'award_expiry_hours' => $awardExpiryHours,
                                    'cutoff_time' => $cutoffTime->toDateTimeString()
                                ]);
                            }
                            
                            $latestAwardedBox = $query->latest()->first();
                            
                            Log::info('Latest awarded box query result', [
                                'msisdn' => $msisdn,
                                'found_box' => $latestAwardedBox ? true : false,
                                'box_id' => $latestAwardedBox ? $latestAwardedBox->id : null,
                                'box_created_at' => $latestAwardedBox ? $latestAwardedBox->created_at->toDateTimeString() : null,
                                'award_expiry_enabled' => $awardExpiryEnabled,
                                'award_expiry_hours' => $awardExpiryHours
                            ]);
                        }

                        // Check if this is an award box that should be opened directly
                        if ($payment_type === Payment::PAYMENT_TYPE_OPEN && 
                            $latestAwardedBox && 
                            ($latestAwardedBox->isMixxPaymentAward() || $latestAwardedBox->isRenewalAward())) {
                            
                            $this->info(sprintf('Consumer #%d: Opening award box directly - Box ID: %d', $consumerId, $latestAwardedBox->id));
                            
                            Log::info('Opening award box directly', [
                                'msisdn' => $msisdn,
                                'box_id' => $latestAwardedBox->id,
                                'source' => $latestAwardedBox->source,
                                'payment_type' => $payment_type
                            ]);
                            
                            // Open the box directly using MysteryBoxController
                            $mysteryBoxController = new MysteryBoxController();
                            $mysteryBoxController->openMysteryBox($msisdn, $latestAwardedBox->id);
                            
                            $this->info(sprintf('Consumer #%d: Award box opened successfully', $consumerId));
                        } else {
                            // For purchase boxes or other cases, proceed with normal payment flow
                            $this->info(sprintf('Consumer #%d: Initiating payment - Box Type: %s, Payment Type: %s, Box ID: %s', 
                                $consumerId, $text, $payment_type, $latestAwardedBox ? $latestAwardedBox->id : 'null'));
                            
                            $request = new Request([
                                'msisdn' => $msisdn,
                                'box_type' => $text,
                                'channel' => 'SMS',
                                'payment_type' => $payment_type,
                                'box_id' => $latestAwardedBox ? $latestAwardedBox->id : null
                            ]);
                            (new PaymentController)->initiatePayment($request);
                            
                            $this->info(sprintf('Consumer #%d: Payment initiation completed', $consumerId));
                        }
                    } elseif (in_array(strtoupper($text), config('keywords.open_mixx_payment_box'))) {
                        // Check if the text is an open_mixx_payment_box keyword (WIN)
                        // Log::info('Open Mixx Payment Box keyword detected', [
                        //     'msisdn' => $msisdn,
                        //     'text' => $text,
                        //     'text_uppercase' => strtoupper($text),
                        //     'keywords_config' => config('keywords.open_mixx_payment_box')
                        // ]);
                        $this->openLatestMixxPaymentBox($msisdn, $smsService);
                    } elseif (in_array(strtoupper($text), config('keywords.points_check'))) {
                        // Check if the text is a points_check keyword
                        $subscriber = Subscriber::where('msisdn', $msisdn)->first();
                        if ($subscriber) {
                            $points = $subscriber->loyalty_points;
                            $pointsMessage = config('messages.sms.points_message', 'Your loyalty points: {points}');
                            $pointsMessage = str_replace('{points}', $points, $pointsMessage);
                            $smsService->sendSms($msisdn, $pointsMessage);
                        }
                    } elseif (in_array(strtoupper($text), config('keywords.help'))) {
                        // Check if the text is a help keyword
                        $helpMessage = config('messages.sms.help_message', 'Help message goes here.');
                        $smsService->sendSms($msisdn, $helpMessage);
                    } else {
                        $response = config('messages.sms.invalid_response');
                        $cleanMessage = preg_replace('/\s+/', ' ', trim($response));
                        Log::info("Sending SMS to: $msisdn, Message: " . $cleanMessage);
                        $smsService->sendSms($msisdn, $response);
                    }

                    // Acknowledge message
                    $this->info(sprintf('Consumer #%d: Acknowledging message...', $consumerId));
                    $message->ack();
                    $this->info(sprintf('Consumer #%d: Message acknowledged', $consumerId));

                    $this->info(sprintf(
                        'Consumer #%d processed SMS notification - MSISDN: %s, Message: %s',
                        $consumerId,
                        $msisdn,
                        $text
                    ));
                } catch (\Exception $e) {
                    Log::error(sprintf('Consumer #%d error processing SMS notification: %s', $consumerId, $e->getMessage()), [
                        'trace' => $e->getTraceAsString(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ]);
                    $this->error(sprintf('Consumer #%d Error: %s', $consumerId, $e->getMessage()));
                    $this->error(sprintf('Consumer #%d Error in file: %s:%d', $consumerId, $e->getFile(), $e->getLine()));
                    
                    // Reject the message without requeuing so it doesn't block the consumer
                    if (method_exists($message, 'reject')) {
                        $message->reject(false);
                    }
                }
            };

            // Start consuming
            $channel->basic_consume($queueName, '', false, false, false, false, $callback);

            while ($channel->is_consuming()) {
                $channel->wait();
            }

            // Close connection
            $channel->close();
            $connection->close();
        } catch (\Exception $e) {
            Log::error(sprintf('Consumer #%d error in SMS notification consumer: %s', $consumerId, $e->getMessage()));
            $this->error('Error: ' . $e->getMessage());
        }
    }

    /**
     * Get visible box types (non-hidden boxes)
     *
     * @return array
     */
    private function getVisibleBoxTypes()
    {
        $boxTypes = config('boxes.types');
        $visibleBoxTypes = [];
        
        foreach ($boxTypes as $id => $box) {
            if (!isset($box['hidden']) || $box['hidden'] !== true) {
                $visibleBoxTypes[] = $id;
            }
        }
        
        return $visibleBoxTypes;
    }

    /**
     * Generate the box selection response message
     *
     * @return string
     */
    private function generateBoxSelectionMessage()
    {
        $boxTypes = config('boxes.types');
        $header = config('messages.sms.box_selection_header', 'Chagua namba ya box ulipendalo. Mfano: 1');
        $footer = config('messages.sms.box_selection_footer', 'Tuma namba ya box.');
        
        // Filter out hidden boxes
        $visibleBoxes = [];
        foreach ($boxTypes as $id => $box) {
            if (!isset($box['hidden']) || $box['hidden'] !== true) {
                $visibleBoxes[$id] = $box;
            }
        }
        
        $response = $header . "\n";
        $boxCount = count($visibleBoxes);
        $currentIndex = 0;
        foreach ($visibleBoxes as $id => $box) {
             $currentIndex++;
             $comma = ($currentIndex < $boxCount) ? "," : "";
             $response .= " $id - {$box['display_name']} (Tsh{$box['amount']})$comma\n";
         }
        // $response .= " " . $footer;
        return $response;
    }

         /**
      * Check if the current message is a response to a redemption request
      */
     private function isRedemptionResponse($msisdn)
     {
         // Get the most recent non-numeric message
         $mostRecentNonNumeric = SmsNotification::where('msisdn', $msisdn)
             ->whereRaw('TRIM(message) NOT REGEXP \'^[0-9]+$\'') // Filter out numeric-only messages
             ->orderBy('created_at', 'desc')
             ->first();

        // Log the context for debugging
        Log::info('isRedemptionResponse context analysis:', [
            'msisdn' => $msisdn,
            'most_recent_non_numeric' => $mostRecentNonNumeric ? [
                'message' => $mostRecentNonNumeric->message,
                'created_at' => $mostRecentNonNumeric->created_at
            ] : null
        ]);

        // If no non-numeric message found, definitely not a redemption response
        if (!$mostRecentNonNumeric) {
            Log::info('isRedemptionResponse: No previous non-numeric message found', [
                'msisdn' => $msisdn
            ]);
            return false;
        }

        // Check if the most recent non-numeric message is a redemption keyword
        $previousText = strtoupper(trim($mostRecentNonNumeric->message));
        $redeemKeywords = config('keywords.redeem');
        $isRedemptionKeyword = in_array($previousText, $redeemKeywords);
        
        Log::info('isRedemptionResponse result:', [
            'msisdn' => $msisdn,
            'previous_message' => $mostRecentNonNumeric->message,
            'previous_text_normalized' => $previousText,
            'redeem_keywords' => $redeemKeywords,
            'is_redemption_keyword' => $isRedemptionKeyword,
            'is_redemption_response' => $isRedemptionKeyword
        ]);

        return $isRedemptionKeyword;
    }

    /**
     * Open the latest mixx_payment mystery box for the given MSISDN
     */
    private function openLatestMixxPaymentBox($msisdn, $smsService)
    {
        try {
            // Get the latest mystery box with source=mixx_payment and status=awarded
            $query = MysteryBox::where('msisdn', $msisdn)
                ->where('source', MysteryBox::SOURCE_MIXX_PAYMENT)
                ->where('status', 'awarded');
            
            // Apply award expiry time limit if enabled
            $awardExpiryHours = config('boxes.award_expiry_hours');
            $awardExpiryEnabled = config('boxes.award_expiry_enabled');
            
            if ($awardExpiryEnabled) {
                $cutoffTime = now()->subHours($awardExpiryHours);
                $query->where('created_at', '>=', $cutoffTime);
                
                Log::info('Applying award expiry time limit for mixx_payment box', [
                    'msisdn' => $msisdn,
                    'award_expiry_hours' => $awardExpiryHours,
                    'cutoff_time' => $cutoffTime->toDateTimeString()
                ]);
            }
            
            $latestMixxPaymentBox = $query->latest()->first();

            Log::info('Searching for latest mixx_payment mystery box', [
                'msisdn' => $msisdn,
                'found_box' => $latestMixxPaymentBox ? true : false,
                'box_id' => $latestMixxPaymentBox ? $latestMixxPaymentBox->id : null,
                'box_created_at' => $latestMixxPaymentBox && $latestMixxPaymentBox->created_at ? $latestMixxPaymentBox->created_at->toDateTimeString() : null,
                'award_expiry_enabled' => $awardExpiryEnabled,
                'award_expiry_hours' => $awardExpiryHours
            ]);

            if ($latestMixxPaymentBox) {
                // Open the box using MysteryBoxController
                $mysteryBoxController = new MysteryBoxController();
                $mysteryBoxController->openMysteryBox($msisdn, $latestMixxPaymentBox->id);

                Log::info('Successfully opened mixx_payment mystery box', [
                    'msisdn' => $msisdn,
                    'box_id' => $latestMixxPaymentBox->id,
                    'box_type' => $latestMixxPaymentBox->box_type
                ]);
            } else {
                // No mixx_payment mystery box found
                $noBoxMessage = config('messages.sms.no_mixx_payment_box_found', 'Hakuna Mystery Box ya Mixx Payment iliyopatikana kwa nambari yako.');
                $smsService->sendSms($msisdn, $noBoxMessage);

                Log::info('No mixx_payment mystery box found for MSISDN', [
                    'msisdn' => $msisdn
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error opening mixx_payment mystery box', [
                'msisdn' => $msisdn,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Send error message to user
            $errorMessage = config('messages.sms.box_opening_error', 'Kuna tatizo katika kufungua Mystery Box yako. Tafadhali jaribu tena baadaye.');
            $smsService->sendSms($msisdn, $errorMessage);
        }
    }
}