<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mystery_boxes', function (Blueprint $table) {
            $table->id();
            $table->string('msisdn');
            $table->string('box_type'); // 'Bronze', 'Silver', 'Gold'
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('pending'); // 'pending', 'opened'
            $table->string('result')->nullable();
            $table->string('prize')->nullable();
            $table->timestamps();

            $table->index('msisdn');
            $table->index('status');
            $table->index(['msisdn', 'status']);
            $table->index('box_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mystery_boxes');
    }
}; 