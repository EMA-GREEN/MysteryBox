<?php

namespace App\Console\Commands;

use App\Models\WinnerJackpotEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ExportHistoricalDrawWinners extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'winners:export-historical 
                            {--period= : Specific draw period (daily, weekly, monthly). If not specified, processes all periods}
                            {--date= : Specific date to process (Y-m-d format). If not specified, processes all available dates}
                            {--start-date= : Start date for date range (Y-m-d format)}
                            {--end-date= : End date for date range (Y-m-d format)}
                            {--force : Force processing even if entries are already processed (status != pending)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process and export historical draw winners for old data. Can process specific dates or date ranges.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting historical draw winners export...');
        
        $period = $this->option('period');
        $periods = $period ? [$period] : ['daily', 'weekly', 'monthly'];
        
        $date = $this->option('date');
        $startDate = $this->option('start-date');
        $endDate = $this->option('end-date');
        $force = $this->option('force');
        
        // Ensure storage directory exists
        $storagePath = config('draws.csv.directory', 'winners');
        $disk = Storage::disk('local');
        
        if (!$disk->exists($storagePath)) {
            $disk->makeDirectory($storagePath);
            $this->info("Created storage directory: storage/app/{$storagePath}");
        }
        
        $totalExported = 0;
        
        // Determine dates to process
        $datesToProcess = $this->getDatesToProcess($date, $startDate, $endDate);
        
        if (empty($datesToProcess)) {
            $this->error('No dates specified. Use --date, --start-date/--end-date, or process all available dates.');
            return Command::FAILURE;
        }
        
        $this->info("Processing " . count($datesToProcess) . " date(s) for historical export...");
        
        foreach ($periods as $drawPeriod) {
            // For weekly and monthly draws, deduplicate draw_dates to avoid processing the same period multiple times
            $uniqueDrawDates = $this->getUniqueDrawDates($drawPeriod, $datesToProcess);
            
            $this->info("Processing {$drawPeriod} draws for " . count($uniqueDrawDates) . " unique draw_date(s)...");
            
            foreach ($uniqueDrawDates as $drawDateInfo) {
                $processDate = $drawDateInfo['process_date'];
                $drawDate = $drawDateInfo['draw_date'];
                
                try {
                    $exported = $this->exportWinnersForPeriod($drawPeriod, $processDate, $drawDate, $force);
                    $totalExported += $exported;
                } catch (\Exception $e) {
                    $this->error("Error exporting {$drawPeriod} winners for draw_date {$drawDate->format('Y-m-d')}: " . $e->getMessage());
                    Log::error("Error exporting historical {$drawPeriod} winners", [
                        'process_date' => $processDate->format('Y-m-d'),
                        'draw_date' => $drawDate->format('Y-m-d'),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }
        }
        
        $this->info("Successfully exported {$totalExported} winners across all periods and dates");
        
        return Command::SUCCESS;
    }

    /**
     * Get dates to process based on options
     *
     * @param string|null $date
     * @param string|null $startDate
     * @param string|null $endDate
     * @return array
     */
    private function getDatesToProcess(?string $date, ?string $startDate, ?string $endDate): array
    {
        $dates = [];
        
        if ($date) {
            // Single date specified
            try {
                $dates[] = Carbon::parse($date);
            } catch (\Exception $e) {
                $this->error("Invalid date format: {$date}. Use Y-m-d format (e.g., 2025-12-11)");
                return [];
            }
        } elseif ($startDate && $endDate) {
            // Date range specified
            try {
                $start = Carbon::parse($startDate);
                $end = Carbon::parse($endDate);
                
                if ($start->gt($end)) {
                    $this->error("Start date must be before or equal to end date");
                    return [];
                }
                
                // Generate all dates in range
                $current = $start->copy();
                while ($current->lte($end)) {
                    $dates[] = $current->copy();
                    $current->addDay();
                }
            } catch (\Exception $e) {
                $this->error("Invalid date format. Use Y-m-d format (e.g., 2025-12-11)");
                return [];
            }
        } else {
            // No dates specified - get all unique draw_dates from database
            $this->info("No dates specified. Fetching all available draw_dates from database...");
            $drawDates = WinnerJackpotEntry::selectRaw('DISTINCT DATE(draw_date) as draw_date')
                ->whereNotNull('draw_date')
                ->orderBy('draw_date', 'asc')
                ->pluck('draw_date')
                ->map(function($date) {
                    return Carbon::parse($date);
                })
                ->toArray();
            
            if (empty($drawDates)) {
                $this->warn("No draw_dates found in database");
                return [];
            }
            
            $this->info("Found " . count($drawDates) . " unique draw_date(s) to process");
            return $drawDates;
        }
        
        return $dates;
    }

    /**
     * Get unique draw_dates for a period to avoid duplicate processing
     *
     * @param string $drawPeriod
     * @param array $datesToProcess
     * @return array Array of ['process_date' => Carbon, 'draw_date' => Carbon]
     */
    private function getUniqueDrawDates(string $drawPeriod, array $datesToProcess): array
    {
        $uniqueDrawDates = [];
        $seenDrawDates = [];
        $today = Carbon::today();
        
        foreach ($datesToProcess as $processDate) {
            $drawDate = $this->getDrawDateForPeriod($drawPeriod, $processDate);
            $drawDateKey = $drawDate->format('Y-m-d');
            
            // Skip if draw_date is today or in the future (draws should only be processed after they've ended)
            if ($drawDate->isSameDay($today) || $drawDate->isFuture()) {
                continue;
            }
            
            // Only add if we haven't seen this draw_date before
            if (!isset($seenDrawDates[$drawDateKey])) {
                $seenDrawDates[$drawDateKey] = true;
                $uniqueDrawDates[] = [
                    'process_date' => $processDate,
                    'draw_date' => $drawDate
                ];
            }
        }
        
        return $uniqueDrawDates;
    }

    /**
     * Export winners for a specific draw period and date
     *
     * @param string $drawPeriod
     * @param Carbon $processDate The original date being processed (for logging)
     * @param Carbon $drawDate The actual draw_date to query (pre-calculated)
     * @param bool $force
     * @return int Number of winners exported
     */
    private function exportWinnersForPeriod(string $drawPeriod, Carbon $processDate, Carbon $drawDate, bool $force = false): int
    {
        $config = config("draws.winners.{$drawPeriod}");
        
        if (!$config) {
            $this->warn("No configuration found for {$drawPeriod} draw period");
            return 0;
        }
        
        // Skip if draw_date is today or in the future (draws should only be processed after they've ended)
        $today = Carbon::today();
        if ($drawDate->isSameDay($today) || $drawDate->isFuture()) {
            $this->warn("Skipping {$drawPeriod} draw for draw_date: {$drawDate->format('Y-m-d')} (draw has not ended yet)");
            return 0;
        }
        
        $topN = $config['top_n'] ?? 10;
        
        $this->info("Processing {$drawPeriod} draw for draw_date: {$drawDate->format('Y-m-d')}...");
        
        // Build query - use 'pending' status unless force is enabled
        $statusQuery = WinnerJackpotEntry::where('draw_period', $drawPeriod)
            ->whereDate('draw_date', $drawDate->toDateString());
        
        if (!$force) {
            $statusQuery->where('status', 'pending');
        }
        
        // Get top N MSISDNs with highest record counts for entries with this specific draw_date
        $topMsisdnsWithCounts = (clone $statusQuery)
            ->selectRaw('msisdn, COUNT(*) as entry_count')
            ->groupBy('msisdn')
            ->orderBy('entry_count', 'desc')
            ->limit($topN)
            ->get();
        
        if ($topMsisdnsWithCounts->isEmpty()) {
            $statusMsg = $force ? " (including already processed)" : " (pending only)";
            $this->warn("No entries found for {$drawPeriod} draw period with draw_date: {$drawDate->format('Y-m-d')}{$statusMsg}");
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
        $winners = (clone $statusQuery)
            ->whereIn('msisdn', $topMsisdns)
            ->get();
        
        if ($winners->isEmpty()) {
            $this->warn("No winners found for {$drawPeriod} draw period");
            return 0;
        }
        
        // Generate CSV filename using draw_date
        $filename = $this->generateFilename($drawPeriod, $drawDate);
        $filePath = config('draws.csv.directory', 'winners') . '/' . $filename;
        
        // Get storage disk
        $disk = Storage::disk('local');
        
        // Check if file already exists and ask for confirmation (unless force)
        if (!$force && $disk->exists($filePath)) {
            if (!$this->confirm("File {$filename} already exists. Overwrite?")) {
                $this->info("Skipping {$drawPeriod} draw for {$drawDate->format('Y-m-d')}");
                return 0;
            }
        }
        
        // Generate CSV content
        $csvContent = $this->generateCsvContent($winners, $drawPeriod, $msisdnRankMap);
        
        // Save to storage
        $disk->put($filePath, $csvContent);
        
        $fullPath = $disk->path($filePath);
        $this->info("Exported {$winners->count()} winners to: {$fullPath}");
        
        // Update entries to 'active' status after successful export (only if they were pending)
        if (!$force) {
            $updatedCount = WinnerJackpotEntry::where('draw_period', $drawPeriod)
                ->where('status', 'pending')
                ->whereIn('msisdn', $topMsisdns)
                ->whereDate('draw_date', $drawDate->toDateString())
                ->update(['status' => 'active']);
            
            $this->info("Updated {$updatedCount} entries to 'active' status");
        } else {
            $this->info("Force mode: Skipping status update");
        }
        
        Log::info("Exported historical {$drawPeriod} winners", [
            'period' => $drawPeriod,
            'process_date' => $processDate->format('Y-m-d'),
            'draw_date' => $drawDate->format('Y-m-d'),
            'count' => $winners->count(),
            'filename' => $filename,
            'force' => $force
        ]);
        
        return $winners->count();
    }
    
    /**
     * Get the draw_date for a specific period based on the given date
     *
     * @param string $drawPeriod
     * @param Carbon $date
     * @return Carbon
     */
    private function getDrawDateForPeriod(string $drawPeriod, Carbon $date): Carbon
    {
        switch ($drawPeriod) {
            case 'daily':
                // For daily, draw_date is the date itself
                return $date->copy();
            
            case 'weekly':
                // For weekly, draw_date is the end of the week (Sunday) that the date belongs to
                return $date->copy()->endOfWeek();
            
            case 'monthly':
                // For monthly, draw_date is the end of the month that the date belongs to
                return $date->copy()->endOfMonth();
            
            default:
                return $date->copy();
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

