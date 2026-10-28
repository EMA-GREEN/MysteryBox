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
        Schema::create('winner_jackpot_entries', function (Blueprint $table) {
            $table->id();
            $table->string('msisdn')->index();
            $table->unsignedBigInteger('winner_id');
            $table->unsignedBigInteger('box_id')->nullable();
            $table->string('box_type')->nullable();
            $table->decimal('jackpot_amount', 10, 2);
            $table->timestamp('entry_date')->useCurrent();
            $table->date('draw_date')->nullable();
            $table->enum('status', ['pending', 'active', 'won', 'lost'])->default('pending')->index();
            $table->decimal('prize_amount', 10, 2)->nullable();
            $table->timestamps();
            
            // Indexes for efficient queries
            $table->index(['msisdn', 'status'], 'winner_jackpot_msisdn_status_index');
            $table->index(['draw_date'], 'winner_jackpot_draw_date_index');
            $table->index(['entry_date'], 'winner_jackpot_entry_date_index');
            $table->index(['winner_id'], 'winner_jackpot_winner_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('winner_jackpot_entries');
    }
};
