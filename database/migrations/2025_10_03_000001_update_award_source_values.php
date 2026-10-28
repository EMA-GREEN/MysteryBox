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
        // Update existing records to use specific source values based on request_id patterns
        
        // Update renewal awards (request_id starts with 'MYBXR')
        DB::table('mystery_boxes')
            ->where('source', 'award')
            ->where('request_id', 'LIKE', 'MYBXR%')
            ->update(['source' => 'renewal']);
            
        // Update Mixx payment awards (request_id starts with 'MMNYTXN')
        DB::table('mystery_boxes')
            ->where('source', 'award')
            ->where('request_id', 'LIKE', 'MMNYTXN%')
            ->update(['source' => 'mixx_payment']);
            
        // Update any remaining 'award' records to 'mixx_payment' (default for unknown patterns)
        DB::table('mystery_boxes')
            ->where('source', 'award')
            ->update(['source' => 'mixx_payment']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert specific source values back to generic 'award'
        // Only revert those that were originally awards (not purchases or point redemptions)
        DB::table('mystery_boxes')
            ->whereIn('source', ['renewal', 'mixx_payment'])
            ->where(function($query) {
                $query->where('request_id', 'LIKE', 'MYBXR%')
                      ->orWhere('request_id', 'LIKE', 'MMNYTXN%')
                      ->orWhere('request_id', 'NOT LIKE', 'MYSTRYBX%'); // Exclude direct purchases
            })
            ->update(['source' => 'award']);
    }
};