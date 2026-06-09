<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('courses') || Schema::hasColumn('courses', 'assessment_enabled')) {
            return;
        }

        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('assessment_enabled')->default(true)->after('show_module_progress');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('courses') || ! Schema::hasColumn('courses', 'assessment_enabled')) {
            return;
        }

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('assessment_enabled');
        });
    }
};
