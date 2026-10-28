<?php

/**
 * Critical Tables Backup Script (PHP Version)
 * This script creates daily backups of critical tables using Archive Tables approach
 * 
 * Usage: php backup_critical_tables.php
 */

// Load Laravel environment
require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class CriticalTablesBackup
{
    private $dateSuffix;
    private $timestamp;
    private $logFile;
    private $criticalTables;
    
    public function __construct()
    {
        // Set timezone
        date_default_timezone_set('Africa/Dar_Es_Salaam');
        
        $this->dateSuffix = Carbon::now()->format('Ymd');
        $this->timestamp = Carbon::now()->format('Y-m-d H:i:s');
        
        // Create logs directory if it doesn't exist
        $logDir = storage_path('logs');
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $this->logFile = $logDir . '/archive_' . $this->dateSuffix . '.log';
        
        // Critical tables to backup
        $this->criticalTables = [
            'app_notifications',
            'sms_notifications', 
            'sms_logs',
            'payment_notifications',
            'payment_callbacks',
            'renewal_payment_callbacks',
            'payments',
            'mobile_money_transaction_notifications',
            'mpush_subs_init_notifications'
        ];
    }
    
    /**
     * Log message to both console and file
     */
    private function logMessage($message)
    {
        $formattedMessage = "[{$this->timestamp}] {$message}";
        echo $formattedMessage . PHP_EOL;
        
        // Write to log file
        file_put_contents($this->logFile, $formattedMessage . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Check if table exists
     */
    private function tableExists($tableName)
    {
        return Schema::hasTable($tableName);
    }
    
    /**
     * Get row count for a table
     */
    private function getRowCount($tableName)
    {
        return DB::table($tableName)->count();
    }
    
    /**
     * Backup a single table using Archive Tables approach
     */
    private function backupTable($tableName)
    {
        $archiveTableName = $tableName . '_' . $this->dateSuffix;
        
        $this->logMessage("Starting backup of table: {$tableName} to archive: {$archiveTableName}");
        
        // Check if table exists
        if (!$this->tableExists($tableName)) {
            $this->logMessage("⚠ Table {$tableName} does not exist, skipping...");
            return true;
        }
        
        // Check if table has data
        $rowCount = $this->getRowCount($tableName);
        if ($rowCount == 0) {
            $this->logMessage("⚠ Table {$tableName} is empty, skipping...");
            return true;
        }
        
        // Check if archive table already exists
        if ($this->tableExists($archiveTableName)) {
            $this->logMessage("⚠ Archive table {$archiveTableName} already exists, dropping it first...");
            Schema::dropIfExists($archiveTableName);
        }
        
        try {
            // Archive the table using RENAME
            DB::statement("RENAME TABLE `{$tableName}` TO `{$archiveTableName}`");
            
            $this->logMessage("✓ Successfully archived {$tableName} to {$archiveTableName}");
            $this->logMessage("  Rows archived: {$rowCount}");
            
            // Create new empty table with same structure
            DB::statement("CREATE TABLE `{$tableName}` LIKE `{$archiveTableName}`");
            
            $this->logMessage("✓ Created new empty table: {$tableName}");
            return true;
            
        } catch (Exception $e) {
            $this->logMessage("✗ Failed to archive table {$tableName}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Cleanup old archive tables (keep last 7 days)
     */
    private function cleanupOldBackups()
    {
        $this->logMessage("Cleaning up archive tables older than 7 days...");
        
        $cutoffDate = Carbon::now()->subDays(7)->format('Ymd');
        $deletedCount = 0;
        
        foreach ($this->criticalTables as $table) {
            // Get all archive tables for this table
            $archiveTables = DB::select("SHOW TABLES LIKE '{$table}_%'");
            
            foreach ($archiveTables as $archiveTable) {
                $archiveTableName = $archiveTable->{"Tables_in_" . config('database.connections.mysql.database')};
                
                // Extract date from table name
                $tableDate = str_replace($table . '_', '', $archiveTableName);
                
                // Check if date is older than cutoff
                if ($tableDate < $cutoffDate) {
                    try {
                        Schema::dropIfExists($archiveTableName);
                        $this->logMessage("  Dropped old archive table: {$archiveTableName}");
                        $deletedCount++;
                    } catch (Exception $e) {
                        $this->logMessage("  Failed to drop {$archiveTableName}: " . $e->getMessage());
                    }
                }
            }
        }
        
        $this->logMessage("✓ Cleanup completed. Dropped {$deletedCount} old archive tables");
    }
    
    /**
     * Cleanup old log files (keep last 30 days)
     */
    private function cleanupOldLogs()
    {
        $this->logMessage("Cleaning up log files older than 30 days...");
        
        $logDir = storage_path('logs');
        $cutoffTime = Carbon::now()->subDays(30)->timestamp;
        $deletedLogs = 0;
        
        $files = glob($logDir . '/archive_*.log');
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                if (unlink($file)) {
                    $deletedLogs++;
                }
            }
        }
        
        $this->logMessage("✓ Cleanup completed. Deleted {$deletedLogs} old log files");
    }
    
    /**
     * Main backup process
     */
    public function run()
    {
        $this->logMessage("=== Starting Critical Tables Archive Process ===");
        $this->logMessage("Database: " . config('database.connections.mysql.database'));
        $this->logMessage("Database Host: " . config('database.connections.mysql.host'));
        $this->logMessage("Database User: " . config('database.connections.mysql.username'));
        $this->logMessage("Date suffix: {$this->dateSuffix}");
        $this->logMessage("Timezone: Africa/Dar_Es_Salaam");
        $this->logMessage("Log file: {$this->logFile}");
        
        // Test database connection
        try {
            DB::connection()->getPdo();
            $this->logMessage("✓ Database connection successful");
        } catch (Exception $e) {
            $this->logMessage("✗ Cannot connect to database: " . $e->getMessage());
            $this->logMessage("Please check credentials in .env file.");
            exit(1);
        }
        
        // Backup each table
        $failedBackups = 0;
        foreach ($this->criticalTables as $table) {
            if (!$this->backupTable($table)) {
                $failedBackups++;
            }
        }
        
        // Cleanup old backups
        $this->cleanupOldBackups();
        
        // Cleanup old logs
        $this->cleanupOldLogs();
        
        // Summary
        $this->logMessage("=== Backup Summary ===");
        $this->logMessage("Total tables: " . count($this->criticalTables));
        $this->logMessage("Successful archives: " . (count($this->criticalTables) - $failedBackups));
        $this->logMessage("Failed archives: {$failedBackups}");
        
        if ($failedBackups == 0) {
            $this->logMessage("✓ All archives completed successfully");
            exit(0);
        } else {
            $this->logMessage("✗ Some archives failed");
            exit(1);
        }
    }
}

// Run the backup
$backup = new CriticalTablesBackup();
$backup->run();