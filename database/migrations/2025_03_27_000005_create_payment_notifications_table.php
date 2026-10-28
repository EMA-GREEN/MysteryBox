<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('msisdn');
            $table->string('request_id');
            $table->string('payment_type'); // 'PurchaseBox', 'OpenBox'
            $table->decimal('amount', 10, 2);
            $table->string('box_type'); // 'Bronze', 'Silver', 'Gold'
            $table->string('channel')->default('APP');
            $table->boolean('publish_status')->default(false);
            $table->timestamps();

            $table->index('msisdn');
            $table->index('request_id');
            $table->index('publish_status');
            $table->index('payment_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_notifications');
    }
}; 