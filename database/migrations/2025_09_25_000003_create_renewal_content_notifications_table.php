<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('renewal_content_notifications', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('msisdn')->nullable();
            $table->string('request_id', 1000);
            $table->string('payment_type', 1000)->nullable();
            $table->string('text', 1000)->nullable();
            $table->boolean('publish_status')->default(false);
            $table->timestamps();

            // Optimized indexes - removed redundant individual indexes
            // Primary composite index for main notification queries
            $table->index(['msisdn', 'publish_status', 'created_at'], 'idx_msisdn_publish_created'); // Covers most common query patterns
            
            // Specific indexes for large text fields (partial indexes)
            $table->index([DB::raw('request_id(255)')], 'idx_request_id_partial');
            $table->index([DB::raw('payment_type(255)')], 'idx_payment_type_partial');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('renewal_content_notifications');
    }
};