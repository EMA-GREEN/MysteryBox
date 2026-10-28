<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mystery_box_award_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('msisdn');
            $table->string('box_type');
            $table->string('request_id');
            $table->string('status')->default('pending');
            $table->boolean('publish_status')->default(false);
            $table->timestamps();

            $table->index('msisdn');
            $table->index('box_type');
            $table->index('request_id');
            $table->index('publish_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mystery_box_award_notifications');
    }
}; 