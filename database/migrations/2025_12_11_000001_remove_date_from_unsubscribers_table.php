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
        Schema::table('unsubscribers', function (Blueprint $table) {
            $table->dropIndex(['date']);
            $table->dropColumn('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unsubscribers', function (Blueprint $table) {
            $table->date('date')->after('mo_id');
            $table->index('date');
        });
    }
};

