<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // First, rename the existing winners table to winner_entries if it exists
        if (Schema::hasTable('winners')) {
            Schema::rename('winners', 'winner_entries');
        }

        // Now create the new winners table
        Schema::create('winners', function (Blueprint $table) {
            $table->id();
            $table->string('msisdn', 15);
            $table->string('winner_type', 50); // 'airtime', 'wallet', 'jackpot', 'physical'
            $table->text('prize_description');
            $table->decimal('prize_value', 10, 2);
            $table->string('source_table', 50); // 'winner_entries', 'winner_jackpot_entries', 'winner_physical_prizes'
            $table->bigInteger('source_id');
            $table->bigInteger('box_id')->nullable();
            $table->string('box_type', 20)->nullable();
            $table->string('draw_period', 20)->nullable(); // For jackpot winners
            
            // Disbursement tracking
            $table->string('disbursement_status', 20)->default('pending'); // 'pending', 'processing', 'success', 'failed'
            $table->string('disbursement_reference_id', 100)->nullable();
            $table->timestamp('disbursement_request_time')->nullable();
            $table->timestamp('disbursement_response_time')->nullable();
            $table->string('disbursement_response_status', 50)->nullable();
            $table->text('disbursement_response_message')->nullable();
            
            // For wallet disbursements
            $table->decimal('payout_amount', 10, 2)->nullable();
            $table->decimal('tax_percent', 5, 2)->nullable();
            
            $table->timestamp('won_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('disbursed_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('msisdn');
            $table->index('winner_type');
            $table->index('disbursement_status');
            $table->index(['source_table', 'source_id']);
            $table->index('box_id');
            $table->index('draw_period');
            $table->index('won_at');
            $table->index('disbursement_reference_id');
        });
    }

    public function down()
    {
        // Drop the new winners table
        Schema::dropIfExists('winners');
        
        // Rename winner_entries back to winners if it exists
        if (Schema::hasTable('winner_entries')) {
            Schema::rename('winner_entries', 'winners');
        }
    }
};
