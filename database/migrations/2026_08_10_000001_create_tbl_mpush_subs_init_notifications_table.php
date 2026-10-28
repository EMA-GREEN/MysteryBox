<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_mpush_subs_init_notifications', function (Blueprint $table) {
            $table->bigIncrements('ID');
            $table->bigInteger('MSISDN')->nullable()->index();
            $table->enum('CHANNEL', ['SMS', 'USSD', 'APP'])->default('USSD')->index();
            $table->integer('IS_OUTGOING')->default(1)->index();
            $table->timestamp('CREATED_AT')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_mpush_subs_init_notifications');
    }
};
