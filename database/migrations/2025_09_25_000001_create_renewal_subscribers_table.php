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
        Schema::create('renewal_subscribers', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('msisdn')->unique();
            $table->enum('activity_status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->enum('dnd_status', ['ON', 'OFF'])->default('OFF');
            $table->integer('priority')->nullable()->default(1);
            $table->string('retry_status', 60)->nullable();
            $table->integer('retry_count')->nullable();
            $table->boolean('publish_status')->default(false);
            $table->dateTime('last_renewal_billing')->nullable();
            $table->dateTime('next_retrial_period')->nullable();
            $table->timestamp('record_date')->useCurrent();

            // Optimized indexes - removed duplicates and unnecessary individual indexes
            // Primary composite index for main renewal queries (covers both Query 1 & 2)
            $table->index(['activity_status', 'dnd_status', 'retry_count', 'publish_status', 'last_renewal_billing', 'id'], 'idx_renewal_main');
            
            // Additional indexes for specific use cases
            $table->index('priority'); // For priority-based queries
            $table->index('record_date'); // For date-based filtering and reporting
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('renewal_subscribers');
    }
};