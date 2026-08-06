<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Course;
use App\Models\CourseModule;
use Illuminate\Support\Facades\Schema;

/**
 * Отображение процентов и баллов для обучающихся: дефолт курса, переопределение модуля.
 */
final class LearnerScoreDisplay
{
    public static function showPercents(?Course $course, ?CourseModule $module = null): bool
    {
        return self::resolve($course, $module, 'show_score_percents');
    }

    public static function showPoints(?Course $course, ?CourseModule $module = null): bool
    {
        return self::resolve($course, $module, 'show_score_points');
    }

    /**
     * @return array{showScorePercents: bool, showScorePoints: bool}
     */
    public static function flags(?Course $course, ?CourseModule $module = null): array
    {
        return [
            'showScorePercents' => self::showPercents($course, $module),
            'showScorePoints' => self::showPoints($course, $module),
        ];
    }

    private static function resolve(?Course $course, ?CourseModule $module, string $column): bool
    {
        if ($module !== null && Schema::hasColumn('course_modules', $column)) {
            $raw = $module->getAttributes()[$column] ?? null;
            if ($raw !== null) {
                return (bool) (int) $raw;
            }
        }

        if ($course === null || ! Schema::hasColumn('courses', $column)) {
            return true;
        }

        return (bool) ($course->{$column} ?? true);
    }
}
