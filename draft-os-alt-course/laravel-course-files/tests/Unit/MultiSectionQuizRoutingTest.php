<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\CourseSection;
use App\Support\LearnerRoute;
use PHPUnit\Framework\TestCase;

/**
 * Маршрутизация learner-разделов: quiz/exam/survey/text — свой URL с ordinal section.
 */
final class MultiSectionQuizRoutingTest extends TestCase
{
    public function test_quiz_section_uses_section_scoped_route_name(): void
    {
        $sec = new CourseSection(['type' => CourseSection::TYPE_QUIZ]);

        $this->assertSame('course.module.section.theory-quiz', $sec->learnerRouteName());
    }

    public function test_exam_section_uses_section_scoped_route_name(): void
    {
        $sec = new CourseSection(['type' => CourseSection::TYPE_EXAM]);

        $this->assertSame('course.module.section.exam', $sec->learnerRouteName());
    }

    public function test_survey_still_uses_section_scoped_route_name(): void
    {
        $sec = new CourseSection(['type' => CourseSection::TYPE_SURVEY]);

        $this->assertSame('course.module.section.survey', $sec->learnerRouteName());
    }

    public function test_text_section_uses_section_scoped_theory_route(): void
    {
        $sec = new CourseSection(['type' => CourseSection::TYPE_TEXT]);

        $this->assertSame('course.module.section.theory', $sec->learnerRouteName());
    }

    public function test_different_quiz_sections_get_distinct_route_params(): void
    {
        $first = LearnerRoute::section(1, 2, 3);
        $second = LearnerRoute::section(1, 2, 4);

        $this->assertNotSame($first, $second);
        $this->assertSame(3, $first['section']);
        $this->assertSame(4, $second['section']);
    }

    public function test_hub_params_exclude_section_ordinal(): void
    {
        $hub = LearnerRoute::hub(5, 1);

        $this->assertSame(['course' => 5, 'module' => 1], $hub);
        $this->assertArrayNotHasKey('section', $hub);
    }

    public function test_text_section_route_params_include_section_ordinal(): void
    {
        $sec = new CourseSection([
            'type' => CourseSection::TYPE_TEXT,
            'id' => 42,
            'course_module_id' => 7,
        ]);
        // sequenceForSection requires DB/service; pass ordinal explicitly.
        $params = $sec->learnerRouteParams(1, 2, 5);

        $this->assertSame(['course' => 1, 'module' => 2, 'section' => 5], $params);
    }
}
