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
        // Update POINT_REDEEM to point_redeem for consistency
        DB::table('mystery_boxes')
            ->where('source', 'POINT_REDEEM')
            ->update(['source' => 'point_redeem']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert point_redeem back to POINT_REDEEM
        DB::table('mystery_boxes')
            ->where('source', 'point_redeem')
            ->update(['source' => 'POINT_REDEEM']);
    }
};