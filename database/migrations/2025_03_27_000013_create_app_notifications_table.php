<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('msisdn');
            $table->string('action');
            $table->boolean('publish_status')->default(false);
            $table->string('notif_id')->unique();
            $table->string('box_type')->nullable();
            $table->unsignedBigInteger('box_id')->nullable();
            $table->timestamps();

            $table->index('msisdn');
            $table->index('publish_status');
            $table->index('notif_id');
            $table->index('box_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('app_notifications');
    }
}; 