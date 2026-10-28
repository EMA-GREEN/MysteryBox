<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::create('user_access_token', function (Blueprint $table) {
            $table->increments('ID');
            $table->text('ACCESS_TOKEN');
            $table->text('REFERESH_TOKEN')->nullable();
            $table->dateTime('CREATED_AT')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->dateTime('EXPIRES_AT')->nullable();

            $table->index('CREATED_AT');
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_access_token');
    }
}; 