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
        Schema::table('payment_notifications', function (Blueprint $table) {
            $table->unsignedBigInteger('box_id')->nullable()->after('channel');
            $table->index('box_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_notifications', function (Blueprint $table) {
            $table->dropIndex(['box_id']);
            $table->dropColumn('box_id');
        });
    }
}; 