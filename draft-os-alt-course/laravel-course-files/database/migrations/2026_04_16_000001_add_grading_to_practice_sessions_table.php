<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('practice_sessions', function (Blueprint $table) {
            $table->unsignedSmallInteger('last_check_score')->nullable();
            $table->unsignedSmallInteger('last_check_max_score')->nullable();
            $table->json('last_check_hints')->nullable();
            $table->unsignedSmallInteger('accepted_practice_score')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('practice_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'last_check_score',
                'last_check_max_score',
                'last_check_hints',
                'accepted_practice_score',
            ]);
        });
    }
};
