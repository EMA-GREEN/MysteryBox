<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RenewalPayment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class FixDeliveryImpossiblePayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'renewal:fix-delivery-impossible {--date=} {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix renewal payments with DeliveryImpossible reason from log file';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $date = $this->option('date') ?: Carbon::today()->format('Y-m-d');
        $dryRun = $this->option('dry-run');
        
        $this->info("Processing logs for date: {$date}");
        
        if ($dryRun) {
            $this->warn("DRY RUN MODE - No database updates will be made");
        }

        $logPath = storage_path('logs/laravel.log');
        
        if (!File::exists($logPath)) {
            $this->error("Log file not found: {$logPath}");
            return Command::FAILURE;
        }

        $this->info("Reading log file line by line (this may take a while for large files)...");
        
        // Get file size for progress tracking
        $fileSize = filesize($logPath);
        $fileSizeMB = round($fileSize / 1024 / 1024, 2);
        $this->info("Log file size: {$fileSizeMB} MB");
        
        // Read file line by line to avoid memory issues
        $requestIds = [];
        $processedCount = 0;
        $totalLines = 0;
        $datePattern = '/\[' . preg_quote($date, '/') . '/';
        $lastProgressTime = time();
        $bytesRead = 0;
        
        $handle = fopen($logPath, 'r');
        if (!$handle) {
            $this->error("Could not open log file: {$logPath}");
            return Command::FAILURE;
        }
        
        while (($line = fgets($handle)) !== false) {
            $totalLines++;
            $bytesRead += strlen($line);
            
            // Show progress every 10 seconds
            $currentTime = time();
            if ($currentTime - $lastProgressTime >= 10) {
                $progressMB = round($bytesRead / 1024 / 1024, 2);
                $progressPercent = $fileSize > 0 ? round(($bytesRead / $fileSize) * 100, 2) : 0;
                $this->info("Progress: {$progressMB} MB ({$progressPercent}%) - Found {$processedCount} callbacks, {$totalLines} lines processed");
                $lastProgressTime = $currentTime;
            }
            
            // Check if line is from the specified date
            if (preg_match($datePattern, $line)) {
                // Check if this line contains "Received payment callback"
                if (strpos($line, 'Received payment callback') !== false) {
                    $processedCount++;
                    
                    // Extract datetime from log line (format: [2025-12-05 10:30:45])
                    $datetime = null;
                    if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $datetimeMatches)) {
                        $datetime = $datetimeMatches[1];
                    }
                    
                    // Try to extract JSON from this line
                    $this->extractDeliveryImpossibleFromLine($line, $requestIds, $datetime);
                }
            }
        }
        
        fclose($handle);
        
        $this->info("Finished reading log file. Total lines processed: {$totalLines}");
        
        $this->info("\nTotal log lines processed: {$processedCount}");
        $this->info("Found " . count($requestIds) . " callbacks with DeliveryImpossible");
        
        if (empty($requestIds)) {
            $this->info("No DeliveryImpossible callbacks found for date: {$date}");
            return Command::SUCCESS;
        }
        
        // Display found request IDs
        $this->table(
            ['Request ID'],
            array_map(fn($id) => [$id], $requestIds)
        );
        
        if ($dryRun) {
            $this->warn("\nDRY RUN: Would update " . count($requestIds) . " records");
            return Command::SUCCESS;
        }
        
        // Update renewal_payments table
        $this->info("\nUpdating renewal_payments table...");
        $updatedCount = 0;
        $notFoundCount = 0;
        
        foreach ($requestIds as $requestId) {
            $payment = RenewalPayment::where('request_id', $requestId)->first();
            
            if ($payment) {
                $payment->update([
                    'status' => 'failed',
                    'callback_description' => 'DeliveryImpossible',
                    'callback_received_time' => now()
                ]);
                $updatedCount++;
                $this->info("Updated: {$requestId}");
            } else {
                $notFoundCount++;
                $this->warn("Not found in renewal_payments: {$requestId}");
            }
        }
        
        $this->info("\n=== Summary ===");
        $this->info("Total callbacks found: " . count($requestIds));
        $this->info("Successfully updated: {$updatedCount}");
        $this->info("Not found in database: {$notFoundCount}");
        
        Log::info('FixDeliveryImpossiblePayments command executed', [
            'date' => $date,
            'total_found' => count($requestIds),
            'updated' => $updatedCount,
            'not_found' => $notFoundCount
        ]);
        
        return Command::SUCCESS;
    }
    
    /**
     * Extract DeliveryImpossible callbacks from a log line
     */
    private function extractDeliveryImpossibleFromLine($line, &$requestIds, $datetime = null)
    {
        // Extract JSON from the line
        $jsonStart = strpos($line, '{');
        if ($jsonStart === false) {
            return;
        }
        
        $jsonString = substr($line, $jsonStart);
        
        // Prepare datetime prefix for logging
        $datetimePrefix = $datetime ? "[{$datetime}] " : "";
        
        // Try to find requestId and Reason using regex (more memory efficient than full JSON decode)
        if (preg_match('/"requestId"\s*:\s*"([^"]+)"/', $jsonString, $reqMatches)) {
            $requestId = $reqMatches[1];
            
            // Check if Reason exists with DeliveryImpossible
            if (preg_match('/"name"\s*:\s*"Reason"[^}]*"value"\s*:\s*"([^"]+)"/', $jsonString, $reasonMatches)) {
                $reason = $reasonMatches[1];
                
                if ($reason === 'DeliveryImpossible') {
                    $requestIds[] = $requestId;
                    $this->info("{$datetimePrefix}Found DeliveryImpossible callback: {$requestId}");
                }
            } else {
                // Alternative pattern - Reason might be in requestParam.data array
                if (preg_match('/"Reason"[^}]*"value"\s*:\s*"DeliveryImpossible"/', $jsonString)) {
                    $requestIds[] = $requestId;
                    $this->info("{$datetimePrefix}Found DeliveryImpossible callback: {$requestId}");
                }
            }
        }
    }
}

