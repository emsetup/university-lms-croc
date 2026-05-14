<?php

namespace App\Services;

use App\Http\Controllers\AdminQuizController;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\CourseModuleContent;
use App\Models\CourseQuizBank;
use App\Support\CourseModuleMeta;
use App\Support\CourseQuizBankLoader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Однократный перенос теории/практики и банков вопросов курса «ОС Альт» (slug alt-os-features)
 * из config/snippets в таблицы course_module_contents и course_quiz_*.
 */
final class LegacyAltCourseContentBootstrap
{
    public const SLUG = 'alt-os-features';

    public static function sync(): void
    {
        if (! Schema::hasTable('courses') || ! Schema::hasTable('course_modules')) {
            return;
        }

        $course = Course::query()->where('slug', self::SLUG)->first();
        if (! $course instanceof Course || ! $course->isLegacyAltCourse()) {
            return;
        }

        $quiz = app(AdminQuizController::class);

        DB::transaction(function () use ($course, $quiz): void {
            $modules = CourseModule::query()
                ->where('course_id', (int) $course->id)
                ->orderBy('sort')
                ->orderBy('id')
                ->get();

            foreach ($modules as $cm) {
                $idx = $cm->effectiveContentIndex();
                $meta = CourseModuleMeta::resolved($idx);
                $theory = (string) ($meta['theory'] ?? '');
                $practice = (string) ($meta['practice'] ?? '');

                if (Schema::hasTable('course_module_contents')) {
                    CourseModuleContent::query()->updateOrCreate(
                        ['course_module_id' => (int) $cm->id],
                        ['theory_markdown' => $theory, 'practice_markdown' => $practice]
                    );
                }

                if (! Schema::hasTable('course_quiz_banks')) {
                    continue;
                }

                foreach (['theory_quiz', 'module_exam'] as $kind) {
                    self::syncQuizBankForModule($course, $cm, $idx, $kind, $quiz);
                }
            }
        });

        app(CourseSectionService::class)->clearCache();
    }

    private static function syncQuizBankForModule(Course $course, CourseModule $cm, int $idx, string $kind, AdminQuizController $quiz): void
    {
        $jsonPath = config_path(sprintf('snippets/module_%02d_%s_questions.json', $idx, $kind));
        $phpPath = config_path(sprintf('snippets/module_%02d_%s_questions.php', $idx, $kind));
        $raw = CourseQuizBankLoader::loadBankWithFallback($jsonPath, is_file($phpPath) ? $phpPath : null);
        if ($raw === []) {
            return;
        }

        $validated = $quiz->validateQuizBankFormat($raw, $kind, true);
        if ($validated['ok'] !== true) {
            throw new \RuntimeException(
                'LegacyAltCourseContentBootstrap: '.$kind.' модуля '.$idx.': '.($validated['message'] ?? 'ошибка формата вопросов.')
            );
        }

        $defaults = $kind === 'theory_quiz'
            ? ['pass_percent' => 70, 'time_limit_minutes' => 30, 'attempt_limit' => null, 'shuffle' => false, 'one_by_one' => true, 'breakdown_visible_minutes' => 15, 'penalties_json' => ['2' => 10]]
            : ['pass_percent' => 70, 'time_limit_minutes' => 60, 'attempt_limit' => 2, 'shuffle' => false, 'one_by_one' => true, 'breakdown_visible_minutes' => 30, 'penalties_json' => ['2' => 10]];

        $bank = CourseQuizBank::query()->firstOrCreate(
            [
                'course_id' => (int) $course->id,
                'course_module_id' => (int) $cm->id,
                'kind' => $kind,
            ],
            $defaults
        );

        $quiz->persistQuizBankItems($bank, $validated['data']);
    }
}
