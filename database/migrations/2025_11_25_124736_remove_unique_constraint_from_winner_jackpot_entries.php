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
        Schema::table('winner_jackpot_entries', function (Blueprint $table) {
            // Remove unique constraint to allow multiple entries per user per draw period
            if (Schema::hasIndex('winner_jackpot_entries', 'winner_jackpot_unique_user_period')) {
                $table->dropUnique('winner_jackpot_unique_user_period');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('winner_jackpot_entries', function (Blueprint $table) {
            // Re-add unique constraint if rolling back
            if (!Schema::hasIndex('winner_jackpot_entries', 'winner_jackpot_unique_user_period')) {
                $table->unique(['msisdn', 'draw_period', 'status'], 'winner_jackpot_unique_user_period');
            }
        });
    }
};

