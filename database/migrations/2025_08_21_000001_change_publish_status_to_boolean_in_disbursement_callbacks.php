<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update airtime_disbursement_callbacks table
        Schema::table('airtime_disbursement_callbacks', function (Blueprint $table) {
            // First, update existing data to convert string values to boolean
            DB::statement("UPDATE airtime_disbursement_callbacks SET publish_status = CASE 
                WHEN publish_status = 'pending' THEN 0 
                WHEN publish_status = 'published' THEN 1 
                WHEN publish_status = 'processed' THEN 1 
                ELSE 0 END");
            
            // Change column type to boolean
            $table->boolean('publish_status')->default(false)->change();
        });

        // Update wallet_disbursement_callbacks table
        Schema::table('wallet_disbursement_callbacks', function (Blueprint $table) {
            // First, update existing data to convert string values to boolean
            DB::statement("UPDATE wallet_disbursement_callbacks SET publish_status = CASE 
                WHEN publish_status = 'pending' THEN 0 
                WHEN publish_status = 'published' THEN 1 
                WHEN publish_status = 'processed' THEN 1 
                ELSE 0 END");
            
            // Change column type to boolean
            $table->boolean('publish_status')->default(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert airtime_disbursement_callbacks table
        Schema::table('airtime_disbursement_callbacks', function (Blueprint $table) {
            // Convert boolean back to string
            DB::statement("UPDATE airtime_disbursement_callbacks SET publish_status = CASE 
                WHEN publish_status = 0 THEN 'pending' 
                WHEN publish_status = 1 THEN 'published' 
                ELSE 'pending' END");
            
            // Change column type back to string
            $table->string('publish_status')->default('pending')->change();
        });

        // Revert wallet_disbursement_callbacks table
        Schema::table('wallet_disbursement_callbacks', function (Blueprint $table) {
            // Convert boolean back to string
            DB::statement("UPDATE wallet_disbursement_callbacks SET publish_status = CASE 
                WHEN publish_status = 0 THEN 'pending' 
                WHEN publish_status = 1 THEN 'published' 
                ELSE 'pending' END");
            
            // Change column type back to string
            $table->string('publish_status')->default('pending')->change();
        });
    }
};
