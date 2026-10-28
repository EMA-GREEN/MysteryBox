<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('bill_payment_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('msisdn')->index();
            $table->decimal('amount', 12, 2);
            $table->string('bill_reference')->nullable()->index();
            $table->string('status')->nullable();
            $table->string('bill_type')->nullable()->index();
            $table->timestamp('received_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('bill_payment_notifications');
    }
}; 