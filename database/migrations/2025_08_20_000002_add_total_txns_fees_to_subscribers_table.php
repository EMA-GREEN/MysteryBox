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
        Schema::table('subscribers', function (Blueprint $table) {
            $table->decimal('total_txns_fees', 15, 2)->default(0)->after('total_txns_amount');
            $table->index('total_txns_fees');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropIndex(['total_txns_fees']);
            $table->dropColumn('total_txns_fees');
        });
    }
};
