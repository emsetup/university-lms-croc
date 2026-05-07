<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('final_lab_results') || Schema::hasColumn('final_lab_results', 'course_id') || ! Schema::hasTable('courses')) {
            return;
        }

        Schema::table('final_lab_results', function (Blueprint $table) {
            $table->foreignId('course_id')->nullable()->after('learner_id')->constrained('courses')->cascadeOnDelete();
            $table->index(['course_id', 'certificate_issued_at']);
        });

        // Backfill existing rows to the default course (Alt).
        $defaultCourseId = (int) DB::table('courses')->where('slug', 'alt-os-features')->value('id');
        if ($defaultCourseId > 0) {
            DB::table('final_lab_results')->whereNull('course_id')->update(['course_id' => $defaultCourseId]);
        }

        // Enforce uniqueness per learner+course (instead of learner global).
        try {
            Schema::table('final_lab_results', function (Blueprint $table) {
                $table->dropUnique(['learner_id']);
            });
        } catch (\Throwable) {
            // best effort: different DBs may have different index names
        }
        Schema::table('final_lab_results', function (Blueprint $table) {
            $table->unique(['learner_id', 'course_id']);
        });
    }

    public function down(): void
    {
        // Откат не поддерживается: меняется ключ уникальности.
    }
};

