<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('subscribers', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('msisdn')->unique();
            $table->string('customer_type', 20)->nullable();
            $table->integer('boom_credit')->default(0);
            $table->dateTime('last_int_billing');
            $table->integer('total_int_billed_amount')->default(0);
            $table->dateTime('last_renewal_billing')->nullable();
            $table->integer('total_renewal_billed_amount')->default(0);
            $table->integer('credit')->default(0);
            $table->dateTime('last_interaction');
            $table->enum('activity_status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->enum('dnd_status', ['ON', 'OFF'])->default('OFF');
            $table->string('sub_channel', 20)->default('SMS');
            $table->timestamp('record_date')->useCurrent();

            $table->index('customer_type');
            $table->index('last_int_billing');
            $table->index('activity_status');
            $table->index('dnd_status');
            $table->index('sub_channel');
            $table->index('record_date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('subscribers');
    }
}; 