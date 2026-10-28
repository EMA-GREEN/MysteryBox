<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RenewalReset extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mysterybox:renewal:reset';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset renewal service by setting all renewal subscribers retry_count to 0, publish_status to 0, and priority based on last_renewal_billing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startTime = microtime(true);
        $startDateTime = now();
        
        try {
            $this->info('Starting renewal reset process...');
            
            // Calculate one week ago timestamp for priority calculation
            $oneWeekAgo = \Carbon\Carbon::now()->subWeek()->format('Y-m-d H:i:s');
            
            $this->info('Executing UPDATE query on renewal_subscribers table (this may take several minutes for large datasets)...');
            
            // Update all renewal_subscribers: reset retry_count to 0, publish_status to 0, and set priority
            // Priority: 1 if last_renewal_billing is within 1 week, else 2
            $queryStartTime = microtime(true);
            $affectedRows = DB::table('renewal_subscribers')
                ->update([
                    'retry_count' => 0,
                    'publish_status' => 0,
                    'priority' => DB::raw("CASE 
                        WHEN last_renewal_billing IS NOT NULL AND last_renewal_billing >= '{$oneWeekAgo}' THEN 1 
                        ELSE 2 
                    END")
                ]);
            $queryEndTime = microtime(true);
            $queryDuration = round($queryEndTime - $queryStartTime, 2);
            
            $endTime = microtime(true);
            $totalDuration = round($endTime - $startTime, 2);
            
            $this->info("Successfully reset {$affectedRows} renewal subscribers (retry_count = 0, publish_status = 0, priority updated)");
            $this->info("Query execution time: {$queryDuration} seconds");

            // Reset current_retry and last_date for new day (Priority and Low Priority publishers)
            $this->resetPublisherTrackingForNewDay();
            
            $this->info("Total command execution time: {$totalDuration} seconds");
            
            // Log the operation with timing information
            Log::info('RenewalReset command executed successfully', [
                'affected_rows' => $affectedRows,
                'query_duration_seconds' => $queryDuration,
                'total_duration_seconds' => $totalDuration,
                'start_time' => $startDateTime->toISOString(),
                'end_time' => now()->toISOString(),
                'records_per_second' => $affectedRows > 0 ? round($affectedRows / $queryDuration, 2) : 0
            ]);
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Error during renewal reset: ' . $e->getMessage());
            
            Log::error('RenewalReset command failed', [
                'error' => $e->getMessage(),
                'timestamp' => now()
            ]);
            
            return Command::FAILURE;
        }
    }

    /**
     * Reset current_retry and last_date in tracking files for both Priority and Low Priority publishers.
     * Ensures publishers start fresh each new day regardless of whether all retry levels completed.
     */
    private function resetPublisherTrackingForNewDay(): void
    {
        $today = now()->format('Y-m-d');
        $trackingFiles = [
            config('renewal.tracking_files.priority_publisher.retry_tracking'),
            config('renewal.tracking_files.priority_publisher.last_date'),
            config('renewal.tracking_files.low_priority_publisher.retry_tracking'),
            config('renewal.tracking_files.low_priority_publisher.last_date'),
        ];

        foreach ($trackingFiles as $file) {
            $fullPath = str_starts_with($file, '/') ? $file : base_path($file);
            $directory = dirname($fullPath);

            if (!is_dir($directory)) {
                @mkdir($directory, 0755, true);
            }

            $updatedAt = now()->format('Y-m-d H:i:s');
            if (strpos($file, 'retry_tracking') !== false) {
                $data = [
                    'current_retry' => 0,
                    'updated_at' => $updatedAt,
                ];
            } else {
                $data = [
                    'last_date' => $today,
                    'updated_at' => $updatedAt,
                ];
            }

            file_put_contents($fullPath, json_encode($data));
        }

        $this->info("Reset publisher tracking for new day: current_retry=0, last_date={$today}");
        Log::info('RenewalReset: Publisher tracking files reset for new day', [
            'date' => $today,
            'files_reset' => $trackingFiles,
        ]);
    }
}

