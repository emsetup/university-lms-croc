<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('courses') || Schema::hasColumn('courses', 'unlock_all_modules')) {
            return;
        }

        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('unlock_all_modules')->default(false)->after('difficulty_flags_enabled');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('courses') || ! Schema::hasColumn('courses', 'unlock_all_modules')) {
            return;
        }

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('unlock_all_modules');
        });
    }
};
