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
        Schema::create('winner_physical_prizes', function (Blueprint $table) {
            $table->id();
            $table->string('msisdn')->index();
            $table->unsignedBigInteger('winner_id');
            $table->unsignedBigInteger('box_id')->nullable();
            $table->string('box_type')->nullable();
            $table->enum('device_type', ['5g_router', 'feature_phone', 'tech_gadget', 'other_device'])->index();
            $table->string('specific_item')->nullable(); // e.g., 'iPhone 15', 'Samsung Galaxy'
            $table->enum('status', ['pending', 'processing', 'shipped', 'delivered', 'cancelled'])->default('pending')->index();
            $table->string('tracking_number')->nullable();
            $table->text('shipping_address')->nullable();
            $table->date('delivery_date')->nullable();
            $table->timestamps();
            
            // Indexes for efficient queries
            $table->index(['msisdn', 'status'], 'winner_physical_msisdn_status_index');
            $table->index(['device_type'], 'winner_physical_device_type_index');
            $table->index(['created_at'], 'winner_physical_created_at_index');
            $table->index(['winner_id'], 'winner_physical_winner_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('winner_physical_prizes');
    }
};
