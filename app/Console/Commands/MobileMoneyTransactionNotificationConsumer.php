<?php

namespace App\Console\Commands;

use App\Models\BillPaymentNotification;
use App\Models\Subscriber;
use App\Models\MysteryBoxAwardNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class MobileMoneyTransactionNotificationConsumer extends Command
{
    protected $signature = 'mobile-money-transaction:consume-notifications';
    protected $description = 'Consume mobile money transaction notifications from RabbitMQ';

    public function handle()
    {
        $this->info('Starting Mobile Money Transaction Notification Consumer...');

        try {
            $connection = new AMQPStreamConnection(
                config('rabbitmq.host'),
                config('rabbitmq.port'),
                config('rabbitmq.user'),
                config('rabbitmq.password')
            );
            $channel = $connection->channel();

            $queueName = config('queue_settings.mobile_money_transaction_notification.consumer.queue_name');
            $channel->queue_declare($queueName, false, true, false, false);
            $channel->basic_qos(null, 1, null);

            $this->info('Waiting for mobile money transaction notifications...');

            $callback = function (AMQPMessage $message) {
                try {
                    $data = json_decode($message->getBody(), true);
                    
                    // Log received data for debugging
                    Log::info('Processing mobile money transaction notification', [
                        'data' => $data
                    ]);
                    
                    // Validate required fields
                    if (!isset($data['msisdn']) || !isset($data['amount']) || !isset($data['id'])) {
                        Log::warning('Missing required fields in notification data', [
                            'data' => $data,
                            'required_fields' => ['msisdn', 'amount', 'id']
                        ]);
                        $message->ack();
                        return;
                    }
                    $date = date("YmdHis");

                    $msisdn = $data['msisdn'];
                    
                    if (!$msisdn) {
                        Log::warning('No msisdn found in notification data', ['data' => $data]);
                        $message->ack();
                        return;
                    }

                    // Validate and convert amount to float
                    $amount = $data['amount'] ?? 0;
                    if (is_string($amount)) {
                        $amount = (float) $amount;
                    }
                    
                    if ($amount <= 0) {
                        Log::warning('Invalid amount in notification data', ['data' => $data, 'amount' => $amount]);
                        $message->ack();
                        return;
                    }
                    
                    // Convert msisdn to bigint for database compatibility
                    $msisdnBigint = (int) $msisdn;
                    
                    // Validate msisdn format (should be a valid phone number)
                    if ($msisdnBigint <= 0 || strlen($msisdn) < 10) {
                        Log::warning('Invalid msisdn format', [
                            'msisdn' => $msisdn,
                            'msisdn_bigint' => $msisdnBigint,
                            'data' => $data
                        ]);
                        $message->ack();
                        return;
                    }

                     // Find or create subscriber with proper defaults
                     $subscriber = Subscriber::findOrCreate($msisdnBigint, [
                         'record_date' => now(),
                         'activity_status' => 'INACTIVE',
                         'dnd_status' => 'OFF',
                         'sub_channel' => 'SMS'
                     ]);
                    
                     // Check if subscriber was just created or already existed
                     if ($subscriber->wasRecentlyCreated) {
                         // New subscriber - set initial values and increment for first transaction
                         $subscriber->txns_count = 1;
                         $subscriber->total_txns_amount = $amount;
                         
                        //  Log::info('New subscriber created with first transaction', [
                        //      'msisdn' => $msisdn,
                        //      'txns_count' => $subscriber->txns_count,
                        //      'total_txns_amount' => $subscriber->total_txns_amount
                        //  ]);
                     } else {
                         // Log current values before updating
                        //  Log::info('Existing subscriber - values before update', [
                        //      'msisdn' => $msisdn,
                        //      'current_txns_count' => $subscriber->txns_count,
                        //      'current_total_txns_amount' => $subscriber->total_txns_amount,
                        //      'new_transaction_amount' => $amount
                        //  ]);
                         
                         // Existing subscriber - increment the values
                         $subscriber->txns_count = ($subscriber->txns_count ?? 0) + 1;
                         
                         // Use bcmath for precise decimal arithmetic, fallback to regular addition
                         if (extension_loaded('bcmath')) {
                             $subscriber->total_txns_amount = bcadd($subscriber->total_txns_amount ?? '0.00', (string) $amount, 2);
                         } else {
                             $subscriber->total_txns_amount = round(($subscriber->total_txns_amount ?? 0.00) + $amount, 2);
                         }
                         
                        //  Log::info('Existing subscriber updated', [
                        //      'msisdn' => $msisdn,
                        //      'old_txns_count' => ($subscriber->txns_count ?? 0) - 1,
                        //      'new_txns_count' => $subscriber->txns_count,
                        //      'old_total_amount' => bcsub($subscriber->total_txns_amount ?? '0.00', (string) $amount, 2),
                        //      'new_total_amount' => $subscriber->total_txns_amount
                        //  ]);
                     }
                    
                    // Update today's transaction amount for bill payment tracking
                    $today = now()->format('Y-m-d');
                    $lastInteractionDate = $subscriber->last_interaction ? $subscriber->last_interaction->format('Y-m-d') : null;
                    $lastAwardedDate = $subscriber->last_awarded_box_date ? $subscriber->last_awarded_box_date->format('Y-m-d') : null;
                    
                    // Reset today_txns_amount if it's a new day (based on last transaction, not last box award)
                    if ($lastInteractionDate !== $today) {
                        $subscriber->today_txns_amount = 0;
                    }
                    
                    // Update last_interaction to current time for accurate daily tracking
                    $subscriber->last_interaction = now();
                    
                    // Increment today's transaction amount
                    if (extension_loaded('bcmath')) {
                        $subscriber->today_txns_amount = bcadd($subscriber->today_txns_amount ?? '0.00', (string) $amount, 2);
                    } else {
                        $subscriber->today_txns_amount = round(($subscriber->today_txns_amount ?? 0.00) + $amount, 2);
                    }
                    
                    $subscriber->save();
                    
                    // Log subscriber update summary
                    // Log::info('Subscriber processing completed', [
                    //     'msisdn' => $msisdn,
                    //     'msisdn_bigint' => $msisdnBigint,
                    //     'txns_count' => $subscriber->txns_count,
                    //     'total_txns_amount' => $subscriber->total_txns_amount,
                    //     'today_txns_amount' => $subscriber->today_txns_amount,
                    //     'was_created' => $subscriber->wasRecentlyCreated
                    // ]);
                    
                    $bills = $subscriber->txns_count;
                    $totalAmount = $subscriber->total_txns_amount;
                    $todayAmount = $subscriber->today_txns_amount;
                    
                    // Check for BillPay box award (once per day for bill payments)
                    $awardCriteria = config('boxes.box_award_criteria', []);
                    $boxType = null;
                    
                    // Check if subscriber qualifies for BillPay box today
                    if (!empty($awardCriteria) && isset($awardCriteria['min_amount']) && isset($awardCriteria['box'])) {
                        $minAmount = $awardCriteria['min_amount'];
                        $billPayBoxName = $awardCriteria['box'];
                        
                        // Check if today's transaction amount meets criteria and hasn't been awarded today
                        if ($todayAmount >= $minAmount && $lastAwardedDate !== $today) {
                            $boxType = $billPayBoxName;
                            
                            Log::info('BillPay box criteria met', [
                                'msisdn' => $msisdn,
                                'today_amount' => $todayAmount,
                                'min_amount' => $minAmount,
                                'last_awarded_date' => $lastAwardedDate,
                                'today' => $today,
                                'box_type' => $boxType
                            ]);
                        }
                    }

                    $requestId = "MMNYTXN".$date.$msisdn;

                    if ($boxType) {
                        try {
                            // ===== TEST MODE IMPLEMENTATION - START =====
                            // Comment out this entire block to disable test mode restrictions
                            $testModeEnabled = config('test_mode.enabled', false);
                            $whitelistedMsisdns = config('test_mode.msisdn_whitelist', []);
                            $shouldProcessAward = !$testModeEnabled || in_array($msisdn, $whitelistedMsisdns);
                            if (!$shouldProcessAward) {
                                Log::info('Award skipped due to test mode restrictions', [
                                    'msisdn' => $msisdn,
                                    'box_type' => $boxType,
                                    'test_mode_enabled' => $testModeEnabled,
                                    'is_whitelisted' => in_array($msisdn, $whitelistedMsisdns),
                                    'whitelisted_msisdns' => $whitelistedMsisdns
                                ]);
                                $message->ack();
                                return; // Skip award processing for non-whitelisted MSISDNs in test mode
                            }
                            // ===== TEST MODE IMPLEMENTATION - END =====
                            
                            MysteryBoxAwardNotification::create([
                                'msisdn' => $msisdn,
                                'box_type' => $boxType,
                                'request_id' => $requestId,
                                'status' => 'awarded',
                                'publish_status' => false
                            ]);
                            
                            // Update subscriber's award tracking
                            $subscriber->last_awarded_box = $boxType;
                            $subscriber->last_awarded_box_date = now();
                            $subscriber->save();
                            
                            Log::info('BillPay MysteryBox Award Notification created successfully', [
                                'msisdn' => $msisdn,
                                'box_type' => $boxType,
                                'request_id' => $requestId,
                                'today_amount' => $todayAmount,
                                'awarded_date' => $subscriber->last_awarded_box_date
                            ]);
                        } catch (\Exception $e) {
                            Log::error('Failed to create mystery box award notification', [
                                'msisdn' => $msisdn,
                                'box_type' => $boxType,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }

                    $message->ack();
                    $this->info("Processed mobile money transaction notification for $msisdn, Amount: $amount, Today Amount: $todayAmount, Total Amount: $totalAmount, Box awarded: " . ($boxType ?? 'none'));
                    
                    Log::info('Mobile money transaction notification processed successfully', [
                        'msisdn' => $msisdn,
                        'amount' => $amount,
                        'today_amount' => $todayAmount,
                        'total_amount' => $totalAmount,
                        'box_awarded' => $boxType,
                        'transaction_id' => $data['id'],
                        'last_awarded_date' => $lastAwardedDate
                    ]);
                } catch (\Exception $e) {
                    Log::error('Error processing mobile money transaction notification: ' . $e->getMessage(), [
                        'data' => $data ?? null,
                        'trace' => $e->getTraceAsString()
                    ]);
                    $this->error('Error: ' . $e->getMessage());
                }
            };

            $channel->basic_consume($queueName, '', false, false, false, false, $callback);

            while ($channel->is_consuming()) {
                $channel->wait();
            }

            $channel->close();
            $connection->close();
        } catch (\Exception $e) {
            Log::error('Error in mobile money transaction notification consumer: ' . $e->getMessage());
            $this->error('Error: ' . $e->getMessage());
        }
    }
}