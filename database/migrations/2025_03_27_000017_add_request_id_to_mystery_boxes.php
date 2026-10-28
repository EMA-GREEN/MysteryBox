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
        Schema::table('mystery_boxes', function (Blueprint $table) {
            $table->string('request_id')->nullable()->after('box_type');
            $table->index('request_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mystery_boxes', function (Blueprint $table) {
            $table->dropIndex(['request_id']);
            $table->dropColumn('request_id');
        });
    }
}; 