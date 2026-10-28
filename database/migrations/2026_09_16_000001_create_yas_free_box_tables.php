<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('yas_free_box_daily_counters')) {
            Schema::create('yas_free_box_daily_counters', function (Blueprint $table) {
                $table->id();
                $table->date('open_date')->unique();
                $table->unsignedInteger('open_count')->default(0);
                $table->unsignedInteger('winners_count')->default(0);
                $table->decimal('total_prize_amount', 14, 2)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('yas_free_box_openings')) {
            Schema::create('yas_free_box_openings', function (Blueprint $table) {
                $table->id();
                $table->string('msisdn', 20)->index();
                $table->string('source', 32)->index(); // mixx_app | yas_app
                $table->date('open_date');
                $table->unsignedInteger('open_index');
                $table->unsignedTinyInteger('user_open_number');
                $table->boolean('is_winner')->default(false);
                $table->decimal('prize_amount', 12, 2)->default(0);
                $table->string('prize_type', 32)->nullable();
                $table->string('status', 32)->default('opened');
                $table->string('reference_id', 64)->nullable();
                $table->string('request_id', 64)->unique();
                $table->timestamps();

                $table->index(['msisdn', 'open_date']);
                $table->index(['open_date', 'open_index']);
                $table->index(['open_date', 'is_winner']);
                $table->index(['source', 'open_date']);
            });
        }

        if (!Schema::hasTable('yas_free_box_daily_usage')) {
            Schema::create('yas_free_box_daily_usage', function (Blueprint $table) {
                $table->id();
                $table->string('msisdn', 20);
                $table->date('open_date');
                $table->unsignedTinyInteger('opens_used')->default(0);
                $table->timestamps();

                $table->unique(['msisdn', 'open_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('yas_free_box_daily_usage');
        Schema::dropIfExists('yas_free_box_openings');
        Schema::dropIfExists('yas_free_box_daily_counters');
    }
};
