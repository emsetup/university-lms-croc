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
     * Сводка и шкалы прохождения модулей/этапов для обучающихся.
     * Скрывается при свободном доступе ко всем модулям и при явном отключении в настройках курса.
     */
    public static function showModuleProgress(?Course $course): bool
    {
        if ($course === null) {
            return true;
        }
        if (Schema::hasColumn('courses', 'unlock_all_modules') && (bool) ($course->unlock_all_modules ?? false)) {
            return false;
        }
        if (! Schema::hasColumn('courses', 'show_module_progress')) {
            return true;
        }

        return (bool) ($course->show_module_progress ?? true);
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
