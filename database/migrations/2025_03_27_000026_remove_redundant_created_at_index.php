<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Remove redundant index from sms_logs table
        if ($this->indexExists('sms_logs', 'sms_logs_created_at_index')) {
            Schema::table('sms_logs', function (Blueprint $table) {
                // Remove redundant created_at index since the composite msisdn_created_at index can handle these queries
                $table->dropIndex('sms_logs_created_at_index');
            });
        }
    }

    public function down(): void
    {
        // Restore index for sms_logs table
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->index('created_at', 'sms_logs_created_at_index');
        });
    }

    private function indexExists($table, $indexName)
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }
}; 