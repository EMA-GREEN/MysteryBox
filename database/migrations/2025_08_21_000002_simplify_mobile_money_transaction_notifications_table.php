<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop all existing columns and recreate with simplified structure
        Schema::table('mobile_money_transaction_notifications', function (Blueprint $table) {
            // Drop all existing columns except id and timestamps
            $table->dropColumn([
                'channel',
                'channel_of_receiver',
                'channel_of_sender',
                'conversation_id',
                'credit_amount',
                'debit_amount',
                'dest_customer_mobile',
                'destination_balance_after',
                'destination_balance_before',
                'dest_total_fees',
                'external_reference',
                'original_amount',
                'original_transaction_id',
                'payable_amount',
                'sales_order_number',
                'sales_order_status',
                'sender_account_number',
                'sender_customer_id',
                'sender_customer_mobile',
                'service_id',
                'service_name',
                'source_balance_after',
                'source_balance_before',
                'source_brand_fee',
                'source_customer_mobile',
                'source_total_fees',
                'source_user_mobile',
                'transaction_date',
                'wallet_type',
                'publish_status'
            ]);
        });

        // Add the new simplified columns
        Schema::table('mobile_money_transaction_notifications', function (Blueprint $table) {
            $table->string('msisdn')->index();
            $table->string('txn_type')->index();
            $table->decimal('amount', 12, 2);
            $table->string('reference')->index();
            $table->string('status');
            $table->boolean('publish_status')->default(false)->index();
            $table->timestamp('received_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the simplified columns
        Schema::table('mobile_money_transaction_notifications', function (Blueprint $table) {
            $table->dropColumn([
                'msisdn',
                'txn_type',
                'amount',
                'reference',
                'status',
                'publish_status',
                'received_at'
            ]);
        });

        // Recreate the original columns (simplified version for rollback)
        Schema::table('mobile_money_transaction_notifications', function (Blueprint $table) {
            $table->string('conversation_id')->index();
            $table->string('external_reference')->index();
            $table->string('original_transaction_id')->index();
            $table->string('sales_order_number')->index();
            $table->timestamp('transaction_date')->index();
            $table->string('sender_customer_mobile')->index();
            $table->decimal('original_amount', 12, 2)->nullable();
            $table->string('sales_order_status')->nullable();
            $table->boolean('publish_status')->default(false)->index();
        });
    }
};
