<?php

namespace App\Console\Commands;

use App\Models\Winner;
use App\Models\WinnerJackpotEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PopulateJackpotWinners extends Command
{
    protected $signature = 'winners:populate-jackpot {--draw-period= : Specific draw period (daily, weekly, monthly, grand)}';
    protected $description = 'Populate winners table from jackpot entries after draws are completed';

    public function handle()
    {
        $drawPeriod = $this->option('draw-period');
        
        try {
            $query = WinnerJackpotEntry::where('status', 'won');
            
            if ($drawPeriod) {
                $query->where('draw_period', $drawPeriod);
            }
            
            $jackpotEntries = $query->get();
            
            $this->info("Found {$jackpotEntries->count()} jackpot winners to process");
            
            $processed = 0;
            foreach ($jackpotEntries as $entry) {
                // Check if already exists in winners table
                $existingWinner = Winner::where('source_table', 'winner_jackpot_entries')
                    ->where('source_id', $entry->id)
                    ->first();
                
                if (!$existingWinner) {
                    Winner::create([
                        'msisdn' => $entry->msisdn,
                        'winner_type' => 'jackpot',
                        'prize_description' => $entry->draw_period . ' jackpot winner - ' . $entry->jackpot_amount . ' TZS',
                        'prize_value' => $entry->jackpot_amount,
                        'source_table' => 'winner_jackpot_entries',
                        'source_id' => $entry->id,
                        'box_id' => $entry->box_id,
                        'box_type' => $entry->box_type,
                        'draw_period' => $entry->draw_period,
                        'airtime_disbursement_status' => 'pending',
                        'wallet_disbursement_status' => 'pending',
                        'won_at' => $entry->draw_date ?? now(),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    
                    $processed++;
                    $this->info("Processed jackpot winner: {$entry->msisdn} - {$entry->draw_period} - {$entry->jackpot_amount} TZS");
                }
            }
            
            $this->info("Successfully processed {$processed} new jackpot winners");
            
        } catch (\Exception $e) {
            Log::error('Error populating jackpot winners: ' . $e->getMessage());
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}
