<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add indexes to winner_airtime_disbursements table
        Schema::table('winner_airtime_disbursements', function (Blueprint $table) {
            // Index for response_status column (for filtering successful disbursements)
            $table->index('response_status', 'idx_winner_airtime_disbursements_response_status');
            
            // Index for request_sent_time column (for date filtering and ordering)
            $table->index('request_sent_time', 'idx_winner_airtime_disbursements_request_sent_time');
            
            // Composite index for common query pattern: msisdn + response_status + request_sent_time
            $table->index(['msisdn', 'response_status', 'request_sent_time'], 'idx_winner_airtime_disbursements_msisdn_status_time');
        });

        // Add indexes to winner_wallet_disbursements table
        Schema::table('winner_wallet_disbursements', function (Blueprint $table) {
            // Index for response_status column (for filtering successful disbursements)
            $table->index('response_status', 'idx_winner_wallet_disbursements_response_status');
            
            // Index for request_sent_time column (for date filtering and ordering)
            $table->index('request_sent_time', 'idx_winner_wallet_disbursements_request_sent_time');
            
            // Composite index for common query pattern: msisdn + response_status + request_sent_time
            $table->index(['msisdn', 'response_status', 'request_sent_time'], 'idx_winner_wallet_disbursements_msisdn_status_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove indexes from winner_airtime_disbursements table
        Schema::table('winner_airtime_disbursements', function (Blueprint $table) {
            $table->dropIndex('idx_winner_airtime_disbursements_response_status');
            $table->dropIndex('idx_winner_airtime_disbursements_request_sent_time');
            $table->dropIndex('idx_winner_airtime_disbursements_msisdn_status_time');
        });

        // Remove indexes from winner_wallet_disbursements table
        Schema::table('winner_wallet_disbursements', function (Blueprint $table) {
            $table->dropIndex('idx_winner_wallet_disbursements_response_status');
            $table->dropIndex('idx_winner_wallet_disbursements_request_sent_time');
            $table->dropIndex('idx_winner_wallet_disbursements_msisdn_status_time');
        });
    }
};
