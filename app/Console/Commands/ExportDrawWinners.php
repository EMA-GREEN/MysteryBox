<?php

namespace App\Console\Commands;

use App\Models\WinnerJackpotEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ExportDrawWinners extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'winners:export-draws {--period= : Specific draw period (daily, weekly, monthly). If not specified, exports all periods}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Select and export top N jackpot entries for daily, weekly, and monthly draws to CSV files. Only rank 1 is the actual winner for the period.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting draw winners export...');
        
        $period = $this->option('period');
        $periods = $period ? [$period] : ['daily', 'weekly', 'monthly'];
        
        // Ensure storage directory exists
        $storagePath = config('draws.csv.directory', 'winners');
        $disk = Storage::disk('local');
        
        if (!$disk->exists($storagePath)) {
            $disk->makeDirectory($storagePath);
            $this->info("Created storage directory: storage/app/{$storagePath}");
        }
        
        // Use yesterday's date since this runs at 00:00 (start of new day)
        // This ensures we export winners from the completed period
        $date = Carbon::yesterday();
        $totalExported = 0;
        
        foreach ($periods as $drawPeriod) {
            try {
                $exported = $this->exportWinnersForPeriod($drawPeriod, $date);
                $totalExported += $exported;
            } catch (\Exception $e) {
                $this->error("Error exporting {$drawPeriod} winners: " . $e->getMessage());
                Log::error("Error exporting {$drawPeriod} winners", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
        
        $this->info("Successfully exported {$totalExported} winners across all periods");
        
        return Command::SUCCESS;
    }

    /**
     * Export winners for a specific draw period
     *
     * @param string $drawPeriod
     * @param Carbon $date
     * @return int Number of winners exported
     */
    private function exportWinnersForPeriod(string $drawPeriod, Carbon $date): int
    {
        $config = config("draws.winners.{$drawPeriod}");
        
        if (!$config) {
            $this->warn("No configuration found for {$drawPeriod} draw period");
            return 0;
        }
        
        $topN = $config['top_n'] ?? 10;
        
        // Determine the draw_date for this period based on the date
        $drawDate = $this->getDrawDateForPeriod($drawPeriod, $date);
        
        // Check if we should process this draw period today
        if (!$this->shouldProcessDraw($drawPeriod, $date, $drawDate)) {
            $this->info("Skipping {$drawPeriod} draw - not the draw date. Draw date: {$drawDate->format('Y-m-d')}, Yesterday: {$date->format('Y-m-d')}");
            return 0;
        }
        
        $this->info("Selecting top {$topN} MSISDNs with highest records for {$drawPeriod} draw period (draw_date: {$drawDate->format('Y-m-d')})...");
        
        // Get top N MSISDNs with highest record counts for entries with this specific draw_date
        $topMsisdnsWithCounts = WinnerJackpotEntry::where('draw_period', $drawPeriod)
            ->where('status', 'pending')
            ->whereDate('draw_date', $drawDate->toDateString())
            ->selectRaw('msisdn, COUNT(*) as entry_count')
            ->groupBy('msisdn')
            ->orderBy('entry_count', 'desc')
            ->limit($topN)
            ->get();
        
        if ($topMsisdnsWithCounts->isEmpty()) {
            $this->warn("No entries found for {$drawPeriod} draw period with draw_date: {$drawDate->format('Y-m-d')}");
            return 0;
        }
        
        // Extract MSISDN list and create rank map
        $topMsisdns = $topMsisdnsWithCounts->pluck('msisdn')->toArray();
        $msisdnRankMap = [];
        $rank = 1;
        foreach ($topMsisdnsWithCounts as $item) {
            $msisdnRankMap[$item->msisdn] = $rank++;
        }
        
        // Get all entries for those top N MSISDNs with this specific draw_date
        $winners = WinnerJackpotEntry::where('draw_period', $drawPeriod)
            ->where('status', 'pending')
            ->whereIn('msisdn', $topMsisdns)
            ->whereDate('draw_date', $drawDate->toDateString())
            ->get();
        
        if ($winners->isEmpty()) {
            $this->warn("No winners found for {$drawPeriod} draw period");
            return 0;
        }
        
        // Generate CSV filename using draw_date
        $filename = $this->generateFilename($drawPeriod, $drawDate);
        $filePath = config('draws.csv.directory', 'winners') . '/' . $filename;
        
        // Generate CSV content
        $csvContent = $this->generateCsvContent($winners, $drawPeriod, $msisdnRankMap);
        
        // Save to storage
        $disk = Storage::disk('local');
        $disk->put($filePath, $csvContent);
        
        $fullPath = $disk->path($filePath);
        $this->info("Exported {$winners->count()} winners to: {$fullPath}");
        
        // After successful export, mark these jackpot entries as processed
        // NOTE: winner_jackpot_entries.status enum is ['pending', 'active', 'won', 'lost']
        // We use 'active' here to represent "processed" (no longer pending selection).
        $updatedCount = WinnerJackpotEntry::where('draw_period', $drawPeriod)
            ->where('status', 'pending')
            ->whereIn('msisdn', $topMsisdns)
            ->whereDate('draw_date', $drawDate->toDateString())
            ->update(['status' => 'active']);
        
        $this->info("Updated {$updatedCount} entries to 'active' status");
        
        Log::info("Exported {$drawPeriod} winners and marked entries as processed", [
            'period' => $drawPeriod,
            'count' => $winners->count(),
            'draw_date' => $drawDate->format('Y-m-d'),
            'filename' => $filename,
            'updated_entries' => $updatedCount
        ]);
        
        return $winners->count();
    }
    
    /**
     * Get the draw_date for a specific period based on the given date
     *
     * @param string $drawPeriod
     * @param Carbon $date (yesterday's date when script runs at 00:00)
     * @return Carbon
     */
    private function getDrawDateForPeriod(string $drawPeriod, Carbon $date): Carbon
    {
        switch ($drawPeriod) {
            case 'daily':
                // For daily, draw_date is yesterday (the completed day)
                return $date->copy();
            
            case 'weekly':
                // For weekly, draw_date is the end of the week (Sunday) that yesterday belongs to
                return $date->copy()->endOfWeek();
            
            case 'monthly':
                // For monthly, draw_date is the end of the month that yesterday belongs to
                return $date->copy()->endOfMonth();
            
            default:
                return $date->copy();
        }
    }
    
    /**
     * Check if we should process the draw for this period today
     *
     * @param string $drawPeriod
     * @param Carbon $date (yesterday's date when script runs at 00:00)
     * @param Carbon $drawDate
     * @return bool
     */
    private function shouldProcessDraw(string $drawPeriod, Carbon $date, Carbon $drawDate): bool
    {
        switch ($drawPeriod) {
            case 'daily':
                // Process daily draws every day (yesterday's date matches draw_date)
                return $date->format('Y-m-d') === $drawDate->format('Y-m-d');
            
            case 'weekly':
                // Process weekly draws only when yesterday was Sunday (end of week)
                // Since script runs at 00:00, we check if yesterday was Sunday
                return $date->isSunday();
            
            case 'monthly':
                // Process monthly draws only when yesterday was the last day of the month
                // Since script runs at 00:00, we check if yesterday was the last day of the month
                return $date->format('Y-m-d') === $date->copy()->endOfMonth()->format('Y-m-d');
            
            default:
                return false;
        }
    }


    /**
     * Generate CSV filename based on format
     *
     * @param string $drawPeriod
     * @param Carbon $date
     * @return string
     */
    private function generateFilename(string $drawPeriod, Carbon $date): string
    {
        $format = config('draws.csv.filename_format', '{draw_period}_winners_{date}.csv');
        
        return str_replace(
            ['{draw_period}', '{date}'],
            [$drawPeriod, $date->format('Y-m-d')],
            $format
        );
    }

    /**
     * Generate CSV content from winners collection
     *
     * @param \Illuminate\Database\Eloquent\Collection $winners
     * @param string $drawPeriod
     * @param array $msisdnRankMap Map of MSISDN to rank
     * @return string
     */
    private function generateCsvContent($winners, string $drawPeriod, array $msisdnRankMap = []): string
    {
        $includeHeaders = config('draws.csv.include_headers', true);
        
        $lines = [];
        
        // Add headers if enabled
        if ($includeHeaders) {
            $lines[] = [
                'MSISDN Rank',
                'MSISDN',
                'Entry Count',
                'Entry Rank',
                'Jackpot Amount (TZS)',
                'Prize Amount (TZS)',
                'Box Type',
                'Draw Period',
                'Draw Date',
                'Entry Date',
                'Status',
                'Box ID',
                'Winner ID',
                'Entry ID',
            ];
        }
        
        // Calculate entry counts per MSISDN
        $msisdnEntryCounts = [];
        foreach ($winners as $entry) {
            if (!isset($msisdnEntryCounts[$entry->msisdn])) {
                $msisdnEntryCounts[$entry->msisdn] = 0;
            }
            $msisdnEntryCounts[$entry->msisdn]++;
        }
        
        // Sort winners by MSISDN Rank (ascending, so rank 1 comes first) and then by entry_date (ascending)
        $winners = $winners->sortBy(function($entry) use ($msisdnRankMap) {
            $msisdnRank = $msisdnRankMap[$entry->msisdn] ?? 999;
            return [$msisdnRank, $entry->entry_date->timestamp];
        })->values();
        
        // Track entry rank per MSISDN
        $entryRanks = [];
        
        // Add winner data
        foreach ($winners as $entry) {
            // Get MSISDN rank from map (based on entry count order)
            $msisdnRank = $msisdnRankMap[$entry->msisdn] ?? 999;
            
            // Track entry rank per MSISDN
            if (!isset($entryRanks[$entry->msisdn])) {
                $entryRanks[$entry->msisdn] = 1;
            } else {
                $entryRanks[$entry->msisdn]++;
            }
            
            $lines[] = [
                $msisdnRank,
                $entry->msisdn,
                $msisdnEntryCounts[$entry->msisdn],
                $entryRanks[$entry->msisdn],
                number_format($entry->jackpot_amount, 2, '.', ''),
                $entry->prize_amount ? number_format($entry->prize_amount, 2, '.', '') : 'N/A',
                $entry->box_type ?? 'N/A',
                $entry->draw_period ?? $drawPeriod,
                $entry->draw_date ? $entry->draw_date->format('Y-m-d') : 'N/A',
                $entry->entry_date->format('Y-m-d H:i:s'),
                $entry->status,
                $entry->box_id ?? 'N/A',
                $entry->winner_id ?? 'N/A',
                $entry->id,
            ];
        }
        
        // Convert to CSV format
        $csvContent = '';
        foreach ($lines as $line) {
            $csvContent .= $this->formatCsvLine($line) . "\n";
        }
        
        return $csvContent;
    }

    /**
     * Format a line for CSV (handles escaping)
     *
     * @param array $fields
     * @return string
     */
    private function formatCsvLine(array $fields): string
    {
        $formatted = [];
        foreach ($fields as $field) {
            // Escape fields that contain commas, quotes, or newlines
            if (preg_match('/[,"\n\r]/', $field)) {
                $field = '"' . str_replace('"', '""', $field) . '"';
            }
            $formatted[] = $field;
        }
        
        return implode(',', $formatted);
    }
}

