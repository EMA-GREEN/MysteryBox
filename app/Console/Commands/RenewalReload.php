<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RenewalReload extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mysterybox:renewal:reload';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reload renewal subscribers from active subscribers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $this->info('Starting renewal reload process...');
            
            // First, truncate the renewal_subscribers table
            DB::table('renewal_subscribers')->truncate();
            $this->info('Truncated renewal_subscribers table');
            
            // Process subscribers in chunks to handle large datasets efficiently
            $readChunkSize = 10000;  // Read larger chunks from source
            $insertBatchSize = 100000; // Insert in much larger batches
            $totalInserted = 0;
            $currentTime = now();
            $pendingInserts = []; // Buffer for batching inserts
            
            // First, get the total count for progress tracking
            $totalCount = DB::table('subscribers')
                ->where('activity_status', 'ACTIVE')
                ->where('dnd_status', 'OFF')
                ->count();
            
            if ($totalCount === 0) {
                $this->info('No active subscribers found to reload');
                return Command::SUCCESS;
            }
            
            $this->info("Processing {$totalCount} active subscribers");
            $this->info("Reading in chunks of {$readChunkSize}, inserting in batches of {$insertBatchSize}");
            
            // Calculate one week ago timestamp once for efficiency
            $oneWeekAgo = \Carbon\Carbon::now()->subWeek();
            
            // Process subscribers in chunks to avoid memory issues
            DB::table('subscribers')
                ->select(['msisdn', 'activity_status', 'dnd_status', 'last_renewal_billing', 'record_date'])
                ->where('activity_status', 'ACTIVE')
                ->where('dnd_status', 'OFF')
                ->orderBy('last_renewal_billing', 'desc')
                ->chunk($readChunkSize, function ($subscribers) use (&$totalInserted, $currentTime, &$pendingInserts, $insertBatchSize, $oneWeekAgo) {
                    // Prepare data for insertion
                    foreach ($subscribers as $subscriber) {
                        // Calculate priority based on last_renewal_billing
                        $priority = 2; // Default priority
                        if ($subscriber->last_renewal_billing) {
                            $lastRenewalDate = \Carbon\Carbon::parse($subscriber->last_renewal_billing);
                            
                            // If last renewal billing is within 1 week, priority is 1, else priority is 2
                            $priority = $lastRenewalDate->gte($oneWeekAgo) ? 1 : 2;
                        }
                        
                        $pendingInserts[] = [
                            'msisdn' => $subscriber->msisdn,
                            'activity_status' => $subscriber->activity_status,
                            'dnd_status' => $subscriber->dnd_status,
                            'priority' => $priority,
                            'last_renewal_billing' => $subscriber->last_renewal_billing,
                            'retry_status' => 'OFF',
                            'retry_count' => 0,
                            'publish_status' => 0,
                            'record_date' => $subscriber->record_date ?? $currentTime,
                        ];
                    }
                    
                    // Insert when we reach the batch size
                    if (count($pendingInserts) >= $insertBatchSize) {
                        DB::table('renewal_subscribers')->insert($pendingInserts);
                        $totalInserted += count($pendingInserts);
                        $this->info("Bulk inserted batch: {$totalInserted} records processed so far");
                        $pendingInserts = []; // Clear the buffer
                    }
                });
            
            // Insert any remaining records
            if (!empty($pendingInserts)) {
                DB::table('renewal_subscribers')->insert($pendingInserts);
                $totalInserted += count($pendingInserts);
                $this->info("Inserted final batch: " . count($pendingInserts) . " records");
            }
            
            $this->info("Successfully reloaded {$totalInserted} renewal subscribers from active subscribers");
            
            // Reset for next cycle after successful reload
            $this->resetForNextCycle();
            
            // Log the operation
            Log::info('RenewalReload command executed successfully', [
                'total_inserted' => $totalInserted,
                'timestamp' => now()
            ]);
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Error during renewal reload: ' . $e->getMessage());
            
            Log::error('RenewalReload command failed', [
                'error' => $e->getMessage(),
                'timestamp' => now()
            ]);
            
            return Command::FAILURE;
        }
    }

    /**
     * Reset tracking for next renewal cycle
     */
    private function resetForNextCycle()
    {
        $today = now()->format('Y-m-d');
        
        // Check if we need to reset for a new cycle
        $lastReloadDate = $this->getLastReloadDate();
        
        if ($lastReloadDate !== $today) {
            $this->saveLastReloadDate($today);
            $this->info("Reset for new renewal cycle: $today");
            
            // Reset any cycle-specific tracking files
            $this->resetCycleTrackingFiles();
        }
    }

    /**
     * Get the last reload date from tracking file
     */
    private function getLastReloadDate()
    {
        $trackingFile = config('renewal.tracking_files.reload_command.tracking');
        
        if (!file_exists($trackingFile)) {
            return null;
        }
        
        $data = json_decode(file_get_contents($trackingFile), true);
        return $data['last_reload_date'] ?? null;
    }

    /**
     * Save the last reload date to tracking file
     */
    private function saveLastReloadDate($date)
    {
        $trackingFile = config('renewal.tracking_files.reload_command.tracking');
        
        $data = [
            'last_reload_date' => $date,
            'updated_at' => now()->toISOString()
        ];
        
        file_put_contents($trackingFile, json_encode($data));
    }

    /**
     * Reset cycle-specific tracking files
     */
    private function resetCycleTrackingFiles()
    {
        $trackingFiles = [
            config('renewal.tracking_files.priority_publisher.retry_tracking'),
            config('renewal.tracking_files.priority_publisher.last_date'),
            config('renewal.tracking_files.low_priority_publisher.retry_tracking'),
            config('renewal.tracking_files.low_priority_publisher.last_date')
        ];

        foreach ($trackingFiles as $file) {
            if (file_exists($file)) {
                // Reset retry tracking to 0
                if (strpos($file, 'retry_tracking') !== false) {
                    $resetData = [
                        'current_retry' => 0,
                        'updated_at' => now()->toISOString()
                    ];
                    file_put_contents($file, json_encode($resetData));
                }
                
                // Reset date tracking to today
                if (strpos($file, 'last_date') !== false) {
                    $resetData = [
                        'last_date' => now()->format('Y-m-d'),
                        'updated_at' => now()->toISOString()
                    ];
                    file_put_contents($file, json_encode($resetData));
                }
            }
        }
        
        $this->info("Reset cycle tracking files for new renewal cycle");
    }
}