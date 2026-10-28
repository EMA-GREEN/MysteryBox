<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('msisdn');
            $table->string('request_id')->unique();
            $table->string('payment_type'); // 'PurchaseBox', 'OpenBox'
            $table->decimal('amount', 10, 2);
            $table->string('box_type'); // 'Bronze', 'Silver', 'Gold'
            $table->string('channel')->default('APP'); // 'APP', 'SMS'
            $table->string('status')->default('pending'); // 'pending', 'success', 'failed', etc.
            $table->string('billing_system')->nullable();
            $table->string('ack_status_code')->nullable();
            $table->string('ack_description')->nullable();
            $table->string('callback_status_code')->nullable();
            $table->string('callback_description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
}; 