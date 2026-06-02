<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('courses') || Schema::hasColumn('courses', 'difficulty_flags_enabled')) {
            return;
        }

        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('difficulty_flags_enabled')->default(true)->after('default_pass_percent');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('courses') || ! Schema::hasColumn('courses', 'difficulty_flags_enabled')) {
            return;
        }

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('difficulty_flags_enabled');
        });
    }
};

