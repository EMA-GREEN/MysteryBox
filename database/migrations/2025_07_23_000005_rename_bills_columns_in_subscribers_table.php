<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->renameColumn('bills_done', 'txns_count');
            $table->renameColumn('total_billed_amount', 'total_txns_amount');
        });
    }

    public function down()
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->renameColumn('txns_count', 'bills_done');
            $table->renameColumn('total_txns_amount', 'total_billed_amount');
        });
    }
}; 