<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->decimal('today_txns_amount', 10, 2)->default(0)->after('txns_count');
            $table->index('today_txns_amount');
        });
    }

    public function down()
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropIndex(['today_txns_amount']);
            $table->dropColumn('today_txns_amount');
        });
    }
};