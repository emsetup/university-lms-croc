<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Практика финальной лабы в practice_sessions: module_id = 0 (не пересекается с course_modules.id). */
    public const FINAL_LAB_PRACTICE_SESSION_MODULE_ID = 0;

    public function up(): void
    {
        if (! Schema::hasTable('courses')) {
            return;
        }

        if (! Schema::hasTable('course_modules')) {
            Schema::create('course_modules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
                $table->unsignedInteger('sort')->default(100);
                $table->string('title');
                $table->text('summary')->default('');
                $table->string('letter', 8)->nullable();
                $table->unsignedSmallInteger('content_source_index')->nullable();
                $table->timestamps();
                $table->index(['course_id', 'sort']);
            });
        }

        $this->seedCourseModulesFromConfig();

        if (Schema::hasTable('course_sections')
            && Schema::hasColumn('course_sections', 'course_id')
            && ! Schema::hasColumn('course_sections', 'course_module_id')) {
            $this->migrateCourseSectionsToModules();
        }

        if (Schema::hasTable('module_progress')
            && Schema::hasColumn('module_progress', 'module_id')
            && ! Schema::hasColumn('module_progress', 'course_module_id')) {
            $this->migrateModuleProgress();
        }

        if (Schema::hasTable('practice_sessions')
            && Schema::hasColumn('practice_sessions', 'module_id')
            && ! Schema::hasColumn('practice_sessions', 'course_id')) {
            $this->migratePracticeSessions();
        }
    }

    public function down(): void
    {
        // Откат не поддерживается: данные module_progress/practice_sessions несовместимы со старой схемой.
    }

    private function seedCourseModulesFromConfig(): void
    {
        $mods = config('course.modules');
        if (! is_array($mods) || $mods === []) {
            return;
        }
        $courseId = (int) DB::table('courses')->where('slug', 'alt-os-features')->value('id');
        if ($courseId < 1) {
            return;
        }
        if (DB::table('course_modules')->where('course_id', $courseId)->exists()) {
            return;
        }
        $sort = 10;
        $now = now();
        ksort($mods, SORT_NUMERIC);
        foreach ($mods as $idx => $meta) {
            $idx = (int) $idx;
            if (! is_array($meta)) {
                continue;
            }
            DB::table('course_modules')->insert([
                'course_id' => $courseId,
                'sort' => $sort,
                'title' => (string) ($meta['title'] ?? 'Модуль '.$idx),
                'summary' => (string) ($meta['summary'] ?? ''),
                'letter' => isset($meta['letter']) ? (string) $meta['letter'] : null,
                'content_source_index' => $idx,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $sort += 10;
        }
    }

    private function migrateCourseSectionsToModules(): void
    {
        Schema::table('course_sections', function (Blueprint $table) {
            $table->foreignId('course_module_id')->nullable()->after('course_id')->constrained('course_modules')->cascadeOnDelete();
        });

        if (DB::table('course_sections')->count() === 0) {
            return;
        }

        $this->dropUniqueIfExists('course_sections', 'course_sections_course_id_type_unique', ['course_id', 'type']);

        $templates = DB::table('course_sections')->whereNull('course_module_id')->orderBy('sort')->orderBy('id')->get();
        if ($templates->isEmpty()) {
            return;
        }

        $courseIds = $templates->pluck('course_id')->unique()->all();
        foreach ($courseIds as $courseId) {
            $courseId = (int) $courseId;
            $rows = $templates->where('course_id', $courseId)->values();
            if ($rows->isEmpty()) {
                continue;
            }
            $modules = DB::table('course_modules')->where('course_id', $courseId)->orderBy('sort')->orderBy('id')->get();
            if ($modules->isEmpty()) {
                continue;
            }
            foreach ($modules as $mod) {
                foreach ($rows as $tpl) {
                    $sid = DB::table('course_sections')->insertGetId([
                        'course_id' => $courseId,
                        'course_module_id' => $mod->id,
                        'type' => $tpl->type,
                        'title' => $tpl->title,
                        'sort' => $tpl->sort,
                        'is_enabled' => $tpl->is_enabled,
                        'created_at' => $tpl->created_at ?? now(),
                        'updated_at' => $tpl->updated_at ?? now(),
                    ]);
                    $set = DB::table('course_section_settings')->where('course_section_id', $tpl->id)->first();
                    if ($set) {
                        DB::table('course_section_settings')->insert([
                            'course_section_id' => $sid,
                            'settings' => $set->settings,
                            'created_at' => $set->created_at ?? now(),
                            'updated_at' => $set->updated_at ?? now(),
                        ]);
                    }
                }
            }
            $oldIds = $rows->pluck('id')->all();
            DB::table('course_section_settings')->whereIn('course_section_id', $oldIds)->delete();
            DB::table('course_sections')->whereIn('id', $oldIds)->delete();
        }

        DB::table('course_sections')->whereNull('course_module_id')->delete();

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE course_sections MODIFY course_module_id BIGINT UNSIGNED NOT NULL');
        }

        Schema::table('course_sections', function (Blueprint $table) {
            $table->unique(['course_module_id', 'type']);
        });
    }

    private function migrateModuleProgress(): void
    {
        $defaultCourseId = (int) DB::table('courses')->where('slug', 'alt-os-features')->value('id');
        if ($defaultCourseId < 1) {
            return;
        }

        $map = DB::table('course_modules')
            ->where('course_id', $defaultCourseId)
            ->pluck('id', 'content_source_index')
            ->all();

        Schema::table('module_progress', function (Blueprint $table) {
            $table->foreignId('course_id')->nullable()->after('learner_id')->constrained('courses')->cascadeOnDelete();
            $table->unsignedBigInteger('course_module_id')->nullable()->after('course_id');
        });

        foreach (DB::table('module_progress')->cursor() as $row) {
            $oldMid = (int) $row->module_id;
            $newCm = $map[$oldMid] ?? null;
            if ($newCm === null) {
                DB::table('module_progress')->where('id', $row->id)->delete();

                continue;
            }
            DB::table('module_progress')->where('id', $row->id)->update([
                'course_id' => $defaultCourseId,
                'course_module_id' => (int) $newCm,
            ]);
        }

        $this->dropUniqueIfExists('module_progress', 'module_progress_learner_id_module_id_unique', ['learner_id', 'module_id']);

        Schema::table('module_progress', function (Blueprint $table) {
            $table->dropColumn('module_id');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE module_progress MODIFY course_id BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE module_progress MODIFY course_module_id BIGINT UNSIGNED NOT NULL');
        }

        Schema::table('module_progress', function (Blueprint $table) {
            $table->foreign('course_module_id')->references('id')->on('course_modules')->cascadeOnDelete();
            $table->unique(['learner_id', 'course_id', 'course_module_id']);
        });
    }

    private function migratePracticeSessions(): void
    {
        $defaultCourseId = (int) DB::table('courses')->where('slug', 'alt-os-features')->value('id');
        if ($defaultCourseId < 1) {
            return;
        }

        $map = DB::table('course_modules')
            ->where('course_id', $defaultCourseId)
            ->pluck('id', 'content_source_index')
            ->all();

        Schema::table('practice_sessions', function (Blueprint $table) {
            $table->foreignId('course_id')->nullable()->after('learner_id')->constrained('courses')->cascadeOnDelete();
        });

        Schema::table('practice_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('module_id_big')->nullable()->after('module_id');
        });

        foreach (DB::table('practice_sessions')->cursor() as $s) {
            $mid = (int) $s->module_id;
            if ($mid === 10) {
                DB::table('practice_sessions')->where('id', $s->id)->update([
                    'course_id' => $defaultCourseId,
                    'module_id_big' => self::FINAL_LAB_PRACTICE_SESSION_MODULE_ID,
                ]);

                continue;
            }
            $newCm = $map[$mid] ?? null;
            if ($newCm === null) {
                DB::table('practice_sessions')->where('id', $s->id)->delete();

                continue;
            }
            DB::table('practice_sessions')->where('id', $s->id)->update([
                'course_id' => $defaultCourseId,
                'module_id_big' => (int) $newCm,
            ]);
        }

        $this->dropUniqueIfExists('practice_sessions', 'practice_sessions_learner_id_module_id_unique', ['learner_id', 'module_id']);

        Schema::table('practice_sessions', function (Blueprint $table) {
            $table->dropColumn('module_id');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE practice_sessions CHANGE module_id_big module_id BIGINT UNSIGNED NOT NULL');
        } else {
            Schema::table('practice_sessions', function (Blueprint $table) {
                $table->renameColumn('module_id_big', 'module_id');
            });
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE practice_sessions MODIFY course_id BIGINT UNSIGNED NOT NULL');
        }

        Schema::table('practice_sessions', function (Blueprint $table) {
            $table->unique(['learner_id', 'course_id', 'module_id']);
        });
    }

    /**
     * @param  list<string>  $columns
     */
    private function dropUniqueIfExists(string $table, string $preferredName, array $columns): void
    {
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                $blueprint->dropUnique($columns);
            });
        } catch (\Throwable) {
            try {
                Schema::table($table, function (Blueprint $blueprint) use ($preferredName) {
                    $blueprint->dropIndex($preferredName);
                });
            } catch (\Throwable) {
            }
        }
    }
};
