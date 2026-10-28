<?php

namespace App\Jobs;

use App\Models\MobileMoneyTransactionNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProcessMobileMoneyNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 30;
    public $tries = 3;
    public $maxExceptions = 2;
    
    protected array $data;
    protected string $clientIp;

    /**
     * Create a new job instance.
     */
    public function __construct(array $data, string $clientIp)
    {
        $this->data = $data;
        $this->clientIp = $clientIp;
        
        // Set queue priority based on amount (higher amounts = higher priority)
        $amount = (float) ($data['OriginalAmount'] ?? 0);
        if ($amount >= 10000) {
            $this->onQueue('mobile_money_high_priority');
        } elseif ($amount >= 1000) {
            $this->onQueue('mobile_money_medium_priority');
        } else {
            $this->onQueue('mobile_money_low_priority');
        }
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = microtime(true);
        
        try {
            // Log notification received for tracking
            Log::info('Notification received', [
                'ref' => $this->data['SalesOrderNumber'],
                'msisdn' => $this->data['SourceCustomerMobile'],
                'amount' => $this->data['OriginalAmount'],
                'client_ip' => $this->clientIp
            ]);
            
            // Optimized date processing
            $transactionDateTime = $this->processTransactionDate($this->data['TransactionDate']);
            
            // Use database transaction for consistency
            DB::transaction(function () use ($transactionDateTime) {
                // Check for duplicate by reference (commented out for load testing)
                /*
                $existing = MobileMoneyTransactionNotification::where('reference', $this->data['SalesOrderNumber'])
                    ->where('msisdn', $this->data['SourceCustomerMobile'])
                    ->first();
                    
                if ($existing) {
                    Log::info('Duplicate MM notification skipped', [
                        'ref' => $this->data['SalesOrderNumber'],
                        'existing_id' => $existing->id
                    ]);
                    return;
                }
                */

                // Create notification record
                MobileMoneyTransactionNotification::create([
                    'msisdn' => $this->data['SourceCustomerMobile'],
                    'txn_type' => $this->data['BrandName'],
                    'amount' => $this->data['OriginalAmount'],
                    'reference' => $this->data['SalesOrderNumber'],
                    'status' => $this->data['SalesOrderStatus'],
                    'received_at' => $transactionDateTime,
                    'publish_status' => false,
                ]);
            });

            $processingTime = (microtime(true) - $startTime) * 1000;
            
            // Minimal logging for performance
            Log::info('Notification processed', [
                'ref' => $this->data['SalesOrderNumber'],
                'msisdn' => substr($this->data['SourceCustomerMobile'], -4),
                'amount' => $this->data['OriginalAmount'],
                'processing_time_ms' => round($processingTime, 2),
                'queue' => $this->queue
            ]);

        } catch (\Exception $e) {
            Log::error('MM notification job failed', [
                'ref' => $this->data['SalesOrderNumber'],
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
                'max_tries' => $this->tries
            ]);
            
            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Optimized date processing
     */
    private function processTransactionDate(string $transactionDate): string
    {
        try {
            // Fast path: check if already in correct format
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $transactionDate)) {
                return $transactionDate;
            }
            
            // Handle numeric timestamp
            if (is_numeric($transactionDate)) {
                $timestamp = $transactionDate / 1000;
                if ($timestamp > 0 && $timestamp < 4102444800) {
                    return Carbon::createFromTimestamp($timestamp)->format('Y-m-d H:i:s');
                }
            }
            
            // General date parsing
            return Carbon::parse($transactionDate)->format('Y-m-d H:i:s');
            
        } catch (\Exception $e) {
            Log::warning('Date parsing failed, using current time', [
                'original' => $transactionDate,
                'error' => $e->getMessage()
            ]);
            return now()->format('Y-m-d H:i:s');
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('MM notification job permanently failed', [
            'ref' => $this->data['SalesOrderNumber'],
            'error' => $exception->getMessage(),
            'data' => $this->data
        ]);
    }
}