<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rename admins table to staff
        Schema::rename('admins', 'staff');
        
        // Add role column after password
        Schema::table('staff', function (Blueprint $table) {
            $table->string('role')->default('admin')->after('password');
        });
        
        // Rename admin_token column to staff_token
        Schema::table('staff', function (Blueprint $table) {
            $table->renameColumn('admin_token', 'staff_token');
        });
    }

    public function down(): void
    {
        // Rename staff_token back to admin_token
        Schema::table('staff', function (Blueprint $table) {
            $table->renameColumn('staff_token', 'admin_token');
        });
        
        // Remove role column
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('role');
        });
        
        // Rename staff table back to admins
        Schema::rename('staff', 'admins');
    }
};
