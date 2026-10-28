<?php

namespace App\Console\Commands;

use App\Models\WalletDisbursementCallback;
use App\Models\AirtimeDisbursementCallback;
use App\Services\SmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class CheckDisbursementBalance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'balance:check-disbursement';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check disbursement account balance and send notifications when balance is low';

    /**
     * SMS Service instance
     */
    protected $smsService;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
        $this->smsService = new SmsService();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking disbursement account balance...');

        try {
            $threshold = config('balance_monitoring.threshold', 200000);
            $lookbackMinutes = config('balance_monitoring.check.lookback_minutes', 30);
            $status = config('balance_monitoring.check.status', 'COMPLETED');
            $notificationMsisdns = config('balance_monitoring.notification_msisdns', []);
            $cooldownMinutes = config('balance_monitoring.notification.cooldown_minutes', 60);

            if (empty($notificationMsisdns)) {
                $this->warn('No notification MSISDNs configured. Skipping balance check.');
                return Command::SUCCESS;
            }

            // Get the most recent COMPLETED callback from wallet disbursements
            $walletCallback = WalletDisbursementCallback::where('status', $status)
                ->where('callback_received_at', '>=', now()->subMinutes($lookbackMinutes))
                ->orderBy('id', 'desc')
                ->first();

            // Get the most recent COMPLETED callback from airtime disbursements
            $airtimeCallback = AirtimeDisbursementCallback::where('status', $status)
                ->where('callback_received_at', '>=', now()->subMinutes($lookbackMinutes))
                ->orderBy('id', 'desc')
                ->first();

            // Determine which callback is more recent
            $latestCallback = null;
            if ($walletCallback && $airtimeCallback) {
                $latestCallback = $walletCallback->callback_received_at > $airtimeCallback->callback_received_at 
                    ? $walletCallback 
                    : $airtimeCallback;
            } elseif ($walletCallback) {
                $latestCallback = $walletCallback;
            } elseif ($airtimeCallback) {
                $latestCallback = $airtimeCallback;
            }

            if (!$latestCallback) {
                $this->info("No recent {$status} disbursement callbacks found in the last {$lookbackMinutes} minutes.");
                return Command::SUCCESS;
            }

            // Extract balance from raw_response
            $balance = $this->extractBalance($latestCallback);

            if ($balance === null) {
                $this->warn('Could not extract balance from callback. Raw response: ' . json_encode($latestCallback->raw_response));
                return Command::SUCCESS;
            }

            $this->info("Current balance: TZS " . number_format($balance, 2));
            $this->info("Threshold: TZS " . number_format($threshold, 2));

            // Check if balance is below threshold
            if ($balance <= $threshold) {
                // Check if we should send notification (cooldown check)
                $cacheKey = 'balance_notification_last_sent';
                $lastNotificationTime = Cache::get($cacheKey);
                $lastNotifiedBalance = Cache::get('balance_notification_last_balance');

                $shouldNotify = false;

                if (!$lastNotificationTime) {
                    // Never notified before
                    $shouldNotify = true;
                    $this->info('First time balance is below threshold. Sending notifications.');
                } elseif (now()->diffInMinutes($lastNotificationTime) >= $cooldownMinutes) {
                    // Cooldown period has passed
                    $shouldNotify = true;
                    $this->info('Cooldown period has passed. Sending notifications.');
                } elseif ($lastNotifiedBalance !== null && abs($balance - $lastNotifiedBalance) > ($threshold * 0.1)) {
                    // Balance has changed significantly (more than 10% of threshold)
                    $shouldNotify = true;
                    $this->info('Balance has changed significantly. Sending notifications.');
                } else {
                    $this->info('Balance is below threshold, but notification was sent recently. Skipping.');
                }

                if ($shouldNotify) {
                    $this->sendNotifications($balance, $threshold, $notificationMsisdns);
                    
                    // Update cache
                    Cache::put($cacheKey, now(), now()->addHours(24));
                    Cache::put('balance_notification_last_balance', $balance, now()->addHours(24));
                }
            } else {
                $this->info('Balance is above threshold. No action needed.');
                
                // Reset notification tracking if balance is back above threshold
                $lastNotifiedBalance = Cache::get('balance_notification_last_balance');
                if ($lastNotifiedBalance !== null && $lastNotifiedBalance <= $threshold) {
                    $this->info('Balance is back above threshold. Resetting notification tracking.');
                    Cache::forget('balance_notification_last_sent');
                    Cache::forget('balance_notification_last_balance');
                }
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error checking disbursement balance: ' . $e->getMessage());
            Log::error('Error in CheckDisbursementBalance command', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Extract balance from callback raw_response
     *
     * @param \App\Models\WalletDisbursementCallback|\App\Models\AirtimeDisbursementCallback $callback
     * @return float|null
     */
    private function extractBalance($callback)
    {
        if (!$callback->raw_response) {
            return null;
        }

        // raw_response is already cast to array, so we can access it directly
        $rawResponse = $callback->raw_response;

        // Try to get available_balance from raw_response
        if (isset($rawResponse['available_balance'])) {
            return (float) $rawResponse['available_balance'];
        }

        // Fallback: try balance field if available
        if (isset($rawResponse['balance'])) {
            return (float) $rawResponse['balance'];
        }

        // Fallback: use the balance column if raw_response doesn't have it
        if ($callback->balance !== null) {
            return (float) $callback->balance;
        }

        return null;
    }

    /**
     * Send notifications to configured MSISDNs
     *
     * @param float $balance
     * @param float $threshold
     * @param array $msisdns
     * @return void
     */
    private function sendNotifications($balance, $threshold, array $msisdns)
    {
        $messageTemplate = config('balance_monitoring.notification.message_template', 
            'ALERT: Disbursement account balance is TZS {balance}. Balance is below threshold of TZS {threshold}.');
        
        $message = str_replace(
            ['{balance}', '{threshold}'],
            [number_format($balance, 2), number_format($threshold, 2)],
            $messageTemplate
        );

        $successCount = 0;
        $failCount = 0;

        foreach ($msisdns as $msisdn) {
            try {
                $result = $this->smsService->sendSms($msisdn, $message);
                if ($result) {
                    $successCount++;
                    $this->info("Sent notification to {$msisdn}");
                    Log::info('Balance alert notification sent', [
                        'msisdn' => $msisdn,
                        'balance' => $balance,
                        'threshold' => $threshold
                    ]);
                } else {
                    $failCount++;
                    $this->warn("Failed to send notification to {$msisdn}");
                    Log::warning('Failed to send balance alert notification', [
                        'msisdn' => $msisdn,
                        'balance' => $balance,
                        'threshold' => $threshold
                    ]);
                }
            } catch (\Exception $e) {
                $failCount++;
                $this->error("Error sending notification to {$msisdn}: " . $e->getMessage());
                Log::error('Error sending balance alert notification', [
                    'msisdn' => $msisdn,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info("Notifications sent: {$successCount} successful, {$failCount} failed");
    }
}

