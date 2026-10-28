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
        Schema::table('payments', function (Blueprint $table) {
            // Add acknowledgment and callback time fields
            $table->timestamp('ack_received_time')->nullable()->after('callback_description');
            $table->timestamp('callback_received_time')->nullable()->after('ack_received_time');
            
            // Add indexes for better query performance
            $table->index('ack_received_time');
            $table->index('callback_received_time');
            $table->index(['ack_received_time', 'callback_received_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex(['ack_received_time']);
            $table->dropIndex(['callback_received_time']);
            $table->dropIndex(['ack_received_time', 'callback_received_time']);
            
            // Drop columns
            $table->dropColumn(['ack_received_time', 'callback_received_time']);
        });
    }
};
