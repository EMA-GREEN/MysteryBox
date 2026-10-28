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

    protected $description = 'Archive renewal_payments shards, drop old renewal_payment_callbacks shards, rebuild success payment tables without secondary indexes';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $cutoffDate = Carbon::today()->subDays($days);

        $this->info('Renewal archive: tables with shard date before ' . $cutoffDate->format('Y-m-d') . ' (older than ' . $days . ' days)');
        if ($dryRun) {
            $this->warn('DRY RUN: no changes will be made.');
        }

        $database = DB::getDatabaseName();
        $errors = [];
        $processedPayments = 0;
        $processedCallbacks = 0;
        $processedRebuild = 0;

        // --- Phase 1: renewal_payments_yyyymmdd (not success) -> keep success rows, rename to *_success_yyyymmdd ---
        $paymentTables = DB::select(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME LIKE ? AND TABLE_NAME NOT LIKE ?',
            [$database, 'renewal_payments_%', 'renewal_payments_success_%']
        );

        foreach ($paymentTables as $row) {
            $tableName = $row->TABLE_NAME;
            if (!preg_match('/^renewal_payments_(\d{8})$/', $tableName, $m)) {
                continue;
            }
            $dateStr = $m[1];
            $tableDate = Carbon::createFromFormat('Ymd', $dateStr)->startOfDay();
            if ($tableDate->gte($cutoffDate)) {
                continue;
            }

            $newName = 'renewal_payments_success_' . $dateStr;

            if ($dryRun) {
                $this->line("[dry-run] Would archive: {$tableName} -> {$newName} (delete non-success, rename)");
                $processedPayments++;
                continue;
            }

            try {
                $deleted = DB::table($tableName)->where('status', '!=', 'success')->delete();
                DB::statement("RENAME TABLE `{$tableName}` TO `{$newName}`");
                $this->info("Archived: {$tableName} -> {$newName} (deleted {$deleted} non-success row(s))");
                Log::info('RenewalArchiveSuccessTables: archived payments table', [
                    'from' => $tableName,
                    'to' => $newName,
                    'deleted_rows' => $deleted,
                ]);
                $processedPayments++;
            } catch (\Throwable $e) {
                $errors[] = "{$tableName}: " . $e->getMessage();
                Log::error('RenewalArchiveSuccessTables: archive payments failed', ['table' => $tableName, 'error' => $e->getMessage()]);
            }
        }

        // --- Phase 2: renewal_payment_callbacks_yyyymmdd (not success) older than cutoff -> DROP ---
        $callbackTables = DB::select(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME LIKE ? AND TABLE_NAME NOT LIKE ?',
            [$database, 'renewal_payment_callbacks_%', 'renewal_payment_callbacks_success_%']
        );

        foreach ($callbackTables as $row) {
            $tableName = $row->TABLE_NAME;
            if (!preg_match('/^renewal_payment_callbacks_(\d{8})$/', $tableName, $m)) {
                continue;
            }
            $dateStr = $m[1];
            $tableDate = Carbon::createFromFormat('Ymd', $dateStr)->startOfDay();
            if ($tableDate->gte($cutoffDate)) {
                continue;
            }

            if ($dryRun) {
                $this->line("[dry-run] Would DROP table: {$tableName}");
                $processedCallbacks++;
                continue;
            }

            try {
                DB::statement("DROP TABLE `{$tableName}`");
                $this->info("Dropped callback shard: {$tableName}");
                Log::info('RenewalArchiveSuccessTables: dropped callback table', ['table' => $tableName]);
                $processedCallbacks++;
            } catch (\Throwable $e) {
                $errors[] = "{$tableName}: " . $e->getMessage();
                Log::error('RenewalArchiveSuccessTables: drop callback failed', ['table' => $tableName, 'error' => $e->getMessage()]);
            }
        }

        // --- Phase 3: renewal_payments_success_yyyymmdd older than cutoff -> re-create without secondary indexes ---
        $successTables = DB::select(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME LIKE ?',
            [$database, 'renewal_payments_success_%']
        );

        foreach ($successTables as $row) {
            $tableName = $row->TABLE_NAME;
            if (!preg_match('/^renewal_payments_success_(\d{8})$/', $tableName, $m)) {
                continue;
            }
            $dateStr = $m[1];
            $tableDate = Carbon::createFromFormat('Ymd', $dateStr)->startOfDay();
            if ($tableDate->gte($cutoffDate)) {
                continue;
            }

            // Skip if already rebuilt (only PRIMARY index exists)
            if (!$this->tableHasSecondaryIndexes($database, $tableName)) {
                continue;
            }

            if ($dryRun) {
                $this->line("[dry-run] Would rebuild without secondary indexes: {$tableName}");
                $processedRebuild++;
                continue;
            }

            try {
                $this->rebuildPaymentsSuccessTableWithoutSecondaryIndexes($tableName);
                $this->info("Rebuilt (no secondary indexes): {$tableName}");
                Log::info('RenewalArchiveSuccessTables: rebuilt success table without secondary indexes', ['table' => $tableName]);
                $processedRebuild++;
            } catch (\Throwable $e) {
                $errors[] = "{$tableName} (rebuild): " . $e->getMessage();
                Log::error('RenewalArchiveSuccessTables: rebuild failed', ['table' => $tableName, 'error' => $e->getMessage()]);
            }
        }

        if (count($errors) > 0) {
            foreach ($errors as $err) {
                $this->error($err);
            }

            return self::FAILURE;
        }

        $this->info("Done. Archived {$processedPayments} payment shard(s), dropped {$processedCallbacks} callback shard(s), rebuilt {$processedRebuild} success payment table(s).");

        return self::SUCCESS;
    }

    /**
     * Returns true if table has any non-PRIMARY index.
     */
    private function tableHasSecondaryIndexes(string $database, string $tableName): bool
    {
        $rows = DB::select(
            'SELECT COUNT(*) AS c
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND INDEX_NAME <> ?',
            [$database, $tableName, 'PRIMARY']
        );

        $count = (int) ($rows[0]->c ?? 0);
        return $count > 0;
    }

    /**
     * Replace table with a copy that has only PRIMARY KEY on id (no secondary indexes) to save disk.
     */
    private function rebuildPaymentsSuccessTableWithoutSecondaryIndexes(string $tableName): void
    {
        $tmp = $tableName . '__rebuild_no_idx';

        DB::statement("DROP TABLE IF EXISTS `{$tmp}`");

        $createSql = <<<SQL
CREATE TABLE `{$tmp}` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `msisdn` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `ack_status_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ack_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `callback_status_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `callback_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ack_received_time` timestamp NULL DEFAULT NULL,
  `callback_received_time` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

        DB::statement($createSql);
        DB::statement("INSERT INTO `{$tmp}` SELECT * FROM `{$tableName}`");
        DB::statement("DROP TABLE `{$tableName}`");
        DB::statement("RENAME TABLE `{$tmp}` TO `{$tableName}`");
    }
}
