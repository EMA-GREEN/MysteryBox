<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('msisdn')->unique()->nullable()->after('email');
            $table->string('user_token', 80)->unique()->nullable()->after('msisdn');
            $table->timestamp('token_expires_at')->nullable()->after('user_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['msisdn', 'user_token', 'token_expires_at']);
        });
    }
}; 