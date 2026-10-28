<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RenewalArchiveSuccessTables extends Command
{
    protected $signature = 'mysterybox:renewal:archive-success-tables
                            {--days=7 : Archive tables older than this many days}
                            {--dry-run : List tables that would be processed without making changes}';

    protected $description = 'Archive renewal_payments_yyyymmdd and renewal_payment_callbacks_yyyymmdd older than N days: keep only status=success, rename to *_success_yyyymmdd';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $cutoffDate = Carbon::today()->subDays($days);

        $this->info('Renewal archive success tables: tables older than ' . $days . ' days (before ' . $cutoffDate->format('Y-m-d') . ')');
        if ($dryRun) {
            $this->warn('DRY RUN: no changes will be made.');
        }

        $database = DB::getDatabaseName();

        $processed = 0;
        $errors = [];

        foreach (['renewal_payments' => 'renewal_payments_%', 'renewal_payment_callbacks' => 'renewal_payment_callbacks_%'] as $prefix => $likePattern) {
            $tables = DB::select(
                "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME LIKE ? AND TABLE_NAME NOT LIKE ?",
                [$database, $likePattern, $prefix . '_success_%']
            );

            foreach ($tables as $row) {
                $tableName = $row->TABLE_NAME;

                // Extract yyyymmdd from end (e.g. renewal_payments_20260208 -> 20260208)
                if (!preg_match('/^' . preg_quote($prefix, '/') . '_(\d{8})$/', $tableName, $m)) {
                    continue;
                }
                $dateStr = $m[1];
                $tableDate = Carbon::createFromFormat('Ymd', $dateStr)->startOfDay();

                if ($tableDate->gte($cutoffDate)) {
                    continue; // not older than 7 days
                }

                $newName = $prefix . '_success_' . $dateStr;

                if ($dryRun) {
                    $this->line("[dry-run] Would process: {$tableName} -> {$newName} (keep status='success')");
                    $processed++;
                    continue;
                }

                try {
                    $deleted = DB::table($tableName)->where('status', '!=', 'success')->delete();
                    DB::statement("RENAME TABLE `{$tableName}` TO `{$newName}`");
                    $this->info("Archived: {$tableName} -> {$newName} (deleted {$deleted} non-success rows)");
                    Log::info('RenewalArchiveSuccessTables: archived table', [
                        'from' => $tableName,
                        'to' => $newName,
                        'deleted_rows' => $deleted,
                    ]);
                    $processed++;
                } catch (\Throwable $e) {
                    $errors[] = "{$tableName}: " . $e->getMessage();
                    Log::error('RenewalArchiveSuccessTables: failed', ['table' => $tableName, 'error' => $e->getMessage()]);
                }
            }
        }

        if (count($errors) > 0) {
            foreach ($errors as $err) {
                $this->error($err);
            }
            return self::FAILURE;
        }

        $this->info("Done. Processed {$processed} table(s).");
        return self::SUCCESS;
    }
}
