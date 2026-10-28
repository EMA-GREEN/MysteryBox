<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_callbacks', function (Blueprint $table) {
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
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_callbacks');
    }
}; 