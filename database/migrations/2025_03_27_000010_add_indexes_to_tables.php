<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add indexes to payment_callbacks table
        Schema::table('payment_callbacks', function (Blueprint $table) {
            $table->index('msisdn');
            $table->index('request_id');
            $table->index('status');
            $table->index('publish_status');
        });

        // Add indexes to payments table
        Schema::table('payments', function (Blueprint $table) {
            $table->index('msisdn');
            $table->index('status');
            $table->index('payment_type');
            $table->index('box_type');
            $table->index('channel');
        });

        // Add indexes to sms_logs table
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->index('msisdn');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        // Remove indexes from payment_callbacks table
        Schema::table('payment_callbacks', function (Blueprint $table) {
            $table->dropIndex(['msisdn']);
            $table->dropIndex(['request_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['publish_status']);
        });

        // Remove indexes from payments table
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['msisdn']);
            $table->dropIndex(['status']);
            $table->dropIndex(['payment_type']);
            $table->dropIndex(['box_type']);
            $table->dropIndex(['channel']);
        });

        // Remove indexes from sms_logs table
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->dropIndex(['msisdn']);
            $table->dropIndex(['created_at']);
        });
    }
}; 