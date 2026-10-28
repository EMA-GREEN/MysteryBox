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
    protected $description = 'Reset renewal service by setting all renewal subscribers retry_count to 0 and publish_status to 0';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $this->info('Starting renewal reset process...');
            
            // Update all renewal_subscribers: reset retry_count to 0 and publish_status to 0
            $affectedRows = DB::table('renewal_subscribers')
                ->update([
                    'retry_count' => 0,
                    'publish_status' => 0
                ]);
            
            $this->info("Successfully reset {$affectedRows} renewal subscribers (retry_count = 0, publish_status = 0)");
            
            // Log the operation
            Log::info('RenewalReset command executed successfully', [
                'affected_rows' => $affectedRows,
                'timestamp' => now()
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
}

