<?php

namespace App\Support;

use App\Models\CourseSection;
use App\Services\CourseSectionService;

/**
 * Единый формат параметров learner-маршрутов: курс → модуль (ordinal) → раздел (ordinal).
 */
final class LearnerRoute
{
    /**
     * @return array{course: int, module: int}
     */
    public static function hub(int $courseId, int $moduleSequence): array
    {
        return [
            'course' => $courseId,
            'module' => $moduleSequence,
        ];
    }

    /**
     * @return array{course: int, module: int, section: int}
     */
    public static function section(int $courseId, int $moduleSequence, int $sectionSequence): array
    {
        return [
            'course' => $courseId,
            'module' => $moduleSequence,
            'section' => $sectionSequence,
        ];
    }

    /**
     * @return array{course: int, module: int}|array{course: int, module: int, section: int}
     */
    public static function forSection(
        int $courseId,
        int $moduleSequence,
        CourseSection $section,
        ?CourseSectionService $sections = null,
    ): array {
        $sections ??= app(CourseSectionService::class);
        $sectionSeq = $sections->sequenceForSection($section);
        $routeName = $section->learnerRouteName();
        if ($routeName === 'course.module.section.survey') {
            return self::section($courseId, $moduleSequence, $sectionSeq);
        }

        return self::hub($courseId, $moduleSequence);
    }
}
