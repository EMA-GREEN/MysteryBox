<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('bill_payment_notifications', function (Blueprint $table) {
            $table->boolean('publish_status')->default(false)->after('bill_type');
            $table->index('publish_status');
        });
    }

    public function down()
    {
        Schema::table('bill_payment_notifications', function (Blueprint $table) {
            $table->dropColumn('publish_status');
        });
    }
}; 