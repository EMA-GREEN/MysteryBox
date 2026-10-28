<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('winners', function (Blueprint $table) {
            // Remove unused columns that are now replaced by specific disbursement columns
            $table->dropColumn([
                'disbursement_status',           // Replaced by airtime_disbursement_status and wallet_disbursement_status
                'disbursement_reference_id',     // Replaced by airtime_disbursement_reference_id and wallet_disbursement_reference_id
                'disbursed_at',                  // Replaced by airtime_disbursed_at and wallet_disbursed_at
                'processed_at'                   // Not used in current implementation
            ]);
        });
    }

    public function down()
    {
        Schema::table('winners', function (Blueprint $table) {
            // Re-add the dropped columns if needed to rollback
            $table->string('disbursement_status', 20)->default('pending')->after('draw_period');
            $table->string('disbursement_reference_id', 100)->nullable()->after('disbursement_status');
            $table->timestamp('disbursed_at')->nullable()->after('disbursement_response_message');
            $table->timestamp('processed_at')->nullable()->after('disbursed_at');
        });
    }
};
