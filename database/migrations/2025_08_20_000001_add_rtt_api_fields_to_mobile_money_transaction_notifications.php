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
        Schema::table('mobile_money_transaction_notifications', function (Blueprint $table) {
            // Add indexes for commonly queried fields (fields already exist)
            $table->index('conversation_id', 'mmtn_conversation_id_idx');
            $table->index('external_reference', 'mmtn_external_ref_idx');
            $table->index('original_transaction_id', 'mmtn_orig_txn_id_idx');
            $table->index('sales_order_number', 'mmtn_sales_order_idx');
            $table->index('transaction_date', 'mmtn_txn_date_idx');
            $table->index('wallet_type', 'mmtn_wallet_type_idx');
            $table->index('service_name', 'mmtn_service_name_idx');
            $table->index('sender_customer_mobile', 'mmtn_sender_mobile_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mobile_money_transaction_notifications', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex('mmtn_conversation_id_idx');
            $table->dropIndex('mmtn_external_ref_idx');
            $table->dropIndex('mmtn_orig_txn_id_idx');
            $table->dropIndex('mmtn_sales_order_idx');
            $table->dropIndex('mmtn_txn_date_idx');
            $table->dropIndex('mmtn_wallet_type_idx');
            $table->dropIndex('mmtn_service_name_idx');
            $table->dropIndex('mmtn_sender_mobile_idx');
        });
    }
};
