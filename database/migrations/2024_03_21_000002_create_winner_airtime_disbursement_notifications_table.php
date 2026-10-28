<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('winner_airtime_disbursement_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('msisdn');
            $table->decimal('amount', 10, 2);
            $table->string('box_type');
            $table->unsignedBigInteger('box_id');
            $table->unsignedBigInteger('winner_id');
            $table->string('request_id')->nullable();
            $table->boolean('publish_status')->default(false);
            $table->timestamps();

            $table->index('msisdn');
            $table->index('box_id');
            $table->index('winner_id');
            $table->index('publish_status');
            $table->index('request_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('winner_airtime_disbursement_notifications');
    }
}; 