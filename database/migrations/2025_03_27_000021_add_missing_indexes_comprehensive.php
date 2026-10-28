<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add missing indexes for payments table
        if (!$this->indexExists('payments', 'payments_msisdn_box_type_created_at_index')) {
            Schema::table('payments', function (Blueprint $table) {
                // Index for queries by msisdn, box_type (for latest payment)
                $table->index(['msisdn', 'box_type', 'created_at'], 'payments_msisdn_box_type_created_at_index');
            });
        }

        // Add missing indexes for sms_notifications table
        if (!$this->indexExists('sms_notifications', 'sms_notifications_msisdn_message_index')) {
            Schema::table('sms_notifications', function (Blueprint $table) {
                // Index for queries by msisdn and message
                $table->index(['msisdn', 'message'], 'sms_notifications_msisdn_message_index');
            });
        }

        if (!$this->indexExists('sms_notifications', 'sms_notifications_msisdn_created_at_index')) {
            Schema::table('sms_notifications', function (Blueprint $table) {
                // Index for queries by msisdn and created_at
                $table->index(['msisdn', 'created_at'], 'sms_notifications_msisdn_created_at_index');
            });
        }

        if (!$this->indexExists('sms_notifications', 'sms_notifications_message_index')) {
            Schema::table('sms_notifications', function (Blueprint $table) {
                // Index for message text searches (for keyword matching)
                $table->index('message', 'sms_notifications_message_index');
            });
        }

        // Add missing indexes for winner_wallet_disbursement_notifications table
        if (!$this->indexExists('winner_wallet_disbursement_notifications', 'winner_wallet_disbursement_notifications_msisdn_index')) {
            Schema::table('winner_wallet_disbursement_notifications', function (Blueprint $table) {
                // Index for queries by msisdn
                $table->index('msisdn', 'winner_wallet_disbursement_notifications_msisdn_index');
            });
        }

        if (!$this->indexExists('winner_wallet_disbursement_notifications', 'winner_wallet_disbursement_notifications_request_id_index')) {
            Schema::table('winner_wallet_disbursement_notifications', function (Blueprint $table) {
                // Index for queries by request_id
                $table->index('request_id', 'winner_wallet_disbursement_notifications_request_id_index');
            });
        }

        if (!$this->indexExists('winner_wallet_disbursement_notifications', 'winner_wallet_disbursement_notifications_winner_id_index')) {
            Schema::table('winner_wallet_disbursement_notifications', function (Blueprint $table) {
                // Index for queries by winner_id
                $table->index('winner_id', 'winner_wallet_disbursement_notifications_winner_id_index');
            });
        }

        if (!$this->indexExists('winner_wallet_disbursement_notifications', 'winner_wallet_disbursement_notifications_box_id_index')) {
            Schema::table('winner_wallet_disbursement_notifications', function (Blueprint $table) {
                // Index for queries by box_id
                $table->index('box_id', 'winner_wallet_disbursement_notifications_box_id_index');
            });
        }

        if (!$this->indexExists('winner_wallet_disbursement_notifications', 'winner_wallet_disbursement_notifications_publish_status_index')) {
            Schema::table('winner_wallet_disbursement_notifications', function (Blueprint $table) {
                // Index for queries by publish_status
                $table->index('publish_status', 'winner_wallet_disbursement_notifications_publish_status_index');
            });
        }

        // Add missing indexes for winner_wallet_disbursements table
        if (!$this->indexExists('winner_wallet_disbursements', 'winner_wallet_disbursements_msisdn_index')) {
            Schema::table('winner_wallet_disbursements', function (Blueprint $table) {
                // Index for queries by msisdn
                $table->index('msisdn', 'winner_wallet_disbursements_msisdn_index');
            });
        }

        if (!$this->indexExists('winner_wallet_disbursements', 'winner_wallet_disbursements_reference_id_index')) {
            Schema::table('winner_wallet_disbursements', function (Blueprint $table) {
                // Index for queries by reference_id
                $table->index('reference_id', 'winner_wallet_disbursements_reference_id_index');
            });
        }

        if (!$this->indexExists('winner_wallet_disbursements', 'winner_wallet_disbursements_winner_id_index')) {
            Schema::table('winner_wallet_disbursements', function (Blueprint $table) {
                // Index for queries by winner_id
                $table->index('winner_id', 'winner_wallet_disbursements_winner_id_index');
            });
        }

        if (!$this->indexExists('winner_wallet_disbursements', 'winner_wallet_disbursements_box_id_index')) {
            Schema::table('winner_wallet_disbursements', function (Blueprint $table) {
                // Index for queries by box_id
                $table->index('box_id', 'winner_wallet_disbursements_box_id_index');
            });
        }

        if (!$this->indexExists('winner_wallet_disbursements', 'winner_wallet_disbursements_created_at_index')) {
            Schema::table('winner_wallet_disbursements', function (Blueprint $table) {
                // Index for queries by created_at
                $table->index('created_at', 'winner_wallet_disbursements_created_at_index');
            });
        }

        // Add missing indexes for users table
        if (!$this->indexExists('users', 'users_email_index')) {
            Schema::table('users', function (Blueprint $table) {
                // Index for email queries (if not already unique)
                $table->index('email', 'users_email_index');
            });
        }
    }

    public function down(): void
    {
        // Remove indexes from payments table
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_msisdn_box_type_created_at_index');
        });

        // Remove indexes from sms_notifications table
        Schema::table('sms_notifications', function (Blueprint $table) {
            $table->dropIndex('sms_notifications_msisdn_message_index');
            $table->dropIndex('sms_notifications_msisdn_created_at_index');
            $table->dropIndex('sms_notifications_message_index');
        });

        // Remove indexes from winner_wallet_disbursement_notifications table
        Schema::table('winner_wallet_disbursement_notifications', function (Blueprint $table) {
            $table->dropIndex('winner_wallet_disbursement_notifications_msisdn_index');
            $table->dropIndex('winner_wallet_disbursement_notifications_request_id_index');
            $table->dropIndex('winner_wallet_disbursement_notifications_winner_id_index');
            $table->dropIndex('winner_wallet_disbursement_notifications_box_id_index');
            $table->dropIndex('winner_wallet_disbursement_notifications_publish_status_index');
        });

        // Remove indexes from winner_wallet_disbursements table
        Schema::table('winner_wallet_disbursements', function (Blueprint $table) {
            $table->dropIndex('winner_wallet_disbursements_msisdn_index');
            $table->dropIndex('winner_wallet_disbursements_reference_id_index');
            $table->dropIndex('winner_wallet_disbursements_winner_id_index');
            $table->dropIndex('winner_wallet_disbursements_box_id_index');
            $table->dropIndex('winner_wallet_disbursements_created_at_index');
        });

        // Remove indexes from users table
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_email_index');
        });
    }

    private function indexExists($table, $indexName)
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }
}; 