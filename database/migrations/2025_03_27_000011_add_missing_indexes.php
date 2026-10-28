<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add composite index for payments table
        Schema::table('payments', function (Blueprint $table) {
            // Composite index for common query pattern: finding payments by msisdn and status
            $table->index(['msisdn', 'status']);
            // Composite index for payment type and status queries
            $table->index(['payment_type', 'status']);
        });

        // Add composite index for payment_callbacks table
        Schema::table('payment_callbacks', function (Blueprint $table) {
            // Composite index for common query pattern: finding callbacks by msisdn and status
            $table->index(['msisdn', 'status']);
            // Composite index for request_id and status queries
            $table->index(['request_id', 'status']);
        });

        // Add composite index for mystery_boxes table
        Schema::table('mystery_boxes', function (Blueprint $table) {
            // Composite index for box_type and status queries
            $table->index(['box_type', 'status']);
        });

        // Add composite index for winners table
        Schema::table('winners', function (Blueprint $table) {
            // Composite index for box_type and prize_type queries
            $table->index(['box_type', 'prize_type']);
        });
    }

    public function down(): void
    {
        // Remove composite indexes from payments table
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['msisdn', 'status']);
            $table->dropIndex(['payment_type', 'status']);
        });

        // Remove composite indexes from payment_callbacks table
        Schema::table('payment_callbacks', function (Blueprint $table) {
            $table->dropIndex(['msisdn', 'status']);
            $table->dropIndex(['request_id', 'status']);
        });

        // Remove composite indexes from mystery_boxes table
        Schema::table('mystery_boxes', function (Blueprint $table) {
            $table->dropIndex(['box_type', 'status']);
        });

        // Remove composite indexes from winners table
        Schema::table('winners', function (Blueprint $table) {
            $table->dropIndex(['box_type', 'prize_type']);
        });
    }
}; 