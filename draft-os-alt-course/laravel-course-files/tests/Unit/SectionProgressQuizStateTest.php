<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\CourseSection;
use App\Models\ModuleProgress;
use App\Support\SectionProgress;
use PHPUnit\Framework\TestCase;

/**
 * Прогресс quiz/exam хранится отдельно по section_id в section_states.
 */
final class SectionProgressQuizStateTest extends TestCase
{
    public function test_quiz_state_reads_per_section_from_section_states(): void
    {
        if (! $this->schemaHasSectionStates()) {
            $this->markTestSkipped('section_states column not available in this environment');
        }

        $p = new ModuleProgress;
        $p->section_states = [
            '101' => [
                'attempts' => 2,
                'best_score' => 85,
                'passed' => true,
                'last_result' => ['final_percent' => 85],
                'history' => [],
            ],
            '102' => [
                'attempts' => 1,
                'best_score' => 40,
                'passed' => false,
                'last_result' => ['final_percent' => 40],
                'history' => [],
            ],
        ];

        $secA = new CourseSection(['id' => 101, 'type' => CourseSection::TYPE_QUIZ]);
        $secB = new CourseSection(['id' => 102, 'type' => CourseSection::TYPE_QUIZ]);

        $stA = SectionProgress::quizState($p, $secA, false);
        $stB = SectionProgress::quizState($p, $secB, false);

        $this->assertSame(2, $stA['attempts']);
        $this->assertSame(85, $stA['best_score']);
        $this->assertTrue($stA['passed']);

        $this->assertSame(1, $stB['attempts']);
        $this->assertSame(40, $stB['best_score']);
        $this->assertFalse($stB['passed']);
    }

    public function test_save_quiz_state_does_not_overwrite_sibling_section(): void
    {
        if (! $this->schemaHasSectionStates()) {
            $this->markTestSkipped('section_states column not available in this environment');
        }

        $p = new ModuleProgress;
        $p->section_states = [
            '201' => ['attempts' => 1, 'best_score' => 70, 'passed' => true, 'history' => []],
        ];

        $secB = new CourseSection(['id' => 202, 'type' => CourseSection::TYPE_QUIZ]);
        SectionProgress::saveQuizState($p, $secB, false, [
            'attempts' => 1,
            'best_score' => 90,
            'passed' => true,
            'last_result' => ['final_percent' => 90],
            'history' => [['final_percent' => 90]],
        ]);

        $states = $p->section_states;
        $this->assertIsArray($states);
        $this->assertSame(70, $states['201']['best_score']);
        $this->assertSame(90, $states['202']['best_score']);
    }

    private function schemaHasSectionStates(): bool
    {
        if (! class_exists(\Illuminate\Support\Facades\Schema::class)) {
            return false;
        }

        try {
            return \Illuminate\Support\Facades\Schema::hasColumn('module_progress', 'section_states');
        } catch (\Throwable) {
            return false;
        }
    }
}
