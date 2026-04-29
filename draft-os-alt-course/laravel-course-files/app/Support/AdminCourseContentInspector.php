<?php

namespace App\Support;

/**
 * Сводка по содержимому модуля для админ-панели (теория, тесты, практика).
 */
final class AdminCourseContentInspector
{
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
