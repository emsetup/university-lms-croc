<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Режим разбора теста для обучающихся: all | wrongs.
 * Курс — дефолт; модуль nullable = наследовать; раздел — в JSON settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('courses') && ! Schema::hasColumn('courses', 'quiz_breakdown_mode')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->string('quiz_breakdown_mode', 16)->default('all')->after('show_score_points');
            });
        }

        if (Schema::hasTable('course_modules') && ! Schema::hasColumn('course_modules', 'quiz_breakdown_mode')) {
            Schema::table('course_modules', function (Blueprint $table) {
                $after = Schema::hasColumn('course_modules', 'show_score_points')
                    ? 'show_score_points'
                    : (Schema::hasColumn('course_modules', 'show_score_percents') ? 'show_score_percents' : 'view_audience');
                $table->string('quiz_breakdown_mode', 16)->nullable()->after($after);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('courses') && Schema::hasColumn('courses', 'quiz_breakdown_mode')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->dropColumn('quiz_breakdown_mode');
            });
        }
        if (Schema::hasTable('course_modules') && Schema::hasColumn('course_modules', 'quiz_breakdown_mode')) {
            Schema::table('course_modules', function (Blueprint $table) {
                $table->dropColumn('quiz_breakdown_mode');
            });
        }
    }
};
