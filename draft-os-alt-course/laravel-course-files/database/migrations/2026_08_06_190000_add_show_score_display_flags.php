<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('courses')) {
            Schema::table('courses', function (Blueprint $table) {
                if (! Schema::hasColumn('courses', 'show_score_percents')) {
                    $table->boolean('show_score_percents')->default(true)->after('assessment_enabled');
                }
                if (! Schema::hasColumn('courses', 'show_score_points')) {
                    $table->boolean('show_score_points')->default(true)->after('show_score_percents');
                }
            });
        }

        if (Schema::hasTable('course_modules')) {
            Schema::table('course_modules', function (Blueprint $table) {
                if (! Schema::hasColumn('course_modules', 'show_score_percents')) {
                    $table->boolean('show_score_percents')->nullable()->after('view_audience');
                }
                if (! Schema::hasColumn('course_modules', 'show_score_points')) {
                    $table->boolean('show_score_points')->nullable()->after('show_score_percents');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('courses')) {
            Schema::table('courses', function (Blueprint $table) {
                $drop = [];
                if (Schema::hasColumn('courses', 'show_score_percents')) {
                    $drop[] = 'show_score_percents';
                }
                if (Schema::hasColumn('courses', 'show_score_points')) {
                    $drop[] = 'show_score_points';
                }
                if ($drop !== []) {
                    $table->dropColumn($drop);
                }
            });
        }

        if (Schema::hasTable('course_modules')) {
            Schema::table('course_modules', function (Blueprint $table) {
                $drop = [];
                if (Schema::hasColumn('course_modules', 'show_score_percents')) {
                    $drop[] = 'show_score_percents';
                }
                if (Schema::hasColumn('course_modules', 'show_score_points')) {
                    $drop[] = 'show_score_points';
                }
                if ($drop !== []) {
                    $table->dropColumn($drop);
                }
            });
        }
    }
};
