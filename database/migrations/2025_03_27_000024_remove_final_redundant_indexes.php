<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Remove redundant indexes from app_notifications table
        if ($this->indexExists('app_notifications', 'app_notifications_notif_id_index')) {
            Schema::table('app_notifications', function (Blueprint $table) {
                // Remove redundant notif_id index since we already have a unique constraint
                $table->dropIndex('app_notifications_notif_id_index');
            });
        }

        // Remove redundant indexes from sms_logs table
        if ($this->indexExists('sms_logs', 'sms_logs_msisdn_index')) {
            Schema::table('sms_logs', function (Blueprint $table) {
                // Remove redundant msisdn index since we have a composite index
                $table->dropIndex('sms_logs_msisdn_index');
            });
        }

        // Remove redundant indexes from payments table
        if ($this->indexExists('payments', 'payments_box_id_index')) {
            Schema::table('payments', function (Blueprint $table) {
                // Remove redundant box_id index since it's already covered by other indexes
                $table->dropIndex('payments_box_id_index');
            });
        }

        // Remove redundant indexes from winner_airtime_disbursements table
        if ($this->indexExists('winner_airtime_disbursements', 'winner_airtime_disbursements_reference_id_index')) {
            Schema::table('winner_airtime_disbursements', function (Blueprint $table) {
                // Remove redundant reference_id index since we have a unique constraint
                $table->dropIndex('winner_airtime_disbursements_reference_id_index');
            });
        }

        // Remove redundant indexes from sms_notifications table
        if ($this->indexExists('sms_notifications', 'sms_notifications_msisdn_index')) {
            Schema::table('sms_notifications', function (Blueprint $table) {
                // Remove redundant msisdn index since we have composite indexes
                $table->dropIndex('sms_notifications_msisdn_index');
            });
        }
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

        // Restore indexes for sms_notifications table
        Schema::table('sms_notifications', function (Blueprint $table) {
            $table->index('msisdn', 'sms_notifications_msisdn_index');
        });
    }

    private function indexExists($table, $indexName)
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }
}; 