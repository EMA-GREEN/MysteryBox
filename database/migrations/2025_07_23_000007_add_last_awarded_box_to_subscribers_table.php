<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->string('last_awarded_box')->nullable()->after('total_txns_amount');
        });
    }

    public function down()
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropColumn('last_awarded_box');
        });
    }
}; 