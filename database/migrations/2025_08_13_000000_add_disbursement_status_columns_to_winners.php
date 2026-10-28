<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('winners', function (Blueprint $table) {
            // Add separate status columns for each disbursement type
            $table->string('airtime_disbursement_status', 20)->default('pending')->after('disbursement_status');
            $table->string('wallet_disbursement_status', 20)->default('pending')->after('airtime_disbursement_status');
            
            // Add separate reference IDs for each disbursement type
            $table->string('airtime_disbursement_reference_id', 100)->nullable()->after('disbursement_reference_id');
            $table->string('wallet_disbursement_reference_id', 100)->nullable()->after('airtime_disbursement_reference_id');
            
            // Add separate timestamps for each disbursement type
            $table->timestamp('airtime_disbursed_at')->nullable()->after('disbursed_at');
            $table->timestamp('wallet_disbursed_at')->nullable()->after('airtime_disbursed_at');
            
            // Add indexes for the new columns
            $table->index('airtime_disbursement_status');
            $table->index('wallet_disbursement_status');
        });
    }

    public function down()
    {
        Schema::table('winners', function (Blueprint $table) {
            $table->dropIndex(['airtime_disbursement_status']);
            $table->dropIndex(['wallet_disbursement_status']);
            
            $table->dropColumn([
                'airtime_disbursement_status',
                'wallet_disbursement_status',
                'airtime_disbursement_reference_id',
                'wallet_disbursement_reference_id',
                'airtime_disbursed_at',
                'wallet_disbursed_at'
            ]);
        });
    }
};
