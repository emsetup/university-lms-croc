<?php

namespace Tests\Unit;

use App\Support\LearnerRoute;
use PHPUnit\Framework\TestCase;

/**
 * Проверка формата параметров learner-маршрутов (курс → модуль → раздел).
 */
final class CourseSectionSequenceTest extends TestCase
{
    public function test_learner_route_hub_params(): void
    {
        $this->assertSame(['course' => 3, 'module' => 5], LearnerRoute::hub(3, 5));
    }

    public function test_learner_route_section_params(): void
    {
        $this->assertSame(
            ['course' => 3, 'module' => 5, 'section' => 2],
            LearnerRoute::section(3, 5, 2)
        );
    }
}
