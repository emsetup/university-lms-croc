<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('courses') || Schema::hasColumn('courses', 'show_module_progress')) {
            return;
        }

        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('show_module_progress')->default(true)->after('unlock_all_modules');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('courses') || ! Schema::hasColumn('courses', 'show_module_progress')) {
            return;
        }

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('show_module_progress');
        });
    }
};
