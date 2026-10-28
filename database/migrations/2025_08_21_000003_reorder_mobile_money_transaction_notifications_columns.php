<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the existing table completely and recreate it with the correct column order
        Schema::dropIfExists('mobile_money_transaction_notifications');
        
        Schema::create('mobile_money_transaction_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('msisdn')->index();
            $table->string('txn_type')->index();
            $table->decimal('amount', 12, 2);
            $table->string('reference')->index();
            $table->string('status');
            $table->boolean('publish_status')->default(false)->index();
            $table->timestamp('received_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the table and recreate with original column order
        Schema::dropIfExists('mobile_money_transaction_notifications');
        
        Schema::create('mobile_money_transaction_notifications', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('msisdn')->index();
            $table->string('txn_type')->index();
            $table->decimal('amount', 12, 2);
            $table->string('reference')->index();
            $table->string('status');
            $table->boolean('publish_status')->default(false)->index();
            $table->timestamp('received_at')->nullable()->index();
        });
    }
};
