<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove redundant indexes from app_notifications table
        Schema::table('app_notifications', function (Blueprint $table) {
            // Remove redundant notif_id index since we already have a unique constraint
            $table->dropIndex('app_notifications_notif_id_index');
        });

        // Remove redundant indexes from sms_logs table
        Schema::table('sms_logs', function (Blueprint $table) {
            // Remove redundant msisdn index since we have a composite index
            $table->dropIndex('sms_logs_msisdn_index');
        });

        // Remove redundant indexes from payments table
        Schema::table('payments', function (Blueprint $table) {
            // Remove redundant box_id index since it's already covered by other indexes
            $table->dropIndex('payments_box_id_index');
        });

        // Remove redundant indexes from winner_airtime_disbursements table
        Schema::table('winner_airtime_disbursements', function (Blueprint $table) {
            // Remove redundant reference_id index since we have a unique constraint
            $table->dropIndex('winner_airtime_disbursements_reference_id_index');
        });
    }

    public function down(): void
    {
        // Restore indexes for app_notifications table
        Schema::table('app_notifications', function (Blueprint $table) {
            $table->index('notif_id', 'app_notifications_notif_id_index');
        });

        // Restore indexes for sms_logs table
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->index('msisdn', 'sms_logs_msisdn_index');
        });

        // Restore indexes for payments table
        Schema::table('payments', function (Blueprint $table) {
            $table->index('box_id', 'payments_box_id_index');
        });

        // Restore indexes for winner_airtime_disbursements table
        Schema::table('winner_airtime_disbursements', function (Blueprint $table) {
            $table->index('reference_id', 'winner_airtime_disbursements_reference_id_index');
        });
    }
}; 