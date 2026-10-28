<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // First, drop all existing indexes
        Schema::table('payment_callbacks', function (Blueprint $table) {
            $table->dropIndex(['msisdn']);
            $table->dropIndex(['request_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['publish_status']);
            $table->dropIndex(['msisdn', 'status']);
            $table->dropIndex(['request_id', 'status']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['msisdn']);
            $table->dropIndex(['status']);
            $table->dropIndex(['payment_type']);
            $table->dropIndex(['box_type']);
            $table->dropIndex(['channel']);
            $table->dropIndex(['msisdn', 'status']);
            $table->dropIndex(['payment_type', 'status']);
        });

        Schema::table('mystery_boxes', function (Blueprint $table) {
            $table->dropIndex(['msisdn']);
            $table->dropIndex(['status']);
            $table->dropIndex(['box_type']);
            $table->dropIndex(['msisdn', 'status']);
            $table->dropIndex(['box_type', 'status']);
        });

        Schema::table('winners', function (Blueprint $table) {
            $table->dropIndex(['msisdn']);
            $table->dropIndex(['box_type']);
            $table->dropIndex(['awarded_at']);
            $table->dropIndex(['box_type', 'prize_type']);
        });

        // Now add consolidated indexes
        Schema::table('payment_callbacks', function (Blueprint $table) {
            // Composite indexes (these will also cover single-column queries)
            $table->index(['msisdn', 'status']);
            $table->index(['request_id', 'status']);
            $table->index('publish_status');
        });

        Schema::table('payments', function (Blueprint $table) {
            // Composite indexes (these will also cover single-column queries)
            $table->index(['msisdn', 'status']);
            $table->index(['payment_type', 'status']);
            $table->index(['box_type', 'status']);
            $table->index('channel');
        });

        Schema::table('mystery_boxes', function (Blueprint $table) {
            // Composite indexes (these will also cover single-column queries)
            $table->index(['msisdn', 'status']);
            $table->index(['box_type', 'status']);
        });

        Schema::table('winners', function (Blueprint $table) {
            // Composite indexes (these will also cover single-column queries)
            $table->index(['msisdn', 'awarded_at']);
            $table->index(['box_type', 'prize_type']);
        });

        Schema::table('sms_logs', function (Blueprint $table) {
            $table->index(['msisdn', 'created_at']);
        });
    }

    public function down(): void
    {
        // Remove consolidated indexes
        Schema::table('payment_callbacks', function (Blueprint $table) {
            $table->dropIndex(['msisdn', 'status']);
            $table->dropIndex(['request_id', 'status']);
            $table->dropIndex(['publish_status']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['msisdn', 'status']);
            $table->dropIndex(['payment_type', 'status']);
            $table->dropIndex(['box_type', 'status']);
            $table->dropIndex(['channel']);
        });

        Schema::table('mystery_boxes', function (Blueprint $table) {
            $table->dropIndex(['msisdn', 'status']);
            $table->dropIndex(['box_type', 'status']);
        });

        Schema::table('winners', function (Blueprint $table) {
            $table->dropIndex(['msisdn', 'awarded_at']);
            $table->dropIndex(['box_type', 'prize_type']);
        });

        Schema::table('sms_logs', function (Blueprint $table) {
            $table->dropIndex(['msisdn', 'created_at']);
        });
    }
}; 