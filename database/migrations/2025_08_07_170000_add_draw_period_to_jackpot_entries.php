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
            // Add draw_period field to track which draw this entry belongs to (if it doesn't exist)
            if (!Schema::hasColumn('winner_jackpot_entries', 'draw_period')) {
                $table->enum('draw_period', ['daily', 'weekly', 'monthly'])->after('jackpot_amount')->index();
            }
            
            // Add unique constraint to prevent multiple entries per user per draw period
            if (!Schema::hasIndex('winner_jackpot_entries', 'winner_jackpot_unique_user_period')) {
                $table->unique(['msisdn', 'draw_period', 'status'], 'winner_jackpot_unique_user_period');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('winner_jackpot_entries', function (Blueprint $table) {
            $table->dropUnique('winner_jackpot_unique_user_period');
            $table->dropColumn('draw_period');
        });
    }
}; 