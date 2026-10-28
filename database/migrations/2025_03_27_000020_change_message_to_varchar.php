<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Change message column from TEXT to VARCHAR(255) in sms_notifications table
        Schema::table('sms_notifications', function (Blueprint $table) {
            $table->string('message', 255)->change();
        });
        
        // Change message column from TEXT to VARCHAR(255) in sms_logs table (if it exists)
        if (Schema::hasColumn('sms_logs', 'message')) {
            Schema::table('sms_logs', function (Blueprint $table) {
                $table->string('message', 255)->change();
            });
        }
    }

    public function down(): void
    {
        // Revert message column back to TEXT in sms_notifications table
        Schema::table('sms_notifications', function (Blueprint $table) {
            $table->text('message')->change();
        });
        
        // Revert message column back to TEXT in sms_logs table (if it exists)
        if (Schema::hasColumn('sms_logs', 'message')) {
            Schema::table('sms_logs', function (Blueprint $table) {
                $table->text('message')->change();
            });
        }
    }
}; 