<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('msisdn');
            $table->text('message');
            $table->boolean('publish_status')->default(0);
            $table->string('msg_id', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('msisdn');
            $table->index('publish_status');
            $table->index('msg_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_notifications');
    }
}; 