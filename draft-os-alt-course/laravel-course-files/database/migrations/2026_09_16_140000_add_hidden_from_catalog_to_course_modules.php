<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Скрыть модуль с дашборда обучающихся, сохранив доступ по прямой / быстрой ссылке.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('course_modules')) {
            return;
        }
        if (Schema::hasColumn('course_modules', 'hidden_from_catalog')) {
            return;
        }

        Schema::table('course_modules', function (Blueprint $table) {
            $table->boolean('hidden_from_catalog')->default(false)->after('view_audience');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('course_modules') || ! Schema::hasColumn('course_modules', 'hidden_from_catalog')) {
            return;
        }
        Schema::table('course_modules', function (Blueprint $table) {
            $table->dropColumn('hidden_from_catalog');
        });
    }
};
