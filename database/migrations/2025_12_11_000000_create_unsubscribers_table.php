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
        Schema::create('unsubscribers', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('msisdn');
            $table->enum('channel', ['SMS', 'APP', 'ADMIN'])->default('SMS');
            $table->unsignedBigInteger('mo_id')->nullable()->comment('ID from sms_notifications or app_notifications');
            $table->date('date');
            $table->timestamps();

            $table->index('msisdn');
            $table->index('channel');
            $table->index('mo_id');
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unsubscribers');
    }
};

