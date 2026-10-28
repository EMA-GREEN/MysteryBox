<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('winners', function (Blueprint $table) {
            // Remove redundant shared disbursement columns
            // These are now handled by specific disbursement status columns and detailed disbursement tables
            $table->dropColumn([
                'disbursement_request_time',      // Redundant - tracked in disbursement tables
                'disbursement_response_time',     // Redundant - tracked in disbursement tables
                'disbursement_response_status',   // Redundant - replaced by specific status columns
                'disbursement_response_message'   // Redundant - tracked in disbursement tables
            ]);
        });
    }

    public function down()
    {
        Schema::table('winners', function (Blueprint $table) {
            // Re-add the dropped columns if needed to rollback
            $table->timestamp('disbursement_request_time')->nullable()->after('draw_period');
            $table->timestamp('disbursement_response_time')->nullable()->after('disbursement_request_time');
            $table->string('disbursement_response_status', 50)->nullable()->after('disbursement_response_time');
            $table->text('disbursement_response_message')->nullable()->after('disbursement_response_status');
        });
    }
};
