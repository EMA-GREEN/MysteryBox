<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('winner_wallet_disbursements', function (Blueprint $table) {
            $table->id();
            $table->string('msisdn');
            $table->decimal('amount', 10, 2);
            $table->decimal('payout_amount', 10, 2);
            $table->decimal('tax_percent', 5, 2);
            $table->string('reference_id');
            $table->string('box_type');
            $table->string('box_id');
            $table->string('response_status')->nullable();
            $table->text('response_message')->nullable();
            $table->timestamp('request_sent_time')->nullable();
            $table->timestamp('response_received_time')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('winner_wallet_disbursements');
    }
}; 