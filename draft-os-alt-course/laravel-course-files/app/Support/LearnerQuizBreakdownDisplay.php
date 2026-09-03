<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\CourseSection;
use Illuminate\Support\Facades\Schema;

/**
 * Режим разбора теста/экзамена для обучающихся: все вопросы или только ошибки.
 * Курс → модуль (nullable = inherit) → раздел (settings.breakdown_mode_from_parent).
 */
final class LearnerQuizBreakdownDisplay
{
    public const MODE_ALL = 'all';

    public const MODE_WRONGS = 'wrongs';

    public static function normalize(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $v = strtolower(trim((string) $value));
        if ($v === self::MODE_ALL || $v === self::MODE_WRONGS) {
            return $v;
        }

        return null;
    }

    public static function label(string $mode): string
    {
        return $mode === self::MODE_WRONGS ? 'Только ошибки' : 'Все вопросы';
    }

    public static function forCourse(?Course $course): string
    {
        if ($course === null || ! Schema::hasColumn('courses', 'quiz_breakdown_mode')) {
            return self::MODE_ALL;
        }

        return self::normalize($course->quiz_breakdown_mode) ?? self::MODE_ALL;
    }

    public static function forModule(?Course $course, ?CourseModule $module): string
    {
        if ($module !== null && Schema::hasColumn('course_modules', 'quiz_breakdown_mode')) {
            $raw = $module->getAttributes()['quiz_breakdown_mode'] ?? null;
            $own = self::normalize(is_string($raw) || is_numeric($raw) ? (string) $raw : null);
            if ($own !== null) {
                return $own;
            }
        }

        return self::forCourse($course);
    }

    public static function forSection(CourseSection $section, ?CourseModule $module = null, ?Course $course = null): string
    {
        $section->loadMissing('sectionSettings');
        $settings = is_array($section->sectionSettings?->settings) ? $section->sectionSettings->settings : [];

        // Явное «своё» на разделе; иначе — модуль/курс.
        $fromParent = $settings['breakdown_mode_from_parent'] ?? true;
        if ($fromParent === false || $fromParent === 0 || $fromParent === '0') {
            $own = self::normalize($settings['breakdown_mode'] ?? null);
            if ($own !== null) {
                return $own;
            }
        }

        if ($module === null && (int) $section->course_module_id > 0) {
            $module = CourseModule::query()->find((int) $section->course_module_id);
        }
        if ($course === null) {
            if ($module !== null) {
                $module->loadMissing('course');
                $course = $module->course;
            }
            if ($course === null && (int) $section->course_id > 0) {
                $course = Course::query()->find((int) $section->course_id);
            }
        }

        return self::forModule($course, $module);
    }

    /**
     * @param  list<array<string, mixed>>|array<int, mixed>  $items
     * @return list<array<string, mixed>>
     */
    public static function filterItems(array $items, string $mode): array
    {
        $mode = self::normalize($mode) ?? self::MODE_ALL;
        $out = [];
        foreach ($items as $it) {
            if (! is_array($it)) {
                continue;
            }
            if ($mode === self::MODE_WRONGS) {
                if (empty($it['correct']) || ! empty($it['skipped'])) {
                    $out[] = $it;
                }
            } else {
                $out[] = $it;
            }
        }

        return $out;
    }
}
