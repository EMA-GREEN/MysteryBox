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
        Schema::create('wallet_disbursement_callbacks', function (Blueprint $table) {
            $table->id();
            $table->string('reference_id')->index();
            $table->string('msisdn');
            $table->decimal('amount', 10, 2);
            $table->string('status'); // SUCCESS, FAILED, PENDING, etc.
            $table->text('message')->nullable();
            $table->string('transaction_id')->nullable();
            $table->decimal('balance', 10, 2)->nullable();
            $table->json('raw_response')->nullable(); // Store complete callback data
            $table->string('callback_source')->nullable(); // Which service sent the callback
            $table->string('ip_address')->nullable(); // IP of callback sender
            $table->timestamp('callback_received_at');
            $table->timestamps();
            
            $table->index(['reference_id', 'status']);
            $table->index('callback_received_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_disbursement_callbacks');
    }
};
