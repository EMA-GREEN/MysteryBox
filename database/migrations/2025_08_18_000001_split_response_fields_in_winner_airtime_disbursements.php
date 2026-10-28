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
        Schema::table('winner_airtime_disbursements', function (Blueprint $table) {
            // Add new acknowledgment fields
            $table->string('ack_status')->nullable()->after('response_status');
            $table->text('ack_message')->nullable()->after('ack_status');
            $table->timestamp('ack_received_time')->nullable()->after('ack_message');
            
            // Add new callback fields
            $table->string('callback_status')->nullable()->after('ack_received_time');
            $table->text('callback_message')->nullable()->after('callback_status');
            $table->timestamp('callback_received_time')->nullable()->after('callback_message');
            
            // Drop old response fields
            $table->dropColumn(['response_status', 'response_message', 'response_received_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('winner_airtime_disbursements', function (Blueprint $table) {
            // Recreate old response fields
            $table->string('response_status')->nullable();
            $table->text('response_message')->nullable();
            $table->timestamp('response_received_time')->nullable();
            
            // Drop new fields
            $table->dropColumn([
                'ack_status', 'ack_message', 'ack_received_time',
                'callback_status', 'callback_message', 'callback_received_time'
            ]);
        });
    }
};
