<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('renewal_payment_callbacks', function (Blueprint $table) {
            $table->id();
            $table->string('msisdn');
            $table->string('request_id');
            $table->decimal('amount', 10, 2);
            $table->string('channel')->default('APP');
            $table->string('billing_system')->nullable();
            $table->string('status')->default('pending');
            $table->string('details', 255)->nullable();
            $table->boolean('publish_status')->default(false);
            $table->timestamps();

            // Indexes for optimal query performance
            $table->index('msisdn', 'idx_renewal_payment_callbacks_msisdn');
            $table->unique('request_id', 'idx_renewal_payment_callbacks_request_id_unique');
            $table->index('status', 'idx_renewal_payment_callbacks_status');
            $table->index('publish_status', 'idx_renewal_payment_callbacks_publish_status');
            $table->index('created_at', 'idx_renewal_payment_callbacks_created_at');
            
            // Composite indexes for common query patterns
            $table->index(['msisdn', 'status'], 'idx_renewal_payment_callbacks_msisdn_status');
            $table->index(['publish_status', 'created_at'], 'idx_renewal_payment_callbacks_publish_status_created_at');
            $table->index(['status', 'created_at'], 'idx_renewal_payment_callbacks_status_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('renewal_payment_callbacks');
    }
};