<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Remove redundant index from mystery_boxes table
        if ($this->indexExists('mystery_boxes', 'mystery_boxes_msisdn_status_index')) {
            Schema::table('mystery_boxes', function (Blueprint $table) {
                // Remove redundant msisdn_status index since msisdn_box_type_id index provides better coverage
                $table->dropIndex('mystery_boxes_msisdn_status_index');
            });
        }
    }

    public function down(): void
    {
        // Restore index for mystery_boxes table
        Schema::table('mystery_boxes', function (Blueprint $table) {
            $table->index(['msisdn', 'status'], 'mystery_boxes_msisdn_status_index');
        });
    }

    private function indexExists($table, $indexName)
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }
}; 