<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Rename table
        Schema::rename('bill_payment_notifications', 'mobile_money_transaction_notifications');
        // Rename columns
        Schema::table('mobile_money_transaction_notifications', function (Blueprint $table) {
            $table->renameColumn('bill_reference', 'reference');
            $table->renameColumn('bill_type', 'txn_type');
        });
    }

    public function down()
    {
        Schema::rename('mobile_money_transaction_notifications', 'bill_payment_notifications');
        Schema::table('bill_payment_notifications', function (Blueprint $table) {
            $table->renameColumn('reference', 'bill_reference');
            $table->renameColumn('txn_type', 'bill_type');
        });
    }
}; 