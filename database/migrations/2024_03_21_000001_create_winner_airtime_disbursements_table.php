<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('winner_airtime_disbursements', function (Blueprint $table) {
            $table->id();
            $table->string('msisdn');
            $table->decimal('amount', 10, 2);
            $table->string('reference_id')->unique();
            $table->string('box_type');
            $table->unsignedBigInteger('box_id');
            $table->unsignedBigInteger('winner_id');
            $table->timestamp('request_sent_time')->nullable();
            $table->string('response_status')->nullable();
            $table->text('response_message')->nullable();
            $table->timestamp('response_received_time')->nullable();
            $table->timestamps();

            $table->index('msisdn');
            $table->index('reference_id');
            $table->index('box_id');
            $table->index('winner_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('winner_airtime_disbursements');
    }
}; 