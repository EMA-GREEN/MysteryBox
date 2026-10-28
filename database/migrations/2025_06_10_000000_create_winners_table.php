<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('winners', function (Blueprint $table) {
            $table->id();
            $table->string('msisdn')->index();
            $table->string('box_type')->index();
            $table->string('prize');
            $table->string('prize_type');
            $table->decimal('amount', 10, 2);
            $table->timestamp('awarded_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('winners');
    }
}; 