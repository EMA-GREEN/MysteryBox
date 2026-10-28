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
        Schema::create('renewal_payments', function (Blueprint $table) {
            $table->id();
            $table->string('msisdn');
            $table->string('request_id')->unique();
            $table->string('payment_type');
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('pending');
            $table->string('ack_status_code')->nullable();
            $table->string('ack_description')->nullable();
            $table->string('callback_status_code')->nullable();
            $table->string('callback_description')->nullable();
            $table->timestamp('ack_received_time')->nullable();
            $table->timestamp('callback_received_time')->nullable();
            $table->timestamps();

            // Optimized indexes - removed redundant individual indexes
            // Primary composite indexes for common query patterns
            $table->index(['msisdn', 'payment_type', 'status'], 'idx_msisdn_payment_status'); // Covers msisdn, payment_type, and status queries
            $table->index(['status', 'created_at'], 'idx_status_created'); // For status filtering with date ordering
            
            // Specific indexes for callback tracking
            $table->index('ack_received_time'); // For acknowledgment tracking
            $table->index('callback_received_time'); // For callback tracking
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('renewal_payments');
    }
};