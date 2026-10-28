<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mpush_subs_init_notifications')) {
            return;
        }

        Schema::create('mpush_subs_init_notifications', function (Blueprint $table) {
            $table->bigIncrements('ID');
            $table->bigInteger('MSISDN')->nullable()->index();
            $table->enum('CHANNEL', ['SMS', 'USSD', 'APP'])->default('USSD')->index();
            $table->text('USSD_DATA')->nullable();
            $table->integer('IS_OUTGOING')->default(1)->index();
            $table->timestamp('CREATED_AT')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mpush_subs_init_notifications');
    }
};
