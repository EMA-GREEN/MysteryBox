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
        Schema::table('winners', function (Blueprint $table) {
            // Add missing indexes for jackpot queries (box_id index already exists)
            $table->index(['prize'], 'winners_prize_index');
            $table->index(['prize', 'awarded_at'], 'winners_prize_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('winners', function (Blueprint $table) {
            // Remove the added indexes
            $table->dropIndex('winners_prize_index');
            $table->dropIndex('winners_prize_date_index');
        });
    }
};
