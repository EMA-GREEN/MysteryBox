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
        Schema::table('airtime_disbursement_callbacks', function (Blueprint $table) {
            $table->string('publish_status')->default('pending')->after('callback_received_at');
            $table->index('publish_status');
        });

        Schema::table('wallet_disbursement_callbacks', function (Blueprint $table) {
            $table->string('publish_status')->default('pending')->after('callback_received_at');
            $table->index('publish_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('airtime_disbursement_callbacks', function (Blueprint $table) {
            $table->dropIndex(['publish_status']);
            $table->dropColumn('publish_status');
        });

        Schema::table('wallet_disbursement_callbacks', function (Blueprint $table) {
            $table->dropIndex(['publish_status']);
            $table->dropColumn('publish_status');
        });
    }
};
