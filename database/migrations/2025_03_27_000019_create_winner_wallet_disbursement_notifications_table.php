<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('winner_wallet_disbursement_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('msisdn');
            $table->decimal('amount', 10, 2);
            $table->string('box_type');
            $table->string('box_id');
            $table->string('winner_id');
            $table->string('request_id')->nullable();
            $table->boolean('publish_status')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('winner_wallet_disbursement_notifications');
    }
}; 