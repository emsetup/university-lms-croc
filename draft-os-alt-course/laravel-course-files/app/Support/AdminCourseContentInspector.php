<?php

namespace App\Support;

use App\Models\Course;
use App\Models\CourseModule;
use App\Services\CourseContentService;
use App\Services\CourseSectionService;
use Illuminate\Support\Facades\Schema;

/**
 * Сводка по содержимому модуля для админ-панели (теория, тесты, практика).
 */
final class AdminCourseContentInspector
{
    /**
     * Сводка по модулю курса из БД (course_module_contents, course_quiz_*).
     *
     * @return array{
     *     theory_chars: int,
     *     theory_markdown: string,
     *     practice_markdown: string,
     *     theory_quiz: list<array<string, mixed>>,
     *     exam: list<array<string, mixed>>,
     *     exam_time_min: int
     * }
     */
    public static function databaseModuleContentSummary(Course $course, CourseModule $cm): array
    {
        $contentSvc = app(CourseContentService::class);
        $stored = $contentSvc->contentForModule($cm);
        $theoryMd = (string) ($stored['theory_markdown'] ?? '');
        $practiceMd = (string) ($stored['practice_markdown'] ?? '');
        $tq = [];
        $ex = [];
        if (Schema::hasTable('course_quiz_banks')) {
            $tqBank = $contentSvc->quizBankFor($course, $cm, 'theory_quiz');
            if ($tqBank !== null) {
                $tq = $contentSvc->questionsForBank($tqBank);
            }
            $exBank = $contentSvc->quizBankFor($course, $cm, 'module_exam');
            if ($exBank !== null) {
                $ex = $contentSvc->questionsForBank($exBank);
            }
        }
        $idx = $cm->effectiveContentIndex();
        $examMin = app(CourseSectionService::class)->examTimeLimitMinutes((int) $cm->id, $idx, false);

        return [
            'theory_chars' => mb_strlen($theoryMd),
            'theory_markdown' => $theoryMd,
            'practice_markdown' => $practiceMd,
            'theory_quiz' => array_values($tq),
            'exam' => array_values($ex),
            'exam_time_min' => $examMin,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function theoryQuizQuestions(int $module): array
    {
        $q = config('course.module_quizzes.'.$module.'.theory_quiz', []);

        return is_array($q) ? array_values($q) : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function moduleExamQuestions(int $module): array
    {
        $q = config('course.module_quizzes.'.$module.'.module_exam', []);

        return is_array($q) ? array_values($q) : [];
    }

    public static function practiceMarkdown(int $module): string
    {
        $meta = CourseModuleMeta::resolved($module);
        $p = $meta['practice'] ?? '';

        return is_string($p) ? $p : '';
    }

    /**
     * Длина текста теории после подстановки сниппетов (как у студента на странице теории).
     */
    public static function theoryCharacterCount(int $module): int
    {
        $meta = CourseModuleMeta::resolved($module);
        $t = $meta['theory'] ?? '';

        return is_string($t) ? mb_strlen($t) : 0;
    }

    /**
     * @param  list<array<string, mixed>>  $questions
     */
    public static function countMatchDrag(array $questions): int
    {
        $n = 0;
        foreach ($questions as $q) {
            if (! empty($q['match_drag'])) {
                $n++;
            }
        }

        return $n;
    }

    public static function practiceSummaryLine(string $markdown): string
    {
        if ($markdown === '') {
            return 'нет текста';
        }
        $chars = mb_strlen($markdown);
        $lines = substr_count($markdown, "\n") + 1;

        return sprintf('~%s симв., ~%d стр.', number_format($chars, 0, ',', ' '), $lines);
    }

    /**
     * Имя образа из practice_lab.images для модуля (как в {@see \App\Services\PracticeLabService::imageForModule}).
     * null — ключа нет или строка пустая.
     */
    public static function practiceLabDockerImageForModule(int $module): ?string
    {
        $images = config('practice_lab.images', []);
        if (! is_array($images)) {
            return null;
        }
        $key = (string) $module;
        if (! isset($images[$key])) {
            return null;
        }
        $v = trim((string) $images[$key]);

        return $v !== '' ? $v : null;
    }
}
