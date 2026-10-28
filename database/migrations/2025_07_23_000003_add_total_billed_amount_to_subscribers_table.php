<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->decimal('total_billed_amount', 16, 2)->default(0)->after('bills_done');
        });
    }

    public function down()
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropColumn('total_billed_amount');
        });
    }
}; 