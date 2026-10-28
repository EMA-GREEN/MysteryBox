<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RenewalStop extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mysterybox:renewal:stop';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Stop renewal service by setting all renewal subscribers publish_status to 1';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $this->info('Starting renewal stop process...');
            
            // Update all renewal_subscribers publish_status to 1 (stops them from being published)
            $affectedRows = DB::table('renewal_subscribers')
                ->update(['publish_status' => 1]);
            
            $this->info("Successfully stopped {$affectedRows} renewal subscribers (publish_status set to 1)");
            
            // Log the operation
            Log::info('RenewalStop command executed successfully', [
                'affected_rows' => $affectedRows,
                'timestamp' => now()
            ]);
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Error during renewal stop: ' . $e->getMessage());
            
            Log::error('RenewalStop command failed', [
                'error' => $e->getMessage(),
                'timestamp' => now()
            ]);
            
            return Command::FAILURE;
        }
    }
}

